<?php

use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard')->name('dashboard');
Route::view('/dashboard', 'dashboard');
Route::view('/home', 'dashboard')->name('home');
Route::get('/plans', [PlanController::class, 'index'])->middleware('auth')->name('plans');
Route::get('/login', fn () => redirect('/'))->name('login');
Route::get('/boards/{board}', function (string $board) {
    return view('board', ['board' => $board]);
})->whereNumber('board')->name('boards.show');
