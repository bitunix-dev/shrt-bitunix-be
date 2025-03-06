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
    public function index()
    {
        $urls = Url::with('tags')->get();
        return response()->json([
            'status' => 200,
            'data' => $urls
        ], 200);
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
    
        try {
            // Generate short link unik
            $shortLink = $request->short_link ?? $this->generateUniqueShortLink();
    
            // Normalisasi semua parameter yang bisa mengandung spasi
            $sourceName = $this->normalize($request->source);
            $mediumName = $this->normalize($request->medium);
            $campaignName = $this->normalize($request->campaign);
            $termName = $this->normalize($request->term);
            $contentName = $this->normalize($request->content);
            $referralName = $this->normalize($request->referral);
    
            // Simpan source & medium jika belum ada di tabel masing-masing
            if ($sourceName) {
                Source::firstOrCreate(['name' => $sourceName]);
            }
    
            if ($mediumName) {
                Medium::firstOrCreate(['name' => $mediumName]);
            }
    
            // Simpan URL ke database dengan nilai yang sudah dinormalisasi
            $url = Url::create([
                'destination_url' => $request->destination_url,
                'short_link' => "short.bitunixads.com/" . $shortLink,
                'source' => $sourceName,
                'medium' => $mediumName,
                'campaign' => $campaignName,
                'term' => $termName,
                'content' => $contentName,
                'referral' => $referralName,
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
    
            return response()->json([
                'status' => 201,
                'data' => $url,
                'message' => 'URL created successfully'
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to create URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Normalisasi string: ubah huruf kecil dan ganti spasi dengan dash (-)
     */
    private function normalize($name)
    {
        return $name ? strtolower(preg_replace('/\s+/', '-', trim($name))) : null;
    }
/**
 * Display the specified resource.
 */
    public function show($id)
    {
        $url = Url::with('tags')->find($id);

        if (!$url) {
            return response()->json([
                'status' => 404,
                'message' => 'URL not found'
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $url
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $url = Url::find($id);

        if (!$url) {
            return response()->json([
                'status' => 404,
                'message' => 'URL not found'
            ], 404);
        }

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

        return response()->json([
            'status' => 200,
            'data' => $url,
            'message' => 'URL updated successfully'
        ], 200);
    }
    public function destroy($id)
    {
        $url = Url::find($id);
    
        if (!$url) {
            return response()->json([
                'status' => 404,
                'message' => 'URL not found'
            ], 404);
        }
    
        $url->delete();
    
        return response()->json([
            'status' => 200,
            'message' => 'URL deleted successfully'
        ], 200);
    }

    /**
     * Redirect to the original URL with UTM parameters.
     */
    public function redirect($shortLink, Request $request)
    {
        $url = Url::where('short_link', "short.bitunixads.com/" . $shortLink)->firstOrFail();

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

            // Set cookie agar tidak bisa dihitung lagi dalam 24 jam
            Cookie::queue($cookieName, true, 1440); // 1440 = 24 jam
            $this->incrementClicks($url);
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

        $parsedUrl = parse_url($url->destination_url);
        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams); // Ambil parameter yang sudah ada di URL
        }

        // Gabungkan UTM parameters dengan parameter yang sudah ada, tanpa duplikasi
        $finalParams = array_merge($utmParams, $queryParams);

        // Buat ulang URL tanpa duplikasi parameter
        $destinationUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];
        if (!empty($finalParams)) {
            $destinationUrl .= '?' . http_build_query($finalParams);
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