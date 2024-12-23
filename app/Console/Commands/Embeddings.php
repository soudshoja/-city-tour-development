<?php

namespace App\Console\Commands;

use App\Http\Controllers\OpenAiController;
use App\Models\KnowledgeBase;
use Illuminate\Console\Command;

class Embeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:embeddings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $knowledgeBaseEntries = KnowledgeBase::all();
        foreach ($knowledgeBaseEntries as $entry) {
            $openAIController = new OpenAiController();
            $embedding = $openAIController->embedding($entry->content);

            $entry->embedding = $embedding['data'][0]['embedding'];
            $entry->save();
        }
    }
}
