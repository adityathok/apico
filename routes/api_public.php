<?php

use App\Http\Controllers\ApiPublic\V1\ProjectPublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('public/v1')->group(function () {
    Route::get('/project/{slug}', [ProjectPublicController::class, 'show']);
});

Route::middleware('public.ai.signature')->prefix('ai/public')->group(function () {
    //
});
