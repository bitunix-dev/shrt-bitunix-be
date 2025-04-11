<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClickLog;
use App\Models\Url;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClickAnalyticsController extends Controller
{
    private function addIncrementalId($data)
    {
        return $data->map(function ($item, $index) {
            return array_merge(['id' => $index + 1], $item);
        });
    }

    /**
     * Get click analytics for all URLs.
     */
    public function getAllClicks(Request $request)
    {
        // Ambil rentang waktu (default: 24 jam terakhir)
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        // Ambil jumlah klik per jam
        $clicksData = ClickLog::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour"),
                DB::raw("COUNT(*) as clicks")
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->get();

        return response()->json([
            'status' => 200,
            'data' => [
                'clicks' => $clicksData,
                'total_clicks' => ClickLog::whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
        ]);
    }
    public function getUrls(Request $request)
    {
        $perPage = $request->input('p', 10);
        $urls = Url::orderBy('clicks', 'DESC')->with('tags')->paginate($perPage);

        return response()->json([
            'status' => 200,
            'data' => $urls
        ]);
    }
    private function getClicksByField(Request $request, string $field)
    {
        if (empty($field)) {
            return response()->json([
                'status' => 400,
                'message' => 'Field parameter is required'
            ], 400);
        }

        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $data = ClickLog::select(
                $field,
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('(SELECT country_flag FROM click_logs WHERE click_logs.' . $field . ' = outer_table.' . $field . ' AND country_flag IS NOT NULL ORDER BY created_at DESC LIMIT 1) as country_flag')
            )
            ->from('click_logs as outer_table') // Alias untuk subquery
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull($field)
            ->groupBy($field)
            ->orderByDesc('total_clicks')
            ->paginate()
            ->map(function ($item, $index) use ($field) {
                return [
                    'id' => $index + 1,
                    $field => $item[$field],
                    'total_clicks' => $item['total_clicks'],
                    'country_flag' => $item['country_flag'] ?? null // Pastikan tidak NULL jika ada data
                ];
            });

        return response()->json(['status' => 200, 'data' => $data]);
    }


    /**
     * Get click analytics for a specific short link.
     */
    public function getClicksByUrl(Request $request, $id)
    {
        // Cek apakah URL ada
        $url = Url::with('tags')->findOrFail($id);

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
        ->paginate()
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
     * Get detailed click analytics for a specific short link by its URL.
     */
    public function getClicksByShortLink(Request $request, $shortLink)
    {
        // Find the URL by short_link
        $url = Url::where('short_link', $shortLink)->firstOrFail();

        // Ambil rentang waktu. Jika all=true, ambil semua data
        $allData = $request->query('all', 'false') === 'true';

        if ($allData) {
            // Ambil data paling awal dan paling akhir untuk URL ini
            $firstClick = ClickLog::where('url_id', $url->id)
                ->orderBy('created_at', 'ASC')
                ->first();

            $lastClick = ClickLog::where('url_id', $url->id)
                ->orderBy('created_at', 'DESC')
                ->first();

            if ($firstClick && $lastClick) {
                $startDate = $firstClick->created_at;
                $endDate = $lastClick->created_at;
            } else {
                // Jika tidak ada klik, gunakan default
                $startDate = Carbon::now()->subDays(7);
                $endDate = Carbon::now();
            }
        } else {
            // Gunakan parameter request atau default (7 hari terakhir)
            $startDate = $request->query('start_date', Carbon::now()->subDays(7));
            $endDate = $request->query('end_date', Carbon::now());
        }

        // Tentukan format waktu berdasarkan rentang
        $diffInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $dateFormat = '%Y-%m-%d';  // Default: harian

        // Lebih dari 90 hari, gunakan format bulanan
        if ($diffInDays > 90) {
            $dateFormat = '%Y-%m';
        }
        // Lebih dari 365 hari, gunakan format kuartalan
        if ($diffInDays > 365) {
            $dateFormat = '%Y-%m'; // Tetap bulanan, atau bisa disesuaikan ke kuartalan
        }

        // Get hourly clicks for this specific URL
        $clicksData = ClickLog::select(
            DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as time_period"),
            DB::raw("COUNT(*) as clicks")
        )
        ->where('url_id', $url->id)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('time_period')
        ->orderBy('time_period', 'ASC')
        ->get();

        // Get country statistics
        $countries = ClickLog::select('country', DB::raw('COUNT(*) as total_clicks'), 'country_flag')
            ->where('url_id', $url->id)
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

        // Get city statistics
        $cities = ClickLog::select('city', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'city' => $item->city,
                    'total_clicks' => $item->total_clicks
                ];
            });

        // Get region statistics
        $regions = ClickLog::select('region', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('region')
            ->groupBy('region')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'region' => $item->region,
                    'total_clicks' => $item->total_clicks
                ];
            });

        // Get continent statistics
        $continents = ClickLog::select('continent', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('continent')
            ->groupBy('continent')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'continent' => $item->continent,
                    'total_clicks' => $item->total_clicks
                ];
            });

        // Get device statistics
        $devices = ClickLog::select('device', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('device')
            ->groupBy('device')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'device' => $item->device,
                    'total_clicks' => $item->total_clicks
                ];
            });

        // Get browser statistics
        $browsers = ClickLog::select('browser', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'browser' => $item->browser,
                    'total_clicks' => $item->total_clicks
                ];
            });

        // Get campaign/source statistics
        $sources = ClickLog::select('source', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('source')
            ->groupBy('source')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'source' => $item->source,
                    'total_clicks' => $item->total_clicks
                ];
            });

        $mediums = ClickLog::select('medium', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('medium')
            ->groupBy('medium')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'medium' => $item->medium,
                    'total_clicks' => $item->total_clicks
                ];
            });

        $campaigns = ClickLog::select('campaign', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('campaign')
            ->groupBy('campaign')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'campaign' => $item->campaign,
                    'total_clicks' => $item->total_clicks
                ];
            });

        $terms = ClickLog::select('term', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('term')
            ->groupBy('term')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'term' => $item->term,
                    'total_clicks' => $item->total_clicks
                ];
            });

        $contents = ClickLog::select('content', DB::raw('COUNT(*) as total_clicks'))
            ->where('url_id', $url->id)
            ->whereNotNull('content')
            ->groupBy('content')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'content' => $item->content,
                    'total_clicks' => $item->total_clicks
                ];
            });

        return response()->json([
            'status' => 200,
            'data' => [
                'url' => $url,
                'clicks_timeline' => $clicksData,
                'time_format' => $diffInDays > 365 ? 'quarterly' : ($diffInDays > 90 ? 'monthly' : 'daily'),
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_all_data' => $allData
                ],
                'total_clicks' => $url->clicks, // Gunakan total clicks dari URL daripada menghitung ulang
                'total_clicks_in_range' => ClickLog::where('url_id', $url->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'analytics' => [
                    'countries' => $countries,
                    'cities' => $cities,
                    'regions' => $regions,
                    'continents' => $continents,
                    'devices' => $devices,
                    'browsers' => $browsers,
                    'sources' => $sources,
                    'mediums' => $mediums,
                    'campaigns' => $campaigns,
                    'terms' => $terms,
                    'contents' => $contents
                ]
            ],
        ]);
    }
    public function getClicksByCountry(Request $request) { return $this->getClicksByField($request, 'country'); }
    public function getClicksByCity(Request $request) { return $this->getClicksByField($request, 'city'); }
    public function getClicksByRegion(Request $request) { return $this->getClicksByField($request, 'region'); }
    public function getClicksByContinent(Request $request) { return $this->getClicksByField($request, 'continent'); }
    public function getClicksBySource(Request $request) { return $this->getClicksByField($request, 'source'); }
    public function getClicksByMedium(Request $request) { return $this->getClicksByField($request, 'medium'); }
    public function getClicksByCampaign(Request $request) { return $this->getClicksByField($request, 'campaign'); }
    public function getClicksByTerm(Request $request) { return $this->getClicksByField($request, 'term'); }
    public function getClicksByContent(Request $request) { return $this->getClicksByField($request, 'content'); }
    public function getClicksByDevice(Request $request) { return $this->getClicksByField($request, 'device'); }
    public function getClicksByBrowser(Request $request) { return $this->getClicksByField($request, 'browser'); }

}
