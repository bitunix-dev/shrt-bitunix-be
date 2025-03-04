<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UrlController;
Route::get('/r/{shortLink}', [UrlController::class, 'redirect'])->name('url.redirect');
Route::get('/', function () {
  return view('welcome');
});