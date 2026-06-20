<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiPublic\V1\ProjectPublicController;

Route::prefix('public/v1')->group(function () {
    Route::get('/project/{slug}', [ProjectPublicController::class, 'show']);
});
