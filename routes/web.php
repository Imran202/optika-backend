<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Featured Action Admin Interface
Route::get('/admin/featured-action', function () {
    return view('featured-action-admin');
});

// Bonus System Admin Interface
Route::get('/admin/bonus', function () {
    return view('bonus-admin');
});
