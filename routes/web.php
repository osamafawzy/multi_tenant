<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Stable Google callback path used by OAuth provider settings.
Route::get('/auth/oauth/callback', [\App\Http\Controllers\AuthController::class, 'handleGoogleOAuthCallback'])
    ->middleware('oauth.callback.json_nocache')
    ->name('auth.oauth.google.callback');
