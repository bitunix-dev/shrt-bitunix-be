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
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Http;

class UrlController extends Controller
{
    // Tambahkan property untuk mapping source
    private $sourceMapping = [
        'tg' => 'telegram',
        'ig' => 'instagram',
        'fb' => 'facebook',
        'x' => 'x-twitter',
        'wa' => 'whatsapp',
        'yt' => 'youtube',
        'tk' => 'tiktok',
        'li' => 'linkedin',
        'tw' => 'twitter', // untuk backward compatibility
    ];

    public function index(Request $request)
    {
        // Ambil parameter 'p' dari query string, jika tidak ada, default 10
        $perPage = $request->input('p', 10);

        // Validasi apakah 'p' adalah angka dan lebih besar dari 0
        if (!is_numeric($perPage) || $perPage <= 0) {
            return response()->json([
                'status' => 400,
                'message' => 'Parameter p harus berupa angka positif.'
            ], 400);
        }

        // Ambil data dengan pagination sesuai nilai perPage
        $urls = Url::with('tags')->paginate($perPage);

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

            // Map source menggunakan function baru
            $sourceName = $this->mapSource($request->source);
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

    // Fungsi baru untuk mapping source
    private function mapSource($source)
    {
        if (!$source) {
            return null;
        }

        // Convert ke lowercase untuk case-insensitive matching
        $sourceLower = strtolower(trim($source));

        // Cek apakah ada di mapping
        if (isset($this->sourceMapping[$sourceLower])) {
            return $this->sourceMapping[$sourceLower];
        }

        // Jika tidak ada di mapping, normalize seperti biasa
        return $this->normalize($source);
    }

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

        // Update dengan mapping source yang baru
        $updateData = $request->only([
            'destination_url',
            'medium',
            'campaign',
            'term',
            'content',
            'referral',
            'short_link',
        ]);

        // Map source jika ada
        if ($request->has('source')) {
            $updateData['source'] = $this->mapSource($request->source);
        }

        $url->update($updateData);

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
        // Hapus bagian domain (short.bitunixads.com/) dari shortLink
        $shortLink = str_replace('short.bitunixads.com/', '', $shortLink);
        \Log::info('Redirecting shortLink: ' . $shortLink);

        // Cari URL berdasarkan shortLink saja tanpa domain
        $url = Url::where('short_link', 'short.bitunixads.com/' . $shortLink)->firstOrFail();
        if (!$url) {
            return response()->json(['error' => 'Not Found'], 404);
        }
        $ipAddress = $request->ip();
        $cookieName = 'visited_' . $url->id;

        // Cek apakah pengguna sudah klik dalam 24 jam (via database)
        $alreadyClicked = ClickLog::where('url_id', $url->id)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subHours(24)) // Bisa diubah ke berapa jam
            ->exists();

        // Cek apakah cookie sudah ada
        if (!$alreadyClicked && !$request->cookie($cookieName)) {

            // ✅ 1. Ambil Data Geolokasi dari IPGeoLocations API
            $geoResponse = Http::get("https://api.ipgeolocation.io/ipgeo", [
                'apiKey' => env('IPGEOLOCATION_API_KEY'), // API Key dari .env
                'ip' => $ipAddress
            ]);

            $geoData = $geoResponse->json();

            // ✅ 2. Ambil Data Device & Browser
            $agent = new Agent();
            $device = $agent->device();
            $browser = $agent->browser();

            // ✅ 3. Simpan ke ClickLog
            ClickLog::firstOrCreate(
                [
                    'url_id' => $url->id,
                    'ip_address' => $ipAddress,
                ],
                [
                    'country' => $geoData['country_name'] ?? null,
                    'country_flag' => $geoData['country_flag'] ?? null,
                    'city' => $geoData['city'] ?? null,
                    'region' => $geoData['state_prov'] ?? null,
                    'continent' => $geoData['continent_name'] ?? null,
                    'device' => $device,
                    'browser' => $browser,
                    'source' => $url->source ?? null,
                    'medium' => $url->medium ?? null,
                    'campaign' => $url->campaign ?? null,
                    'term' => $url->term ?? null,
                    'content' => $url->content ?? null,
                ]
            );

            // ✅ 4. Set cookie agar tidak bisa dihitung lagi dalam 24 jam
            Cookie::queue($cookieName, true, 1440);
            $this->incrementClicks($url);
        }

        // ✅ 5. Parse destination URL dan hapus UTM parameter yang sudah ada
        $parsedUrl = parse_url($url->destination_url);
        $existingParams = [];

        // Ambil query parameter yang sudah ada (kecuali UTM dan ref)
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $existingParams);

            // Hapus semua UTM parameter dan ref dari URL asli
            $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'];
            foreach ($utmKeys as $key) {
                unset($existingParams[$key]);
            }
        }

        // ✅ 6. Siapkan UTM parameter dari database (mapping sudah diterapkan)
        $utmParams = collect([
            'utm_source' => $url->source,
            'utm_medium' => $url->medium,
            'utm_campaign' => $url->campaign,
            'utm_term' => $url->term,
            'utm_content' => $url->content,
            'ref' => $url->referral,
        ])->filter()->toArray();

        // ✅ 7. Gabungkan parameter (UTM dari database menang)
        $finalParams = array_merge($existingParams, $utmParams);

        // ✅ 8. Bangun URL final
        $destinationUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (isset($parsedUrl['path'])) {
            $destinationUrl .= $parsedUrl['path'];
        }
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
