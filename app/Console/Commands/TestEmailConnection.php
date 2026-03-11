<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class TestEmailConnection extends Command
{
    protected $signature = 'email:test';
    protected $description = 'Test IMAP/POP3 email connection';

    public function handle()
    {
        $cm = new ClientManager();

        $client = $cm->make([
            'host'          => 'imap.gmail.com',
            'port'          => 993,  // Use 995 for POP3
            'encryption'    => 'ssl', 
            'validate_cert' => true,
            'username'      => 'citytravelerstask@gmail.com',
            'password'      => 'aufcffrgziullkem',  // Use App Password
            'protocol'      => 'imap'  // Use 'pop3' if using POP3
        ]);

        try {
            $this->info("🔄 Attempting to connect...");
            $client->connect();
            $this->info("✅ Connected successfully!");
        } catch (AuthFailedException $e) {
            $this->error("❌ AUTH ERROR: " . $e->getMessage());
        } catch (ConnectionFailedException $e) {
            $this->error("❌ CONNECTION ERROR: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->error("❌ GENERAL ERROR: " . $e->getMessage());
        }
    }
}
