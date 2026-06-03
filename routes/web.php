<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('post', 'Post')->name('post');
    Route::inertia('posts', 'Posts')->name('posts');
    Route::inertia('categories', 'Categories')->name('categories');
    Route::inertia('tags', 'Tags')->name('tags');
    Route::inertia('licences', 'Licences')->name('licences');
});

require __DIR__ . '/settings.php';
