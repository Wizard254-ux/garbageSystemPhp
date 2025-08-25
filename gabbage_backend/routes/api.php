<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\BagController;
use App\Http\Controllers\BagIssueController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;



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
    Route::post('/register', [AuthController::class, 'register'])->middleware('file.uploads');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/create-admin', [AuthController::class, 'createAdmin']);
    Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
    Route::post('/test', function () {
    return response()->json(['message' => 'Route works']);
});

    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/create-organization', [AuthController::class, 'createOrganization'])->middleware(['admin.only', 'file.uploads']);
        Route::post('/organization/manage', [AuthController::class, 'manageOrganization'])->middleware('admin.only');
    });
});

// Organization routes (for logged-in organizations)
Route::prefix('organization')->middleware(['auth:sanctum', 'organization.only'])->group(function () {
    // Dashboard
    Route::get('/dashboard/stats', [AuthController::class, 'getOrganizationStats']);
    
    // Drivers management
    Route::prefix('drivers')->group(function () {
        Route::get('/', [AuthController::class, 'listOrganizationDrivers']);
        Route::post('/', [AuthController::class, 'createDriver'])->middleware('file.uploads');
        Route::get('/{id}', [AuthController::class, 'getDriverDetails']);
        Route::put('/{id}', [AuthController::class, 'updateDriver'])->middleware('file.uploads');
        Route::post('/{id}/update', [AuthController::class, 'updateDriver'])->middleware('file.uploads');
        Route::delete('/{id}', [AuthController::class, 'deleteDriver']);
        Route::post('/{id}/send-credentials', [AuthController::class, 'sendDriverCredentials']);
        Route::delete('/{id}/documents', [AuthController::class, 'deleteDriverDocument']);
    });
    
    // Clients management
    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::post('/', [ClientController::class, 'store'])->middleware('file.uploads');
        Route::get('/{id}', [ClientController::class, 'show']);
        Route::put('/{id}', [ClientController::class, 'update'])->middleware('file.uploads');
        Route::post('/{id}', [ClientController::class, 'update'])->middleware('file.uploads'); // For method spoofing
        Route::delete('/{id}', [ClientController::class, 'destroy']);
        Route::delete('/{id}/documents', [ClientController::class, 'deleteDocument']);
        Route::get('/{id}/payments', [PaymentController::class, 'getClientPayments']);
        Route::get('/{id}/invoices', [InvoiceController::class, 'getClientInvoices']);
        Route::get('/{id}/pickups', [\App\Http\Controllers\PickupController::class, 'getClientPickups']);
        Route::get('/{id}/bags', [AuthController::class, 'getClientBagHistory']);
    });
    
    // Routes management
    Route::prefix('routes')->group(function () {
        Route::get('/', [RouteController::class, 'index']);
        Route::post('/', [RouteController::class, 'store']);
        Route::get('/{id}', [RouteController::class, 'show']);
        Route::put('/{id}', [RouteController::class, 'update']);
        Route::delete('/{id}', [RouteController::class, 'destroy']);
    });
    
    // Payments management
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/cash', [PaymentController::class, 'createCashPayment']);
        Route::get('/{id}', [PaymentController::class, 'show']);
    });
    
    // Invoices management
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::post('/resend', [InvoiceController::class, 'resendInvoices']);
    });
    
    // Bags management
    Route::prefix('bags')->group(function () {
        Route::get('/', [BagController::class, 'index']);
        Route::post('/', [BagController::class, 'store']);
        Route::get('/{id}', [BagController::class, 'show']);
        Route::put('/{id}', [BagController::class, 'update']);
        Route::delete('/{id}', [BagController::class, 'destroy']);
        
        // Bag issuing with OTP
        Route::post('/issue/request', [BagIssueController::class, 'requestOtp']);
        Route::post('/issue/verify', [BagIssueController::class, 'verifyOtp']);
        Route::get('/issues/list', [BagIssueController::class, 'index']);
    });
    
    // Pickups management
    Route::prefix('pickups')->group(function () {
        Route::get('/', [\App\Http\Controllers\PickupController::class, 'getPickups']);
        Route::get('/clients', [\App\Http\Controllers\PickupController::class, 'getClientsToPickup']);
    });
});

// Driver routes (for bag issuing and pickups)
Route::prefix('driver')->middleware(['auth:sanctum', 'driver.only'])->group(function () {
    Route::prefix('bags')->group(function () {
        Route::post('/issue/request', [BagIssueController::class, 'requestOtp']);
        Route::post('/issue/verify', [BagIssueController::class, 'verifyOtp']);
    });
    
    Route::prefix('pickups')->group(function () {
        Route::post('/mark', [\App\Http\Controllers\PickupController::class, 'markPickup']);
        Route::get('/', [\App\Http\Controllers\PickupController::class, 'getPickups']);
        Route::get('/clients', [\App\Http\Controllers\PickupController::class, 'getClientsToPickup']);
    });
    
    Route::prefix('routes')->group(function () {
        Route::post('/activate', [\App\Http\Controllers\PickupController::class, 'activateRoute']);
        Route::post('/deactivate', [\App\Http\Controllers\PickupController::class, 'deactivateRoute']);
        Route::get('/active', [\App\Http\Controllers\PickupController::class, 'getActiveRoutes']);
    });
});

// M-Pesa Callback (no auth required)
Route::post('/mpesa/callback', [PaymentController::class, 'mpesaCallback']);

// Admin routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin.only'])->group(function () {
    Route::prefix('organizations')->group(function () {
        Route::post('/send-credentials', [AuthController::class, 'sendCredentials']);
        Route::get('/{id}', [AuthController::class, 'getOrganization']);
    });
    Route::prefix('admins')->group(function () {
        Route::get('/list', [AuthController::class, 'listAdmins']);
        Route::post('/create', [AuthController::class, 'createAdminByAdmin']);
    });
});
