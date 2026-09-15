<?php

use App\Http\Controllers\PlanController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicPricingController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)->name('public.home');
Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::view('/home', 'dashboard')->name('home');
Route::get('/plans', [PlanController::class, 'index'])->middleware('auth')->name('plans');
Route::get('/pricing', PublicPricingController::class)->name('pricing');
Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::get('/boards/{board}', function (string $board) {
    return view('board', ['board' => $board]);
})->whereNumber('board')->name('boards.show');
