<?php

use App\Http\Controllers\ArticleGeneratorController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProjectChangelogController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RequestLogController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('posts', [PostController::class, 'publicIndex'])->name('frontend.posts');
Route::get('read/{slug}', [PostController::class, 'read'])->name('read');
Route::get('project/changelog/{project_slug}', [ProjectChangelogController::class, 'publicChangelogIndex'])->name('project.changelog');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::inertia('admin/post', 'Post')->name('post');
    Route::inertia('admin/posts', 'Posts')->name('posts');
    Route::get('admin/projects', [ProjectController::class, 'index'])->name('projects');
    Route::inertia('admin/users', 'Users')->name('users');
    Route::inertia('admin/categories', 'Categories')->name('categories');
    Route::inertia('admin/tags', 'Tags')->name('tags');
    Route::inertia('admin/licenses', 'Licenses')->name('licenses');
    Route::inertia('admin/websites', 'Websites')->name('websites');
    Route::inertia('admin/servers', 'Servers')->name('servers');
    Route::get('admin/system/update', [UpdateController::class, 'page'])->name('system.update');
    Route::get('admin/system/check-updates', [UpdateController::class, 'checkUpdates'])->name('system.check-updates');
    Route::post('admin/system/perform-update', [UpdateController::class, 'performUpdate'])->name('system.perform-update');
    Route::post('admin/system/restore-backup', [UpdateController::class, 'restoreBackup'])->name('system.restore-backup');
    // Route::inertia('admin/requestlogs', 'RequestLogs')->name('requestlogs');

    Route::get('admin/requestlogs', [RequestLogController::class, 'index'])->name('requestlogsIndex');
});

Route::middleware(['auth'])->prefix('ajax')->group(function () {
    Route::get('posts/recommended-images', [PostController::class, 'recommendedImages']);
    Route::post('posts/recommended-image', [PostController::class, 'recommendedImage']);

    Route::apiResources([
        'posts' => PostController::class,
        'projects' => ProjectController::class,
        'project-changelogs' => ProjectChangelogController::class,
        'users' => UserController::class,
        'categories' => CategoryController::class,
        'tags' => TagController::class,
        'licenses' => LicenseController::class,
        'websites' => WebsiteController::class,
        'servers' => ServerController::class,
        'request-logs' => RequestLogController::class,
    ]);

    Route::post('article-generator', [ArticleGeneratorController::class, 'generate']);
    Route::post('article-generator-by-agent', [ArticleGeneratorController::class, 'generate_by_agent']);
});

require __DIR__.'/settings.php';
