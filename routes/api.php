<?php

use App\Http\Controllers\API\UrlController;
use App\Http\Controllers\API\SourceController;
use App\Http\Controllers\API\MediumController;
use App\Http\Controllers\API\TagController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClickAnalyticsController;
use App\Http\Controllers\API\AuthController;

Route::apiResource('urls', UrlController::class);
Route::get('/redirect/{shortLink}', [UrlController::class, 'redirect']);
Route::apiResource('sources', SourceController::class);
Route::apiResource('mediums', MediumController::class);
Route::apiResource('tags', TagController::class);
Route::get('/qr/{id}', [UrlController::class, 'getQrCode']);

Route::get('/analytics/clicks', [ClickAnalyticsController::class, 'getAllClicks']);
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

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->put('update-name', [AuthController::class, 'updateName']);
