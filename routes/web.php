<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — SPA Entry Point
|--------------------------------------------------------------------------
|
| Semua route diarahkan ke view 'app' yang memuat Vue 3 SPA.
| Routing sebenarnya ditangani oleh Vue Router di sisi client.
*/

Route::get('/debug-log', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) return 'No log file found.';
    return response()->file($logFile, ['Content-Type' => 'text/plain']);
});

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
