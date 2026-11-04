<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('deploysift');
});
Route::get('/login', fn () => view('auth-login'))->name('login');
Route::get('/register', fn () => view('auth-register'));
Route::middleware('throttle:10,1')->group(function () {
Route::post('/auth/register',[AuthController::class,'register']);
Route::post('/auth/login',[AuthController::class,'login']);
});
Route::post('/auth/logout',[AuthController::class,'logout']);
Route::get('/auth/{provider}/redirect',[AuthController::class,'redirect']);
Route::get('/auth/{provider}/callback',[AuthController::class,'callback']);
