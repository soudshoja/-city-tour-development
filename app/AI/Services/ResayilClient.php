<?php

namespace App\AI\Services;

use App\AI\Contracts\AIClientInterface;
use App\AI\Support\AIResponse;
use App\AI\Support\PdfExtractionPrompt;
use App\Schema\TaskSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resayil LLM provider — OpenAI-compatible /v1/chat/completions, Bearer auth.
 * No Files API: images are sent inline as base64 data URLs. Vision model
 * (qwen3-vl) for documents, cheaper text model for plain chat.
 *
 * Plan A scope (2026-06-04): chat() + extractPassportData() are live. The
 * schema-driven extraction methods return an error result on purpose so
 * FallbackAIClient routes them to OpenAI until Plan B implements Resayil parity.
 * See docs/superpowers/specs/2026-06-04-resayil-ai-provider-fallback-design.md
 */
class ResayilClient implements AIClientInterface
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $model;          // vision (chain-driven; used for PDF extraction)
    protected ?string $chainModel;    // model this hop was pinned to by config('ai.chain'), null when not chain-built
    protected string $modelText;      // text-only
    protected string $modelPassport;  // vision model tuned for passports / Arabic
    protected int $maxPdfPages;
    protected int $timeout;
    protected $logger;

    public function __construct(?string $visionModel = null)
    {
        $this->logger = Log::channel('ai');
        $this->apiUrl = rtrim((string) config('ai.providers.resayil.url', ''), '/');
        $this->apiKey = (string) config('ai.providers.resayil.key', '');
        // A per-instance vision model lets the fallback chain run e.g.
        // qwen3-vl:235b-instruct (fast) then qwen3-vl:235b (thorough).
        $this->chainModel = ($visionModel !== null && trim($visionModel) !== '') ? trim($visionModel) : null;
        $this->model = $this->chainModel ?: (string) config('ai.providers.resayil.model', 'qwen3-vl:235b');
        $this->modelText = (string) config('ai.providers.resayil.model_text', 'gpt-oss:120b');
        // Passport/ID images carry Arabic; gemma3:27b reads Arabic best of the
        // fast vision models (benchmarked 2026-06-08), so passports use a
        // dedicated model independent of the PDF-extraction chain model.
        $this->modelPassport = (string) config('ai.providers.resayil.model_passport', 'gemma3:27b');
        $this->maxPdfPages = (int) config('ai.providers.resayil.max_pdf_pages', 6);
        $this->timeout = (int) config('ai.providers.resayil.timeout', 120);

        // Only hard-fail when Resayil is actually the primary/fallback in use.
        $inUse = in_array('resayil', [config('ai.primary'), config('ai.fallback'), config('ai.default')], true);
        if ($inUse && config('app.env') !== 'testing' && (empty($this->apiUrl) || empty($this->apiKey))) {
            throw new \Exception('Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY).');
        }
    }

    /**
     * Single POST to /chat/completions. Returns decoded array on HTTP 2xx,
     * or throws on failure so the caller/decorator can classify it.
     */
    protected function callChat(string $model, array $messages, int $maxTokens = 1500): array
    {
        $url = $this->apiUrl . '/chat/completions';
        $response = Http::withToken($this->apiKey)
            ->withoutVerifying()
            ->timeout($this->timeout)
            ->post($url, [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Resayil HTTP {$response->status()}: " . substr($response->body(), 0, 300));
        }

        return $response->json() ?? [];
    }

    public function chat(array $messages): array
    {
        try {
            $result = $this->callChat($this->modelText, $messages);
            $content = $result['choices'][0]['message']['content'] ?? null;
            if ($content === null) {
                return AIResponse::error('Resayil chat: no content in response');
            }

            return AIResponse::success($content, 'Chat completed successfully', [
                'usage' => $result['usage'] ?? [],
                'model' => $this->modelText,
                'provider' => 'resayil',
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Resayil chat error: ' . $e->getMessage());
            return AIResponse::error('Resayil chat failed: ' . $e->getMessage());
        }
    }

    /**
     * Which vision model this instance uses for passport/ID extraction.
     *
     * AIManager builds ONE ResayilClient per chain hop. Until 2026-08-10
     * extractPassportData() ignored that and always called $this->modelPassport,
     * so hops 0 and 1 issued two IDENTICAL calls to the same model and the only
     * genuinely different fallback was OpenAI (whose key is dead: HTTP 429 'You
     * have no credits remaining'). One passport read therefore never got a
     * second opinion from a second model, however many times it retried.
     *
     * The passport path deliberately uses its OWN model ladder rather than the
     * chain's models, because the chain is tuned for TEXT/PDF work and most of
     * its models cannot see images at all. Verified against the live gateway on
     * 2026-08-10 with a 64x64 PNG:
     *   mistral-large-3:675b  OK      qwen3.5:397b  OK      kimi-k2.6  OK
     *   glm-5.1               HTTP 400 "does not support image input"
     *   gemma4:31b            HTTP 400 "does not support image input"
     *   minimax-m3            HTTP 200 but replies "I don't see an image"
     *                         — silently blind, the worst possible fallback
     * Anything added to passport_fallback_models MUST be re-verified that way;
     * a non-vision model here turns the fallback into a guaranteed failure or,
     * worse, a confident hallucination.
     *
     * Hop 0 keeps model_passport (benchmarked for Arabic passport/MRZ reading);
     * each later hop takes the next model in the ladder, so the fallback is a
     * real second opinion instead of a repeat of the same call.
     */
    protected function passportModel(): string
    {
        $ladder = $this->passportModelLadder();
        $hop = $this->chainHopIndex();

        return $ladder[$hop] ?? $ladder[count($ladder) - 1];
    }

    /** Ordered passport models: the tuned one first, then the verified alternates. */
    protected function passportModelLadder(): array
    {
        $ladder = [$this->modelPassport];
        foreach ((array) config('ai.providers.resayil.passport_fallback_models', []) as $model) {
            $model = trim((string) $model);
            if ($model !== '' && !in_array($model, $ladder, true)) {
                $ladder[] = $model;
            }
        }

        return $ladder;
    }

    /** This instance's position among the resayil hops of config('ai.chain'). */
    protected function chainHopIndex(): int
    {
        if ($this->chainModel === null) {
            return 0;
        }

        $hop = 0;
        foreach ((array) config('ai.chain', []) as $entry) {
            $provider = is_array($entry) ? ($entry['provider'] ?? null) : $entry;
            if ($provider !== 'resayil') {
                continue;
            }
            if ((string) (is_array($entry) ? ($entry['model'] ?? '') : '') === $this->chainModel) {
                return $hop;
            }
            $hop++;
        }

        return 0;
    }

    public function extractPassportData(string $filePath, string $fileName): array
    {
        try {
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'jpg';
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'])) {
                return AIResponse::error('Unsupported file type for Resayil passport extraction: ' . $extension);
            }

            $imageParts = $this->fileToImageParts($filePath, $extension);
            if (empty($imageParts)) {
                return AIResponse::error('Resayil: could not derive image from file (PDF rasterization unavailable)');
            }

            $prompt = "You are an assistant for a travel agency. Your task is to extract passport details from the provided image or document.\n\n"
                . "Analyze the document carefully and extract the following fields. Return the data in JSON format only:\n\n"
                . "- `passport_no`: Passport number or Passport No.\n"
                . "- `civil_no`: Civil number or Civil No. (if available)\n"
                . "- `name`: Full name as per the passport\n"
                . "- `first_name`: First name based on the first name from the 'Full Name'\n"
                . "- `middle_name`: Middle name (if available)\n"
                . "- `last_name`: Last name based on the last name from the 'Full Name'(if available)\n"
                . "- `nationality`: Nationality\n"
                . "- `date_of_birth`: Date of birth in YYYY-MM-DD format\n"
                . "- `date_of_issue`: Date of issue in YYYY-MM-DD format\n"
                . "- `date_of_expiry`: Date of expiry in YYYY-MM-DD format\n"
                . "- `place_of_birth`: Place of birth\n"
                . "- `place_of_issue`: Place of issue\n"
                . "- `gender`: M or F only (normalize Male->M, Female->F; read the Sex field)\n"
                . "- `nationality_code`: the 3-letter country code exactly as printed on the passport / MRZ (e.g. KWT, USA, IRN, IND)\n"
                . "- `country_of_issue`: 3-letter ISO code of the issuing country (MRZ line 1, the 3 letters right after 'P<')\n"
                . "- `mrz_line1`: the FIRST line of the Machine-Readable Zone at the bottom of the passport, transcribed EXACTLY including every '<' character\n"
                . "- `mrz_line2`: the SECOND MRZ line, transcribed EXACTLY including every '<' character\n\n"
                . "Important guidelines:\n"
                . "1. If a field is not found or not clearly visible, set its value to null\n"
                . "2. Ensure all dates are in YYYY-MM-DD format\n"
                . "3. Extract the full name exactly as it appears on the passport\n"
                . "4. `passport_no` is the document number printed at the top of the passport - include any leading letter (e.g. 'P06626745'). It is NEVER the 12-digit civil number; that value belongs in `civil_no` only\n"
                . "5. `last_name` is the FULL surname from the MRZ - ALL name components BEFORE the '<<' in mrz_line1, joined with single spaces (e.g. 'MOHAMED MOKHTAR FARRARA'); never truncate to one word. `first_name` is the given name(s) after the '<<'\n"
                . "6. `first_name`, `middle_name` and `last_name` must contain ONLY letters and single spaces - NEVER include any '<' character or MRZ filler. The '<' characters belong ONLY in `mrz_line1`/`mrz_line2`\n"
                . "7. `nationality` must be the full country name; `nationality_code` is the 3-letter code\n"
                . "8. Return only valid JSON format without any additional text or explanations";

            $userContent = array_merge(
                [['type' => 'text', 'text' => 'Please extract the passport information from this document and return it in the specified JSON format.']],
                $imageParts
            );
            $messages = [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $userContent],
            ];

            $passportModel = $this->passportModel();
            $result = $this->callChat($passportModel, $messages, 1500);
            $content = $result['choices'][0]['message']['content'] ?? null;
            if (!$content) {
                return AIResponse::error('Resayil passport: empty response');
            }

            $data = AIResponse::parseJsonContent($content);
            if (isset($data['raw_content']) || empty($data['first_name'])) {
                return AIResponse::error('Resayil passport: unparseable or missing first_name', $data);
            }

            return AIResponse::success($data, 'Passport data extracted successfully (resayil)', [
                'file_name' => $fileName, 'provider' => 'resayil', 'model' => $passportModel,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Resayil extractPassportData error: ' . $e->getMessage());
            return AIResponse::error('Resayil passport extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Returns OpenAI-style image_url content parts (inline base64 data URLs).
     * Images → single part. PDFs → up to maxPdfPages rasterized PNG parts
     * (Imagick first, then `pdftoppm`); returns [] if neither is available.
     */
    protected function fileToImageParts(string $filePath, string $extension): array
    {
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
            $b64 = base64_encode((string) file_get_contents($filePath));
            return [['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$b64}"]]];
        }

        // PDF → images
        $parts = [];
        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick();
                $im->setResolution(150, 150);
                $im->readImage($filePath);
                $pageCount = min($im->getNumberImages(), $this->maxPdfPages);
                for ($i = 0; $i < $pageCount; $i++) {
                    $im->setIteratorIndex($i);
                    $page = $im->getImage();
                    $page->setImageFormat('png');
                    $parts[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . base64_encode($page->getImageBlob())]];
                }
                $im->clear();
                if (!empty($parts)) {
                    return $parts;
                }
            } catch (Throwable $e) {
                $this->logger->warning('Resayil Imagick PDF rasterize failed: ' . $e->getMessage());
            }
        }

        if (function_exists('exec')) {
            $outPrefix = sys_get_temp_dir() . '/resayil_' . uniqid();
            @exec('pdftoppm -png -r 150 -l ' . $this->maxPdfPages . ' ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outPrefix) . ' 2>&1');
            $pages = glob($outPrefix . '*.png') ?: [];
            sort($pages);
            foreach (array_slice($pages, 0, $this->maxPdfPages) as $p) {
                $parts[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . base64_encode((string) file_get_contents($p))]];
                @unlink($p);
            }
        }

        return $parts;
    }

    // ---- Plan A: deferred to OpenAI via FallbackAIClient (Plan B implements these) ----
    public function processWithAiTool(string $filePath, string $fileName): array
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            // AIR/txt itineraries are handled by the deterministic AirFileParser;
            // defer non-PDF AI extraction to the next provider in the chain.
            return AIResponse::error('Resayil processWithAiTool: non-PDF deferred to fallback');
        }

        $response = $this->extractPdfFiles($filePath);
        if (($response['status'] ?? null) !== 'success') {
            return AIResponse::error($response['message'] ?? 'Resayil PDF extraction failed', null, [
                'original_filename' => $fileName,
            ]);
        }

        $extractedData = $response['data'] ?? null;
        if (!is_array($extractedData) || empty($extractedData)) {
            return AIResponse::error('Resayil PDF extraction returned no items', null, [
                'original_filename' => $fileName,
            ]);
        }

        // Normalize every item to the task schema, mirroring OpenAIClient so the
        // downstream ProcessAirFiles pipeline receives an identical shape.
        $processedItems = [];
        foreach ($extractedData as $item) {
            $processedItems[] = TaskSchema::normalize(array_merge(
                $item ?? [],
                [
                    'task_flight_details'    => $item['task_flight_details'] ?? [],
                    'task_hotel_details'     => $item['task_hotel_details'] ?? [],
                    'task_insurance_details' => $item['task_insurance_details'] ?? [],
                    'task_visa_details'      => $item['task_visa_details'] ?? [],
                ]
            ));
        }

        return [
            'success'           => true,
            'status'            => 'success',
            'message'           => "Successfully processed {$fileName} using Resayil ({$this->model}).",
            'original_filename' => $fileName,
            'data'              => $processedItems,
        ];
    }

    public function extractAirFiles(string $content): array
    {
        return AIResponse::error('Resayil extractAirFiles not enabled (Plan B) — defer to fallback');
    }

    /**
     * Extract structured booking tasks from a supplier PDF.
     *
     * citycomm has no Imagick and exec() is disabled, so PDF-to-image
     * rasterization is unavailable there. We therefore extract the PDF TEXT
     * (Smalot - the same library the deterministic parsers use) and send it to
     * the configured model with the shared PdfExtractionPrompt. If the PDF yields
     * no usable text (scanned/image-only) we fall back to rasterized images via
     * fileToImageParts() for environments that DO have Imagick; if neither is
     * available we error so the chain moves on.
     *
     * Callers (OpenAIClient parity) pass the file PATH as $fileId.
     * Returns ['status'=>'success','data'=>[ {task...}, ... ]] on success.
     */
    public function extractPdfFiles(string $fileId): array
    {
        $filePath = $fileId;
        try {
            if (!is_string($filePath) || !file_exists($filePath)) {
                return AIResponse::error('Resayil extractPdfFiles: file not found');
            }

            $text = '';
            try {
                $text = trim((new \Smalot\PdfParser\Parser())->parseFile($filePath)->getText());
            } catch (Throwable $e) {
                $this->logger->warning('Resayil extractPdfFiles: PDF text parse failed: ' . $e->getMessage());
            }

            $prompt = PdfExtractionPrompt::build();

            if (mb_strlen($text) >= 40) {
                $userContent = 'Here is the full text extracted from the uploaded supplier document. '
                    . 'Extract the booking(s) and return ONLY the JSON described, with the top-level '
                    . '"result" array. Do not include any commentary.' . "\n\nDOCUMENT TEXT:\n" . $text;
                $messages = [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $userContent],
                ];
            } else {
                $imageParts = $this->fileToImageParts($filePath, 'pdf');
                if (empty($imageParts)) {
                    return AIResponse::error('Resayil extractPdfFiles: no extractable text and no rasterizer (Imagick/exec) available');
                }
                $userContent = array_merge(
                    [['type' => 'text', 'text' => 'Extract the booking(s) from this document and return ONLY the JSON described, with the top-level "result" array.']],
                    $imageParts
                );
                $messages = [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $userContent],
                ];
            }

            $result  = $this->callChat($this->model, $messages, 8000);
            $content = $result['choices'][0]['message']['content'] ?? null;
            if (!$content) {
                return AIResponse::error('Resayil extractPdfFiles: empty response');
            }

            $decoded = AIResponse::parseJsonContent($content);
            if (isset($decoded['raw_content'])) {
                return AIResponse::error('Resayil extractPdfFiles: unparseable JSON', $decoded);
            }

            if (isset($decoded['result']) && is_array($decoded['result'])) {
                $items = $decoded['result'];
            } elseif (array_keys($decoded) === range(0, count($decoded) - 1)) {
                $items = $decoded;
            } else {
                $items = [$decoded];
            }

            if (empty($items)) {
                return AIResponse::error('Resayil extractPdfFiles: no bookings found');
            }

            return AIResponse::success($items, 'Data extracted successfully (resayil)', [
                'provider' => 'resayil', 'model' => $this->model,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Resayil extractPdfFiles error: ' . $e->getMessage());
            return AIResponse::error('Resayil extractPdfFiles failed: ' . $e->getMessage());
        }
    }

    public function extractMultiplePdfFiles(array $fileIds): array
    {
        return AIResponse::error('Resayil extractMultiplePdfFiles not enabled (Plan B) — defer to fallback');
    }

    public function processBatchFiles(array $files): array
    {
        return AIResponse::error('Resayil processBatchFiles not enabled (Plan B) — defer to fallback');
    }

    public function uploadFileToOpenAI($file, string $purpose = 'user_data')
    {
        // No Files API on Resayil; internal handle only (not used by Resayil paths).
        return is_string($file) ? $file : '';
    }
}
