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
        $startDate = $request->query('start_date', Carbon::now()->subDays(1));
        $endDate = $request->query('end_date', Carbon::now());

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
                'clicks' => $this->addIncrementalId($clicksData),
                'total_clicks' => ClickLog::whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
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

        $data = ClickLog::select($field, DB::raw('COUNT(*) as total_clicks'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull($field)
            ->groupBy($field)
            ->orderByDesc('total_clicks')
            ->get()
            ->map(function ($item, $index) use ($field) {
                return [
                    'id' => $index + 1,
                    $field => $item[$field], // Pastikan $field ada di dalam array
                    'total_clicks' => $item['total_clicks']
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
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'hour' => $item->hour,
                    'clicks' => $item->clicks,
                ];
            });

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