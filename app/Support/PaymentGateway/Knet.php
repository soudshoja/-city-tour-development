<?php 

namespace App\Support\PaymentGateway;

use App\Models\Charge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class Knet
{
    protected $url;
    protected $tranportalId;
    protected $tranportalPassword;
    protected $terminalResourceKey;

    public function __construct($companyId)
    {
        if(!$companyId){
            Log::error('Knet: Company ID is required to initialize Knet gateway.');
            throw new InvalidArgumentException('Company ID is required to initialize Knet gateway.');
        }

        $chargeConfig = Charge::where('company_id', $companyId)
                        ->where('is_active', true)
                        ->where('name', 'like', '%knet%')
                        ->first();

        if (!$chargeConfig) {
            Log::error('Knet: Active Knet charge configuration not found for company ID: ' . $companyId);
            throw new InvalidArgumentException('Active Knet charge configuration not found for the specified company.');
        } 

        $this->tranportalId = $chargeConfig->tran_portal_id;
        $this->tranportalPassword = $chargeConfig->tran_portal_password;
        $this->terminalResourceKey = $chargeConfig->terminal_resource_key;

        if(!$this->tranportalId || !$this->tranportalPassword || !$this->terminalResourceKey){
            Log::error('Knet: Incomplete Knet credentials for company ID: ' . $companyId);
            throw new InvalidArgumentException('Incomplete Knet credentials for the specified company.');
        }

        $this->url = config('services.knet.url');
    }

    public function createCharge(Request $request)
    {
        $request->validate([
            'finalAmount' => 'required|numeric|min:0.001',
            'payment_id' => 'required|integer|exists:payments,id',
            'voucher_number' => 'nullable|string',
            'invoice_number' => 'nullable|string',
            'invoice_partial_id' => 'nullable',
            'company_id' => 'required|integer',
        ]);

        try {
            $amount = number_format((float)$request->input('finalAmount'), 3, '.', '');
            $trackId = 'TRK' . time() . rand(1000, 9999);

            $responseUrl = route('payment.knet.response');
            $errorUrl = route('payment.knet.error');

            $udf1 = $request->input('payment_id');
            $udf2 = $request->input('voucher_number', '');
            $udf3 = $request->input('company_id');
            $udf4 = $request->input('invoice_number', '');
            $udf5 = $request->input('invoice_partial_id', '');

            $params = [
                'id' => $this->tranportalId,
                'password' => $this->tranportalPassword,
                'action' => '1',
                'langid' => 'USA',
                'currencycode' => '414',
                'amt' => $amount,
                'responseURL' => $responseUrl,
                'errorURL' => $errorUrl,
                'trackid' => $trackId,
                'udf1' => $udf1,
                'udf2' => $udf2,
                'udf3' => $udf3,
                'udf4' => $udf4,
                'udf5' => $udf5,
            ];

            $paramString = http_build_query($params);

            $encryptedData = $this->encryptAES($paramString, $this->terminalResourceKey);

            $redirectUrl = $this->url . '?param=paymentInit&trandata=' . $encryptedData 
                . '&tranportalId=' . $this->tranportalId 
                . '&responseURL=' . urlencode($responseUrl) 
                . '&errorURL=' . urlencode($errorUrl);

            Log::info('KNET Create Charge', [
                'track_id' => $trackId,
                'amount' => $amount,
                'payment_id' => $udf1,
                'voucher_number' => $udf2,
                'redirect_url' => $redirectUrl,
            ]);

            return [
                'status' => 'success',
                'redirect_url' => $redirectUrl,
                'track_id' => $trackId,
            ];

        } catch (\Exception $e) {
            Log::error('KNET Create Charge Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function decryptResponse($encryptedData)
    {
        try {
            if (empty($encryptedData)) {
                Log::error('KNET Decrypt Response: Empty encrypted data received');
                return null;
            }

            $decryptedString = $this->decryptAES($encryptedData, $this->terminalResourceKey);

            if (!$decryptedString) {
                Log::error('KNET Decrypt Response: Decryption failed');
                return null;
            }

            parse_str($decryptedString, $responseData);

            Log::info('KNET Response Decrypted', $responseData);

            return $responseData;

        } catch (\Exception $e) {
            Log::error('KNET Decrypt Response Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    private function encryptAES($str, $key)
    {
        $str = $this->pkcs5_pad($str);
        $encrypted = openssl_encrypt($str, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $key);
        $encrypted = base64_decode($encrypted);
        $encrypted = unpack('C*', $encrypted);
        $encrypted = $this->byteArray2Hex($encrypted);
        return $encrypted;
    }

    private function decryptAES($code, $key)
    {
        $code = $this->hex2ByteArray(trim($code));
        $code = $this->byteArray2String($code);
        $iv = $key;
        $code = base64_encode($code);
        $decrypted = openssl_decrypt($code, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $iv);
        return $this->pkcs5_unpad($decrypted);
    }

    private function pkcs5_pad($text, $blocksize = 16)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    private function pkcs5_unpad($text)
    {
        $pad = ord($text[strlen($text) - 1]);
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
            return false;
        }
        return substr($text, 0, -1 * $pad);
    }

    private function hex2ByteArray($hexString)
    {
        $string = hex2bin($hexString);
        return unpack('C*', $string);
    }

    private function byteArray2String($byteArray)
    {
        $chars = array_map("chr", $byteArray);
        return join($chars);
    }

    private function byteArray2Hex($byteArray)
    {
        $chars = array_map("chr", $byteArray);
        $bin = join($chars);
        return bin2hex($bin);
    }

    public function getCredentials()
    {
        return [
            'tranportal_id' => $this->tranportalId,
            'tranportal_password' => $this->tranportalPassword,
            'terminal_resource_key' => $this->terminalResourceKey,
            'url' => $this->url,
        ];
    }
}