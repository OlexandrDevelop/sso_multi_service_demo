<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MeController;

Route::middleware('sso.auth')->group(function () {
	Route::get('/sso/me', MeController::class);
	Route::get('/protected/ping', fn () => response()->json(['ok' => true]));
}); 