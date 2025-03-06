<?php

use App\Http\Controllers\API\UrlController;
use App\Http\Controllers\API\SourceController;
use App\Http\Controllers\API\MediumController;
use App\Http\Controllers\API\TagController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ClickAnalyticsController;


Route::apiResource('urls', UrlController::class);
Route::get('/redirect/{shortLink}', [UrlController::class, 'redirect']);
Route::apiResource('sources', SourceController::class);
Route::apiResource('mediums', MediumController::class);
Route::apiResource('tags', TagController::class);
Route::get('/qr/{id}', [UrlController::class, 'getQrCode']);

Route::get('/analytics/clicks', [ClickAnalyticsController::class, 'getAllClicks']);
Route::get('/analytics/clicks/{id}', [ClickAnalyticsController::class, 'getClicksByUrl']);