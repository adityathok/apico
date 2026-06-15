<?php

use App\Http\Controllers\Api\V1\NewsController;
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
    Route::get('/news/categories', [NewsController::class, 'categories']);
});
