<?php

use App\Http\Controllers\API\UrlController;
use App\Http\Controllers\API\SourceController;
use App\Http\Controllers\API\MediumController;
use App\Http\Controllers\API\TagController;
use Illuminate\Support\Facades\Route;

Route::apiResource('urls', UrlController::class);
Route::apiResource('sources', SourceController::class);
Route::apiResource('mediums', MediumController::class);
Route::apiResource('tags', TagController::class);