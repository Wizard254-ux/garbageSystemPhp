<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Protected file access
Route::get('/storage/documents/{filename}', function (Request $request, $filename) {
    $user = $request->user();
    $fileUrl = url('/api/storage/documents/' . $filename);
    
    // Check if user owns this file (check both URL formats)
    $oldUrl = url('/storage/documents/' . $filename);
    $userDocuments = $user->documents ?? [];
    
    if (!in_array($fileUrl, $userDocuments) && !in_array($oldUrl, $userDocuments)) {
        abort(403, 'Unauthorized access to file');
    }
    
    $path = storage_path('app/public/documents/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
})->middleware('auth:sanctum');

// Auth routes
Route::prefix('auth')->group(function () {
    print_r("here");
    Route::post('/register', [AuthController::class, 'register'])->middleware('file.uploads');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/create-admin', [AuthController::class, 'createAdmin']);
    Route::post('/test', function () {
    return response()->json(['message' => 'Route works']);
});

    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/create-organization', [AuthController::class, 'createOrganization'])->middleware(['admin.only', 'file.uploads']);
    });
});
