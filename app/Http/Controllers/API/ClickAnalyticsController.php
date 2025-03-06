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

    /**
     * Get click analytics for a specific short link.
     */
    public function getClicksByUrl($id, Request $request)
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
            ->get();

        return response()->json([
            'status' => 200,
            'data' => [
                'url' => $url->short_link,
                'destination_url' => $url->destination_url,
                'clicks' => $clicksData,
                'total_clicks' => ClickLog::where('url_id', $id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
            ],
        ]);
    }
    public function getClicksByCountry(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $countries = ClickLog::select('country', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $countries]);
    }

    /**
     * Get total clicks by City
     */
    public function getClicksByCity(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $cities = ClickLog::select('city', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $cities]);
    }

    /**
     * Get total clicks by Region
     */
    public function getClicksByRegion(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $regions = ClickLog::select('region', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('region')
            ->groupBy('region')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $regions]);
    }

    /**
     * Get total clicks by Continent
     */
    public function getClicksByContinent(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $continents = ClickLog::select('continent', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('continent')
            ->groupBy('continent')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $continents]);
    }

    /**
     * Get total clicks by Source
     */
    public function getClicksBySource(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $sources = ClickLog::select('source', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('source')
            ->groupBy('source')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $sources]);
    }

    /**
     * Get total clicks by Medium
     */
    public function getClicksByMedium(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $mediums = ClickLog::select('medium', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('medium')
            ->groupBy('medium')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $mediums]);
    }

    /**
     * Get total clicks by Campaign
     */
    public function getClicksByCampaign(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $campaigns = ClickLog::select('campaign', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('campaign')
            ->groupBy('campaign')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $campaigns]);
    }

    /**
     * Get total clicks by Term
     */
    public function getClicksByTerm(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $terms = ClickLog::select('term', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('term')
            ->groupBy('term')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $terms]);
    }

    /**
     * Get total clicks by Content
     */
    public function getClicksByContent(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

        $contents = ClickLog::select('content', DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('content')
            ->groupBy('content')
            ->orderByDesc('total_clicks')
            ->get();

        return response()->json(['status' => 200, 'data' => $contents]);
    }
}