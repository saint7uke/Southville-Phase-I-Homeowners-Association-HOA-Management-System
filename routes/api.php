<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\CheckEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('/announcements/latest', [AnnouncementController::class, 'latest']);
    Route::post('/check-email', CheckEmailController::class)->middleware('throttle:30,1');
});
