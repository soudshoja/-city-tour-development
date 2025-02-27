<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

class ReadEmails extends Command
{
    protected $signature = 'emails:read';
    protected $description = 'Read emails from specific Gmail labels without modifying them';

    public function handle()
    {
        $client = Client::account('default'); 
        $client->connect();

        // Labels (Folders) you want to read emails from
        $labels = ['magic', 'tbo', 'webbeds'];

        foreach ($labels as $label) {
            $this->info("\n📂 Reading emails from: " . strtoupper($label));

            try {
                $folder = $client->getFolder($label);

                $messages = $folder->query()->all()->limit(5)->get(); // Fetch latest 5 emails
                
                if ($messages->count() == 0) {
                    $this->info("No emails found in $label.");
                    continue;
                }

                foreach ($messages as $message) {
                    echo "\n---------------------------------\n";
                    echo "📩 Subject: " . $message->getSubject() . "\n";
                    echo "📅 Date: " . $message->getDate() . "\n";
                    echo "✉️ From: " . $message->getFrom()[0]->mail . "\n";
                    echo "📄 Body: " . strip_tags($message->getTextBody()) . "\n";
                    echo "---------------------------------\n";
                }
            } catch (\Exception $e) {
                $this->error("⚠️ Error reading from $label: " . $e->getMessage());
            }
        }

        $this->info("\n✅ Email reading completed!");
    }
}
