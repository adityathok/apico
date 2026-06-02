<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('post', 'Post')->name('post');
    Route::inertia('posts', 'Posts')->name('posts');
});

require __DIR__ . '/settings.php';
