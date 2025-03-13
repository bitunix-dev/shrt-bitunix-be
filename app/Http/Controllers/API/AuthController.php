<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;

use Laravolt\Avatar\Avatar;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class AuthController extends Controller
{
    // Register
// Register
public function register(Request $request)
{
    $request->validate([
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
    ]);

    // Ambil bagian nama sebelum "@" dari email
    $name = explode('@', $request->email)[0];
    $avatarPath = $this->saveAvatar($name); // Panggil fungsi untuk membuat dan menyimpan avatar ke Cloudinary

    $user = User::create([
        'name' => $name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'avatar' => $avatarPath,  // Simpan URL avatar dari Cloudinary
    ]);

    return response()->json([
        'status' => 201,
        'data' => $user,
        'message' => 'User registered successfully!'
    ], 201);
}

// Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            $token = $user->createToken('API Token')->plainTextToken;

            return response()->json([
                'status' => 200,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                'message' => 'Login successful'
            ], 200);
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
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
        $imageSvg = $avatar->create($initials) // Membuat avatar dengan inisial
            ->setDimension(100, 100)  // Ukuran avatar
            ->setFontSize(40)         // Ukuran font
            ->toSvg();               // Menghasilkan avatar dalam format SVG

        // Base64 encode the SVG string
        $imageBase64 = base64_encode($imageSvg);

        // Konfigurasi Cloudinary
        $cloudinary = new Cloudinary(Configuration::instance());

        // Upload base64-encoded SVG to Cloudinary
        $uploadResult = $cloudinary->uploadApi()->upload('data:image/svg+xml;base64,' . $imageBase64, [
            'folder' => 'avatars', // Tentukan folder di Cloudinary
            'public_id' => Str::random(10), // Public ID yang unik
            'resource_type' => 'image',
            'format' => 'svg'  // Format yang sesuai dengan Cloudinary
        ]);

        // Ambil URL gambar dari Cloudinary
        $avatarUrl = $uploadResult['secure_url'];

        return $avatarUrl; // Kembalikan URL avatar yang telah disimpan di Cloudinary
    }

}