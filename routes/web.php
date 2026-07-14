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

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
