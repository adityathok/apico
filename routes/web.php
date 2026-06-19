<?php

use App\Http\Controllers\ArticleGeneratorController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RequestLogController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('posts', [PostController::class, 'publicIndex'])->name('frontend.posts');
Route::get('read/{slug}', [PostController::class, 'read'])->name('read');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('admin/post', 'Post')->name('post');
    Route::inertia('admin/posts', 'Posts')->name('posts');
    Route::get('admin/projects', [ProjectController::class, 'index'])->name('projects');
    Route::inertia('admin/categories', 'Categories')->name('categories');
    Route::inertia('admin/tags', 'Tags')->name('tags');
    Route::inertia('admin/licenses', 'Licenses')->name('licenses');
    Route::inertia('admin/websites', 'Websites')->name('websites');
    // Route::inertia('admin/requestlogs', 'RequestLogs')->name('requestlogs');

    Route::get('admin/requestlogs', [RequestLogController::class, 'index'])->name('requestlogsIndex');
});

Route::middleware(['auth'])->prefix('ajax')->group(function () {
    Route::apiResources([
        'posts' => PostController::class,
        'categories' => CategoryController::class,
        'tags' => TagController::class,
        'licenses' => LicenseController::class,
        'websites' => WebsiteController::class,
        // 'request-logs' => RequestLogController::class,
    ]);

    Route::post('article-generator', [ArticleGeneratorController::class, 'generate']);
});

require __DIR__.'/settings.php';
