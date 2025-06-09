<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClickLog;
use App\Models\Url;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ClickAnalyticsController extends Controller
{
    /**
     * Add incremental ID to collection
     */
    private function addIncrementalId(Collection $data): Collection
    {
        return $data->map(function ($item, $index) {
            return array_merge(['id' => $index + 1], $item);
        });
    }

    /**
     * Get user's URL IDs for filtering analytics
     */
    private function getUserUrlIds(): array
    {
        $user = Auth::user();

        // Admin (user_id = 0) bisa lihat semua URL
        if ($user->id === 0) {
            return Url::pluck('id')->toArray();
        }

        // User biasa hanya bisa lihat URL mereka sendiri
        return Url::where('user_id', $user->id)->pluck('id')->toArray();
    }

    /**
     * Get click analytics for user's URLs only.
     */
    public function getAllClicks(Request $request): JsonResponse
    {
        // Ambil URL IDs yang boleh diakses user
        $userUrlIds = $this->getUserUrlIds();

        if (empty($userUrlIds)) {
            return response()->json([
                'status' => 200,
                'data' => [
                    'clicks' => [],
                    'total_clicks' => 0,
                ],
            ]);
        }

        // Ambil rentang waktu (default: 24 jam terakhir)
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        // Ambil jumlah klik per jam untuk URL yang dimiliki user
        $clicksData = ClickLog::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour"),
                DB::raw("COUNT(*) as clicks")
            )
            ->whereIn('url_id', $userUrlIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->get();

        return response()->json([
            'status' => 200,
            'data' => [
                'clicks' => $clicksData,
                'total_clicks' => ClickLog::whereIn('url_id', $userUrlIds)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
            ],
        ]);
    }

    public function getUrls(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->input('p', 10);

        // Filter URLs berdasarkan user
        $query = Url::orderBy('clicks', 'DESC')->with('tags');

        if ($user->id !== 0) {
            $query->where('user_id', $user->id);
        }

        $urls = $query->paginate($perPage);

        return response()->json([
            'status' => 200,
            'data' => $urls
        ]);
    }

    /**
     * Method yang diperbaiki dengan user filtering
     */
    private function getClicksByField(Request $request, string $field): JsonResponse
    {
        if (empty($field)) {
            return response()->json([
                'status' => 400,
                'message' => 'Field parameter is required'
            ], 400);
        }

        // Ambil URL IDs yang boleh diakses user
        $userUrlIds = $this->getUserUrlIds();

        if (empty($userUrlIds)) {
            return response()->json([
                'status' => 200,
                'data' => [
                    'data' => [],
                    'current_page' => 1,
                    'total' => 0,
                ]
            ]);
        }

        $startDate = $request->query('start_date', Carbon::now()->subDays(30));
        $endDate = $request->query('end_date', Carbon::now());

        // Query dengan filter user URLs
        $query = ClickLog::select(
                $field,
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('MAX(country_flag) as country_flag')
            )
            ->whereIn('url_id', $userUrlIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->groupBy($field)
            ->orderByDesc('total_clicks');

        // Debug: Log query untuk debugging
        \Log::info("Analytics Query for {$field}: " . $query->toSql());
        \Log::info("Analytics Bindings: " . json_encode($query->getBindings()));

        // Ambil data dengan pagination
        $paginatedData = $query->paginate(10);

        // Transform data untuk menambahkan id incremental
        $transformedData = $paginatedData->getCollection()->map(function ($item, $index) use ($field) {
            return [
                'id' => $index + 1,
                $field => $item->$field,
                'total_clicks' => $item->total_clicks,
                'country_flag' => $item->country_flag ?? null
            ];
        });

        // Update collection dengan data yang sudah di-transform
        $paginatedData->setCollection($transformedData);

        return response()->json([
            'status' => 200,
            'data' => $paginatedData
        ]);
    }

    /**
     * Get click analytics for a specific short link with user filtering.
     */
    public function getClicksByUrl(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        // Cek apakah URL ada dan bisa diakses user
        $query = Url::with('tags')->where('id', $id);

        if ($user->id !== 0) {
            $query->where('user_id', $user->id);
        }

        $url = $query->first();

        if (!$url) {
            return response()->json([
                'status' => 404,
                'message' => 'URL not found'
            ], 404);
        }

        // Ambil rentang waktu (default: 24 jam terakhir)
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        // Ambil jumlah klik per jam untuk URL tertentu
        $clicksData = ClickLog::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour"),
            DB::raw("COUNT(*) as clicks")
        )
        ->where('url_id', $id)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('hour')
        ->orderBy('hour', 'ASC')
        ->paginate(10)
        ->map(function ($item, $index) {
            return [
                'id' => $index + 1,
                'hour' => $item->hour,
                'clicks' => $item->clicks,
            ];
        });

        // ✅ Ambil country_flag dari data klik terbaru untuk URL ini
        $latestClick = ClickLog::where('url_id', $id)
            ->whereNotNull('country_flag')
            ->latest('created_at')
            ->first();

        $country_flag = $latestClick ? $latestClick->country_flag : null;

        return response()->json([
            'status' => 200,
            'data' => [
                'url' => $url->short_link,
                'destination_url' => $url->destination_url,
                'clicks' => $clicksData,
                'total_clicks' => ClickLog::where('url_id', $id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'country_flag' => $country_flag
            ],
        ]);
    }

    /**
     * Get date range for analytics
     */
    private function getDateRange(Request $request, Url $url): array
    {
        $allData = $request->query('all', 'false') === 'true';

        if ($allData) {
            $firstClick = ClickLog::where('url_id', $url->id)
                ->orderBy('created_at', 'ASC')
                ->first();

            $lastClick = ClickLog::where('url_id', $url->id)
                ->orderBy('created_at', 'DESC')
                ->first();

            if ($firstClick && $lastClick) {
                return [
                    'start_date' => $firstClick->created_at,
                    'end_date' => $lastClick->created_at,
                    'is_all_data' => true
                ];
            }
        }

        return [
            'start_date' => $request->query('start_date', Carbon::now()->subDays(7)),
            'end_date' => $request->query('end_date', Carbon::now()),
            'is_all_data' => $allData
        ];
    }

    /**
     * Get time format based on date range
     */
    private function getTimeFormat(Carbon $startDate, Carbon $endDate): array
    {
        $diffInDays = $startDate->diffInDays($endDate);

        if ($diffInDays > 365) {
            return ['format' => '%Y-%m', 'label' => 'quarterly'];
        }

        if ($diffInDays > 90) {
            return ['format' => '%Y-%m', 'label' => 'monthly'];
        }

        return ['format' => '%Y-%m-%d', 'label' => 'daily'];
    }

    /**
     * Get analytics data for specific field
     */
    private function getAnalyticsForField(int $urlId, string $field, int $limit = 10): Collection
    {
        return ClickLog::select($field, DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $urlId)
            ->whereNotNull($field)
            ->groupBy($field)
            ->orderByDesc('total_clicks')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) use ($field) {
                return [
                    'id' => $index + 1,
                    $field => $item->$field,
                    'total_clicks' => $item->total_clicks
                ];
            });
    }

    /**
     * Get country analytics with flags
     */
    private function getCountryAnalytics(int $urlId): Collection
    {
        return ClickLog::select('country', DB::raw('COUNT(*) as total_clicks'), 'country_flag')
            ->where('url_id', $urlId)
            ->whereNotNull('country')
            ->groupBy('country', 'country_flag')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'country' => $item->country,
                    'total_clicks' => $item->total_clicks,
                    'country_flag' => $item->country_flag
                ];
            });
    }

    /**
     * Get detailed click analytics for a specific short link by its URL with user filtering.
     */
    public function getClicksByShortLink(Request $request, string $shortLink): JsonResponse
    {
        $user = Auth::user();

        // Find the URL by short_link dengan user filtering
        $query = Url::where('short_link', $shortLink);

        if ($user->id !== 0) {
            $query->where('user_id', $user->id);
        }

        $url = $query->first();

        if (!$url) {
            return response()->json([
                'status' => 404,
                'message' => 'URL not found or access denied'
            ], 404);
        }

        // Get date range
        $dateRange = $this->getDateRange($request, $url);
        $startDate = Carbon::parse($dateRange['start_date']);
        $endDate = Carbon::parse($dateRange['end_date']);

        // Get time format
        $timeConfig = $this->getTimeFormat($startDate, $endDate);

        // Get clicks timeline
        $clicksData = ClickLog::select(
            DB::raw("DATE_FORMAT(created_at, '{$timeConfig['format']}') as time_period"),
            DB::raw("COUNT(*) as clicks")
        )
        ->where('url_id', $url->id)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('time_period')
        ->orderBy('time_period', 'ASC')
        ->get();

        // Get all analytics data
        $analytics = [
            'countries' => $this->getCountryAnalytics($url->id),
            'cities' => $this->getAnalyticsForField($url->id, 'city'),
            'regions' => $this->getAnalyticsForField($url->id, 'region'),
            'continents' => $this->getAnalyticsForField($url->id, 'continent'),
            'devices' => $this->getAnalyticsForField($url->id, 'device'),
            'browsers' => $this->getAnalyticsForField($url->id, 'browser'),
            'sources' => $this->getAnalyticsForField($url->id, 'source'),
            'mediums' => $this->getAnalyticsForField($url->id, 'medium'),
            'campaigns' => $this->getAnalyticsForField($url->id, 'campaign'),
            'terms' => $this->getAnalyticsForField($url->id, 'term'),
            'contents' => $this->getAnalyticsForField($url->id, 'content')
        ];

        return response()->json([
            'status' => 200,
            'data' => [
                'url' => $url,
                'clicks_timeline' => $clicksData,
                'time_format' => $timeConfig['label'],
                'date_range' => $dateRange,
                'total_clicks' => $url->clicks,
                'total_clicks_in_range' => ClickLog::where('url_id', $url->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'analytics' => $analytics
            ],
        ]);
    }

    // Methods untuk setiap field dengan user filtering
    public function getClicksByCountry(Request $request): JsonResponse { return $this->getClicksByField($request, 'country'); }
    public function getClicksByCity(Request $request): JsonResponse { return $this->getClicksByField($request, 'city'); }
    public function getClicksByRegion(Request $request): JsonResponse { return $this->getClicksByField($request, 'region'); }
    public function getClicksByContinent(Request $request): JsonResponse { return $this->getClicksByField($request, 'continent'); }
    public function getClicksBySource(Request $request): JsonResponse { return $this->getClicksByField($request, 'source'); }
    public function getClicksByMedium(Request $request): JsonResponse { return $this->getClicksByField($request, 'medium'); }
    public function getClicksByCampaign(Request $request): JsonResponse { return $this->getClicksByField($request, 'campaign'); }
    public function getClicksByTerm(Request $request): JsonResponse { return $this->getClicksByField($request, 'term'); }
    public function getClicksByContent(Request $request): JsonResponse { return $this->getClicksByField($request, 'content'); }
    public function getClicksByDevice(Request $request): JsonResponse { return $this->getClicksByField($request, 'device'); }
    public function getClicksByBrowser(Request $request): JsonResponse { return $this->getClicksByField($request, 'browser'); }
}
