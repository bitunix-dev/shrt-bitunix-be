<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClickLog;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Cache;
use App\Models\Url;
use App\Models\Tag;
use App\Models\Source;
use App\Models\Medium;
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
    
        // Normalisasi Source dan Medium (disimpan di tabel masing-masing jika belum ada)
        $sourceName = $this->normalize($request->source);
        $mediumName = $this->normalize($request->medium);
    
        if ($sourceName) {
            Source::firstOrCreate(['name' => $sourceName]);
        }
    
        if ($mediumName) {
            Medium::firstOrCreate(['name' => $mediumName]);
        }
    
        // Simpan URL ke database (source & medium tetap sebagai string)
        $url = Url::create([
            'destination_url' => $request->destination_url,
            'short_link' => "short.bitunixads.com/" . $shortLink,
            'source' => $sourceName,
            'medium' => $mediumName,
            'campaign' => $request->campaign,
            'term' => $request->term,
            'content' => $request->content,
            'referral' => $request->referral,
        ]);
        $url->save();
    
        // Attach tags ke URL jika ada
        if ($request->has('tags') && is_array($request->tags)) {
            $tagIds = [];
            foreach ($request->tags as $tagName) {
                $normalizedTag = $this->normalize($tagName);
                $tag = Tag::firstOrCreate(['name' => $normalizedTag]);
                $tagIds[] = $tag->id;
            }
            $url->tags()->sync($tagIds);
        }
    
        $url->load('tags');
        return response()->json(['data' => $url, 'message' => 'URL created successfully'], 201);
    }
    
    /**
     * Normalisasi string: ubah huruf kecil dan ganti spasi dengan dash (-)
     */
    private function normalize($name)
    {
        return $name ? strtolower(str_replace(' ', '-', trim($name))) : null;
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
    public function redirect($shortLink, Request $request)
    {
        $url = Url::where('short_link', "short.bitunixads.com/" . $shortLink)->firstOrFail();

        // Increment click count, hindari duplikasi klik berdasarkan IP dalam 1 jam
        $this->incrementClicks($url);

        $ipAddress = $request->ip();
        $cookieName = 'visited_' . $url->id;

        // Cek apakah pengguna sudah klik dalam 24 jam (via database)
        $alreadyClicked = ClickLog::where('url_id', $url->id)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subHours(24)) // Bisa diubah ke berapa jam
            ->exists();

        // Cek apakah cookie sudah ada
        if (!$alreadyClicked && !$request->cookie($cookieName)) {
            // Tambah log klik
            ClickLog::create([
                'url_id' => $url->id,
                'ip_address' => $ipAddress
            ]);

            // Tambah count klik
            $url->increment('clicks');

            // Set cookie agar tidak bisa dihitung lagi dalam 24 jam
            Cookie::queue($cookieName, true, 1440); // 1440 = 24 jam
        }

        // Redirect ke URL tujuan dengan UTM tracking
        $utmParams = collect([
            'utm_source' => $url->source,
            'utm_medium' => $url->medium,
            'utm_campaign' => $url->campaign,
            'utm_term' => $url->term,
            'utm_content' => $url->content,
            'ref' => $url->referral,
        ])->filter()->toArray();

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
    private function incrementClicks(Url $url)
    {
        $ip = request()->ip();
        $cacheKey = "url_clicks:{$url->id}:{$ip}";

        if (!Cache::has($cacheKey)) {
            $url->increment('clicks');
            Cache::put($cacheKey, true, now()->addHour()); // Simpan cache 1 jam
        }
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