<?php

use App\Http\Controllers\Api\V1\LicenseController as ApiV1LicenseController;
use App\Http\Controllers\Api\V1\NewsController as ApiV1NewsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RequestLogController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth'])->group(function () {
    Route::apiResources([
        'posts' => PostController::class,
        'categories' => CategoryController::class,
        'tags' => TagController::class,
        'licenses' => LicenseController::class,
        'websites' => WebsiteController::class,
        'request-logs' => RequestLogController::class,
    ]);
});

Route::middleware(['license'])->prefix('v1')->group(function () {
    Route::get('/news/categories', [ApiV1NewsController::class, 'categories']);
    Route::get('/news/posts', [ApiV1NewsController::class, 'posts']);
    Route::get('/license', [ApiV1LicenseController::class, 'index']);
});

require __DIR__ . '/api_public.php';
