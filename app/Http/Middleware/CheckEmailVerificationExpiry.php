<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CheckEmailVerificationExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            if ($user->email_verified_at) {
                // Set verification expiry time (23 hours 55 minutes from verification)
                $expiryTime = Carbon::parse($user->email_verified_at)->addHours(23)->addMinutes(55);

                // If verification has expired, reset it
                if (Carbon::now()->greaterThan($expiryTime)) {
                    $user->email_verified_at = null;
                    $user->save();

                    \Log::info("Email verification expired for user {$user->id}. Reset to unverified via middleware.");
                    
                    // Optionally, you can return an error response here
                    // or just let the request continue with the reset verification
                }
            }
        }

        return $next($request);
    }
}