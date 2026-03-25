<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/auth/oauth-links', [\App\Http\Controllers\AuthController::class, 'getOAuthLinks']);
Route::post('/auth/oauth/callback/apple', [\App\Http\Controllers\AuthController::class, 'handleAppleOAuthCallback'])
    ->name('auth.oauth.apple.callback');
