<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('read/{slug}', [PostController::class, 'read'])->name('read');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('admin/post', 'Post')->name('post');
    Route::inertia('admin/posts', 'Posts')->name('posts');
    Route::inertia('admin/categories', 'Categories')->name('categories');
    Route::inertia('admin/tags', 'Tags')->name('tags');
    Route::inertia('admin/licenses', 'Licenses')->name('licenses');
    Route::inertia('admin/websites', 'Websites')->name('websites');
});

require __DIR__.'/settings.php';
