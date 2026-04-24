<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DynamicApiController;
use App\Http\Controllers\TextEditorController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dynamic/{moduleSlug}', [DynamicApiController::class, 'index']);
    Route::post('/dynamic/{moduleSlug}', [DynamicApiController::class, 'store']);
    Route::get('/dynamic/{moduleSlug}/{record}', [DynamicApiController::class, 'show']);
    Route::put('/dynamic/{moduleSlug}/{record}', [DynamicApiController::class, 'update']);
    Route::delete('/dynamic/{moduleSlug}/{record}', [DynamicApiController::class, 'destroy']);

    // Text Editor routes
    Route::post('/text-editor/validate-token', [TextEditorController::class, 'validateToken']);
    Route::get('/text-editor/{record}/{fieldSlug}/history', [TextEditorController::class, 'getHistory']);
    Route::post('/text-editor/{record}/{fieldSlug}/history', [TextEditorController::class, 'storeHistory']);
    Route::get('/text-editor/{record}/{fieldSlug}/review-status', [TextEditorController::class, 'getReviewStatus']);
    Route::get('/text-editor/{record}/{fieldSlug}/comments', [TextEditorController::class, 'getComments']);
    Route::post('/text-editor/{record}/{fieldSlug}/comments', [TextEditorController::class, 'storeComment']);
    Route::delete('/text-editor/{record}/{fieldSlug}/comments/{commentId}', [TextEditorController::class, 'resolveComment']);
    Route::post('/text-editor/{record}/{fieldSlug}/image', [TextEditorController::class, 'storeImage']);
});
