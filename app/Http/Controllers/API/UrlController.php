<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Url;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class UrlController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $urls = Url::with('tags')->get();
        return response()->json(['data' => $urls], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'destination_url' => 'required|url',
            'tags' => 'nullable|array',
            'source' => 'nullable|string',
            'medium' => 'nullable|string',
            'campaign' => 'nullable|string',
            'term' => 'nullable|string',
            'content' => 'nullable|string',
            'referral' => 'nullable|string',
        ]);
    
        // Generate short link unik
        $shortLink = $this->generateUniqueShortLink();
    
        // Simpan URL ke database
        $url = Url::create([
            'destination_url' => $request->destination_url,
            'short_link' => "dub.sh/".$shortLink,
            'source' => $request->source,
            'medium' => $request->medium,
            'campaign' => $request->campaign,
            'term' => $request->term,
            'content' => $request->content,
            'referral' => $request->referral,
        ]);
    
        // Simpan QR Code ke dalam database
        $url->qr_code = $this->generateQRCode($url);
        $url->save();
    
        // Attach tags jika ada
        if ($request->has('tags') && is_array($request->tags)) {
            $tagIds = [];
            foreach ($request->tags as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }
            $url->tags()->sync($tagIds);
        }
    
        $url->load('tags');
        return response()->json(['data' => $url, 'message' => 'URL created successfully'], 201);
    }
    
    


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $url = Url::with('tags')->findOrFail($id);
        return response()->json(['data' => $url], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $url = Url::findOrFail($id);

        $request->validate([
            'destination_url' => 'nullable|url',
            'tags' => 'nullable|array',
            'source' => 'nullable|string',
            'medium' => 'nullable|string',
            'campaign' => 'nullable|string',
            'term' => 'nullable|string',
            'content' => 'nullable|string',
            'referral' => 'nullable|string',
            'short_link' => 'nullable|string',
        ]);

        // Update URL data
        $url->update($request->only([
            'destination_url',
            'source',
            'medium',
            'campaign',
            'term',
            'content',
            'referral',
            'short_link',
        ]));

        // If destination URL changes, regenerate QR code
        if ($request->has('destination_url')) {
            $qrCodePath = $this->generateQRCode($url);
            $url->qr_code = $qrCodePath;
            $url->save();
        }

        // Update tags if any
        if ($request->has('tags') && is_array($request->tags)) {
            $tagIds = [];
            foreach ($request->tags as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }
            $url->tags()->sync($tagIds);
        }

        $url->load('tags');
        return response()->json(['data' => $url, 'message' => 'URL updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $url = Url::findOrFail($id);
        
        // Delete QR code file if exists
        if ($url->qr_code && Storage::exists('public/' . $url->qr_code)) {
            Storage::delete('public/' . $url->qr_code);
        }
        
        $url->delete();
        return response()->json(['message' => 'URL deleted successfully'], 200);
    }

    /**
     * Redirect to the original URL with UTM parameters.
     */
    public function redirect($shortLink)
    {
        $url = Url::where('short_link', $shortLink)->firstOrFail();
    
        // Increment click count
        $url->increment('clicks');
    
        // Build UTM Parameters
        $utmParams = collect([
            'utm_source' => $url->source,
            'utm_medium' => $url->medium,
            'utm_campaign' => $url->campaign,
            'utm_term' => $url->term,
            'utm_content' => $url->content,
            'ref' => $url->referral,
        ])->filter()->toArray(); // Menghapus parameter yang kosong
    
        // Append parameters ke URL
        $destinationUrl = $url->destination_url;
        if (!empty($utmParams)) {
            $separator = (str_contains($destinationUrl, '?') ? '&' : '?');
            $destinationUrl .= $separator . http_build_query($utmParams);
        }
    
        return redirect($destinationUrl);
    }
    

    /**
     * Generate a unique short link.
     */
    private function generateUniqueShortLink($length = 6)
    {
        do {
            $shortLink = Str::random($length);
        } while (Url::where('short_link', $shortLink)->exists());
        
        return $shortLink;
    }

    /**
     * Generate QR code for the URL.
     */
    private function generateQRCode(Url $url)
    {
        $redirectUrl = url("/r/{$url->short_link}");
    
        // Generate QR Code sebagai Base64 (PNG tanpa Imagick)
        return base64_encode(QrCode::format('png')->size(300)->errorCorrection('H')->generate($redirectUrl));
    }
    public function getQrCode($id)
    {
        $url = Url::findOrFail($id);

        if (!$url->qr_code) {
            return response()->json(['message' => 'QR Code not found'], 404);
        }

        // Decode Base64 ke Binary PNG
        $qrCodeBinary = base64_decode($url->qr_code);

        // Generate nama file berdasarkan short link atau ID
        $fileName = 'qrcode_' . $url->short_link . '.png';

        // Buat response gambar PNG dengan nama file unik
        return response($qrCodeBinary)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');
    }

}    