<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard')->name('dashboard');
Route::view('/dashboard', 'dashboard');
Route::view('/home', 'dashboard')->name('home');
Route::get('/boards/{board}', function (string $board) {
    return view('board', ['board' => $board]);
})->whereNumber('board')->name('boards.show');
