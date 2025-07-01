<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class ResetExpiredEmailVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:reset-expired-verifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset email verifications that have expired after 23 hours 55 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Calculate expiry time (23 hours 55 minutes ago)
        $expiryTime = Carbon::now()->subHours(23)->subMinutes(55);
        
        // Find users whose email verification has expired
        $expiredUsers = User::whereNotNull('email_verified_at')
            ->where('email_verified_at', '<=', $expiryTime)
            ->get();

        $resetCount = 0;

        foreach ($expiredUsers as $user) {
            // Reset email verification
            $user->email_verified_at = null;
            $user->save();
            
            // Log the reset
            \Log::info("Email verification expired and reset for user {$user->id} ({$user->email})");
            $resetCount++;
        }

        if ($resetCount > 0) {
            $this->info("Reset {$resetCount} expired email verifications.");
        } else {
            $this->info("No expired email verifications found.");
        }

        return 0;
    }
}