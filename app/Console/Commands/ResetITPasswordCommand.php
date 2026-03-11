<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ResetITPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset-it-password
                            {--email= : The email address of the IT user (default: it@alphia.net)}
                            {--password= : The new password (default: City@998000)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset password for IT admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?? 'it@alphia.net';
        $newPassword = $this->option('password') ?? 'City@998000';

        $this->info("Searching for user with email: {$email}");

        $user = User::where('email', 'LIKE', "%{$email}%")->first();

        if (!$user) {
            $this->error("User not found: {$email}");
            return Command::FAILURE;
        }

        $this->info("Found user: {$user->name} ({$user->email})");
        $this->info("Current role_id: {$user->role_id}");

        // Update password
        $user->password = Hash::make($newPassword);
        $user->save();

        $this->info("Password updated successfully for: {$user->email}");
        $this->info("New password: {$newPassword}");

        return Command::SUCCESS;
    }
}
