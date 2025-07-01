<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\EmailVerification;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

use Laravolt\Avatar\Avatar;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class AuthController extends Controller
{
    // Register
    public function register(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
                function ($attribute, $value, $fail) {
                    // Validasi domain email - hanya terima @bitunix.com atau @bitunix.io
                    $allowedDomains = ['bitunix.com', 'bitunix.io'];
                    $emailDomain = substr(strrchr($value, "@"), 1);

                    if (!in_array($emailDomain, $allowedDomains)) {
                        $fail('Email harus menggunakan domain @bitunix.com atau @bitunix.io');
                    }
                }
            ],
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Ambil bagian nama sebelum "@" dari email
        $username = explode('@', $request->email)[0];

        // Ganti underscore dan titik dengan spasi
        $username = str_replace(['_', '.'], ' ', $username);

        // Capitalize the first letter of each word
        $name = ucwords($username);

        // Simpan avatar
        $avatarPath = $this->saveAvatar($name); // Panggil fungsi untuk membuat dan menyimpan avatar ke Cloudinary

        $user = User::create([
            'name' => $name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'avatar' => $avatarPath,  // Simpan URL avatar dari Cloudinary
        ]);

        // Generate & send 6-digit verification code
        $verificationCode = $this->generateVerificationCode();
        
        // Save hashed verification code
        EmailVerification::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code' => Hash::make($verificationCode), // Hash the code for security
            'context' => 'register', // This is from register flow
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        // Send verification email
        $this->sendVerificationEmail($user->email, $verificationCode, $user->name);

        return response()->json([
            'status' => 201,
            'data' => [
                'user' => $user,
                'email_verification_required' => true
            ],
            'message' => 'User registered successfully! Please check your email for verification code.'
        ], 201);
    }

    // Verify Email dengan 6 digit code (hashed)
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'status' => 400,
                'message' => 'Email already verified.'
            ], 400);
        }

        // Find the latest verification code for this email
        $verification = EmailVerification::where('email', $request->email)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$verification) {
            return response()->json([
                'status' => 400,
                'message' => 'Verification code not found or expired. Please request a new code.'
            ], 400);
        }

        // Verify the hashed code
        if (!Hash::check($request->code, $verification->code)) {
            return response()->json([
                'status' => 400,
                'message' => 'Invalid verification code.'
            ], 400);
        }

        // Mark verification as used
        $verification->is_used = true;
        $verification->save();

        // Mark email as verified
        $user->email_verified_at = Carbon::now();
        $user->save();

        // Check if this verification was from login flow
        if ($verification->context === 'login') {
            // Auto-login: Create token and return login response
            $token = $user->createToken('API Token')->plainTextToken;

            // Delete all verification codes for this user
            EmailVerification::where('user_id', $user->id)->delete();

            return response()->json([
                'status' => 200,
                'data' => [
                    'user' => $user->fresh(),
                    'token' => $token,
                    'email_verified' => true,
                    'auto_logged_in' => true
                ],
                'message' => 'Email verified successfully and logged in automatically!'
            ], 200);
        }

        // If from register flow, just return verification success
        // Delete all verification codes for this user
        EmailVerification::where('user_id', $user->id)->delete();

        return response()->json([
            'status' => 200,
            'data' => [
                'user' => $user->fresh(),
                'email_verified' => true
            ],
            'message' => 'Email verified successfully! You can now login.'
        ], 200);
    }
    public function resendEmailVerification(Request $request)
    {
        $user = Auth::user();

        if ($user->email_verified_at) {
            return response()->json([
                'status' => 400,
                'message' => 'Email already verified'
            ], 400);
        }

        // Here you would send verification email
        // For now, just return success message

        return response()->json([
            'status' => 200,
            'message' => 'Verification email sent successfully!'
        ]);
    }

    // ✅ Resend verification code
    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'status' => 400,
                'message' => 'Email already verified.'
            ], 400);
        }

        // Delete old verification codes
        EmailVerification::where('email', $request->email)->delete();

        // Generate new verification code
        $verificationCode = $this->generateVerificationCode();

        // Save hashed verification code
        EmailVerification::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code' => Hash::make($verificationCode), // Hash the code for security
            'context' => 'register', // Default context for resend
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        // ✅ Kirim email verification
        $this->sendVerificationEmail($user->email, $verificationCode, $user->name);

        return response()->json([
            'status' => 200,
            'message' => 'Verification code sent successfully!'
        ], 200);
    }

    // Login - Block unverified users & auto-send verification code
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();

            // Check if email verification has expired (24 hours after login)
            $this->checkEmailVerificationExpiry($user);

            // Check if email is verified
            if (is_null($user->email_verified_at)) {
                // Email not verified - generate & send new verification code
                // Delete old verification codes
                EmailVerification::where('email', $user->email)->delete();

                // Generate new verification code
                $verificationCode = $this->generateVerificationCode();

                // Save hashed verification code
                EmailVerification::create([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'code' => Hash::make($verificationCode),
                    'context' => 'login', // This is from login flow
                    'expires_at' => Carbon::now()->addMinutes(15),
                ]);

                // Send verification email
                $this->sendVerificationEmail($user->email, $verificationCode, $user->name);

                return response()->json([
                    'status' => 401,
                    'data' => [
                        'email_verification_required' => true,
                        'email' => $user->email
                    ],
                    'message' => 'Email verification required. We have sent a new verification code to your email.'
                ], 401);
            }

            // Email verified - proceed with login
            $token = $user->createToken('API Token')->plainTextToken;

            return response()->json([
                'status' => 200,
                'data' => [
                    'user' => $user->fresh(),
                    'token' => $token,
                    'email_verified' => true,
                ],
                'message' => 'Login successful'
            ], 200);
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    private function checkEmailVerificationExpiry(User $user)
    {
        if ($user->email_verified_at) {
            // Set verification expiry time (23 hours 55 minutes from verification)
            $expiryTime = Carbon::parse($user->email_verified_at)->addHours(23)->addMinutes(55);

            // If verification has expired, reset it
            if (Carbon::now()->greaterThan($expiryTime)) {
                $user->email_verified_at = null;
                $user->save();

                \Log::info("Email verification expired for user {$user->id}. Reset to unverified.");
            }
        }
    }

    public function updateName(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $user->name = $request->name;
        $user->save();

        return response()->json([
            'status_code' => 200,
            'data' => $user,
            'message' => 'Name updated successfully!'
        ]);
    }

    public function saveAvatar($name)
    {
        // Ambil inisial dari nama pengguna
        $initials = strtoupper(Str::substr($name, 0, 1));

        // Buat avatar menggunakan Laravolt Avatar
        $avatar = new Avatar();
        $imageSvg = $avatar->create($initials)
            ->setDimension(100, 100)
            ->setFontSize(40)
            ->toSvg();

        // Base64 encode the SVG string
        $imageBase64 = base64_encode($imageSvg);

        // Konfigurasi Cloudinary
        $cloudinary = new Cloudinary(Configuration::instance());

        // Upload base64-encoded SVG to Cloudinary
        $uploadResult = $cloudinary->uploadApi()->upload('data:image/svg+xml;base64,' . $imageBase64, [
            'folder' => 'avatars',
            'public_id' => Str::random(10),
            'resource_type' => 'image',
            'format' => 'svg'
        ]);

        // Ambil URL gambar dari Cloudinary
        $avatarUrl = $uploadResult['secure_url'];

        return $avatarUrl;
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        // 1. Reset email verification status when user logs out
        $user->performLogout();

        // 2. Delete all tokens for this user
        $user->tokens->each(function($token) {
            $token->delete();
        });

        \Log::info("User {$user->id} logged out. Email verification reset.");

        return response()->json([
            'status' => 200,
            'message' => 'User logged out successfully!'
        ], 200);
        // dd($user);
    }

    // ✅ Helper Functions

    /**
     * Generate 6 digit verification code
     */
    private function generateVerificationCode()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify email code and auto-login (for smooth login flow)
     */
    public function verifyAndLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        // First, verify the credentials
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 401,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        $user = Auth::user();

        // If already verified, just return login success
        if ($user->email_verified_at) {
            $token = $user->createToken('API Token')->plainTextToken;
            
            return response()->json([
                'status' => 200,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'email_verified' => true,
                ],
                'message' => 'Login successful'
            ], 200);
        }

        // Find the latest verification code for this email
        $verification = EmailVerification::where('email', $request->email)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$verification) {
            return response()->json([
                'status' => 400,
                'message' => 'Verification code not found or expired. Please request a new code.'
            ], 400);
        }

        // Verify the hashed code
        if (!Hash::check($request->code, $verification->code)) {
            return response()->json([
                'status' => 400,
                'message' => 'Invalid verification code.'
            ], 400);
        }

        // Mark verification as used
        $verification->is_used = true;
        $verification->save();

        // Mark email as verified
        $user->email_verified_at = Carbon::now();
        $user->save();

        // Delete all verification codes for this user
        EmailVerification::where('user_id', $user->id)->delete();

        // Create token for successful login
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'status' => 200,
            'data' => [
                'user' => $user->fresh(),
                'token' => $token,
                'email_verified' => true,
                'verified_and_logged_in' => true
            ],
            'message' => 'Email verified successfully and logged in!'
        ], 200);
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $code, $name)
    {
        $subject = 'Email Verification Code';
        $message = "
            <h2>Hello {$name}!</h2>
            <p>Thank you for registering. Please verify your email address using the code below:</p>
            <h1 style='background: #1a1919; padding: 20px; text-align: center; font-size: 32px; letter-spacing: 5px; color: #b9f641;'>{$code}</h1>
            <p>This code will expire in 15 minutes.</p>
            <p>If you didn't create this account, please ignore this email.</p>
            <br>
            <p>Best regards,<br>Bitunix Shortener and UTM Builder App API</p>
        ";

        // ✅ Kirim email menggunakan Laravel Mail
        try {
            Mail::send([], [], function ($mail) use ($email, $subject, $message) {
                $mail->to($email)
                     ->subject($subject)
                     ->html($message);
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email: ' . $e->getMessage());
        }
    }
}
