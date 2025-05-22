<?php

use App\Http\Controllers\API\UrlController;
use App\Http\Controllers\API\SourceController;
use App\Http\Controllers\API\MediumController;
use App\Http\Controllers\API\TagController;
use App\Http\Controllers\API\VipCodeController; // Import VipCodeController
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClickAnalyticsController;
use App\Http\Controllers\API\AuthController;

Route::get('/redirect/{shortLink}', [UrlController::class, 'redirect']);
Route::get('/qr/{id}', [UrlController::class, 'getQrCode']);
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->put('update-name', [AuthController::class, 'updateName']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::apiResource('urls', UrlController::class);
    Route::apiResource('sources', SourceController::class);
    Route::apiResource('mediums', MediumController::class);
    Route::apiResource('tags', TagController::class);
    Route::apiResource('vip-codes', VipCodeController::class); // Tambah route VipCode

    Route::get('/analytics/clicks', [ClickAnalyticsController::class, 'getAllClicks']);
    Route::get('/analytics/urls', [ClickAnalyticsController::class, 'getUrls']);
    Route::get('/analytics/clicks/{id}', [ClickAnalyticsController::class, 'getClicksByUrl']);
    Route::get('/analytics/countries', [ClickAnalyticsController::class, 'getClicksByCountry']);
    Route::get('/analytics/cities', [ClickAnalyticsController::class, 'getClicksByCity']);
    Route::get('/analytics/regions', [ClickAnalyticsController::class, 'getClicksByRegion']);
    Route::get('/analytics/continents', [ClickAnalyticsController::class, 'getClicksByContinent']);
    Route::get('/analytics/source', [ClickAnalyticsController::class, 'getClicksBySource']);
    Route::get('/analytics/medium', [ClickAnalyticsController::class, 'getClicksByMedium']);
    Route::get('/analytics/campaign', [ClickAnalyticsController::class, 'getClicksByCampaign']);
    Route::get('/analytics/term', [ClickAnalyticsController::class, 'getClicksByTerm']);
    Route::get('/analytics/content', [ClickAnalyticsController::class, 'getClicksByContent']);
    Route::get('/analytics/devices', [ClickAnalyticsController::class, 'getClicksByDevice']);
    Route::get('/analytics/browsers', [ClickAnalyticsController::class, 'getClicksByBrowser']);
    Route::get('/analytics/short-link/{shortLink}', [ClickAnalyticsController::class, 'getClicksByShortLink'])->where('shortLink', '.*');
    Route::put('update-name', [AuthController::class, 'updateName']);
});
