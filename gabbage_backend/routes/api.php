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
use App\Http\Controllers\BagTransferController;



// Protected file access
Route::get('/storage/documents/{filename}', function (Request $request, $filename) {
    // Check for token in query parameter or Authorization header
    $token = $request->query('token') ?? $request->bearerToken();
    
    // For profile images, allow access if filename matches pattern and user exists
    if (preg_match('/^\d+_profile_\d+\.(jpg|jpeg|png|gif)$/', $filename)) {
        // Extract user ID from filename (format: timestamp_profile_userid.ext)
        preg_match('/_(\d+)\.(jpg|jpeg|png|gif)$/', $filename, $matches);
        if (isset($matches[1])) {
            $userId = $matches[1];
            $user = \App\Models\User::find($userId);
            if ($user && $user->profile_image && str_contains($user->profile_image, $filename)) {
                // Allow access to profile image
                $path = storage_path('app/public/documents/' . $filename);
                if (file_exists($path)) {
                    return response()->file($path);
                }
            }
        }
    }
    
    if (!$token) {
        return response()->json(['status' => false, 'error' => 'Unauthorized', 'message' => 'Access token is required'], 401);
    }
    
    // Authenticate user with token
    $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
    
    if (!$user) {
        return response()->json(['status' => false, 'error' => 'Unauthorized', 'message' => 'Invalid token'], 401);
    }
    
    $fileUrl = url('/api/storage/documents/' . $filename);
    $oldUrl = url('/storage/documents/' . $filename);
    $userDocuments = $user->documents ?? [];
    
    // Check if user owns this file directly
    $hasAccess = in_array($fileUrl, $userDocuments) || in_array($oldUrl, $userDocuments);
    
    // If organization, also check if any of their drivers/clients own this file
    if (!$hasAccess && $user->role === 'organization') {
        // Check drivers
        $organizationUsers = \App\Models\User::where('organization_id', $user->id)
            ->where('role', 'driver')
            ->get();
        
        foreach ($organizationUsers as $orgUser) {
            $orgUserDocuments = $orgUser->documents ?? [];
            // Handle both string URLs and object format
            foreach ($orgUserDocuments as $doc) {
                $docUrl = is_string($doc) ? $doc : ($doc['url'] ?? '');
                if ($docUrl === $fileUrl || $docUrl === $oldUrl) {
                    $hasAccess = true;
                    break 2;
                }
            }
        }
        
        // Check clients (they have different relationship structure)
        if (!$hasAccess) {
            $clients = \App\Models\Client::where('organization_id', $user->id)->with('user')->get();
            
            foreach ($clients as $client) {
                if ($client->user) {
                    $clientUserDocuments = $client->user->documents ?? [];
                    // Handle both string URLs and object format
                    foreach ($clientUserDocuments as $doc) {
                        $docUrl = is_string($doc) ? $doc : ($doc['url'] ?? '');
                        if ($docUrl === $fileUrl || $docUrl === $oldUrl) {
                            $hasAccess = true;
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    // Admin and super_admin users have access to all files
    if (!$hasAccess && !in_array($user->role, ['admin', 'super_admin'])) {
        abort(403, 'Unauthorized access to file');
    }
    
    $path = storage_path('app/public/documents/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

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
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/update-profile', [AuthController::class, 'updateProfile'])->middleware('file.uploads');
        Route::post('/create-organization', [AuthController::class, 'createOrganization'])->middleware(['admin.only', 'file.uploads']);
        Route::post('/organization/manage', [AuthController::class, 'manageOrganization'])->middleware('admin.only');
    });
});

// Organization routes (for logged-in organizations)
Route::prefix('organization')->middleware(['auth:sanctum'])->group(function () {
    // Dashboard
    Route::get('/dashboard/counts', [AuthController::class, 'getDashboardCounts']);
    
    // Drivers management
    Route::prefix('drivers')->group(function () {
        Route::get('/', [AuthController::class, 'listOrganizationDrivers']);
        Route::post('/', [AuthController::class, 'createDriver'])->middleware('file.uploads');
        Route::get('/{id}', [AuthController::class, 'getDriverDetails']);
        Route::put('/{id}', [AuthController::class, 'updateDriver'])->middleware('file.uploads');
        Route::post('/{id}/update', [AuthController::class, 'updateDriver'])->middleware('file.uploads');
        Route::delete('/{id}', [AuthController::class, 'deleteDriver']);
        Route::post('/{id}/send-credentials', [AuthController::class, 'sendDriverCredentials']);
        Route::post('/{id}/toggle-status', [AuthController::class, 'toggleDriverStatus']);
        Route::delete('/{id}/documents', [AuthController::class, 'deleteDriverDocument']);
    });
    
    // Clients management
    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::get('/search', [ClientController::class, 'search']);
        Route::post('/', [ClientController::class, 'store'])->middleware('file.uploads');
        Route::get('/{id}', [ClientController::class, 'show']);
        Route::put('/{id}', [ClientController::class, 'update'])->middleware('file.uploads');
        Route::post('/{id}', [ClientController::class, 'update'])->middleware('file.uploads'); // For method spoofing
        Route::post('/{id}/toggle-status', [ClientController::class, 'toggleStatus']);
        Route::delete('/{id}', [ClientController::class, 'destroy']);
        Route::delete('/{id}/documents', [ClientController::class, 'deleteDocument']);
        Route::get('/{id}/payments', [PaymentController::class, 'getClientPayments']);
        Route::get('/{id}/invoices', [InvoiceController::class, 'getClientInvoices']);
        Route::get('/{id}/pickups', [\App\Http\Controllers\PickupController::class, 'getClientPickups']);
        Route::get('/{id}/bags', [AuthController::class, 'getClientBagHistory']);
    });
    
    // Routes management
    Route::prefix('routes')->middleware('organization.only')->group(function () {
        Route::get('/', [RouteController::class, 'index'])->withoutMiddleware('organization.only');
        Route::post('/', [RouteController::class, 'store']);
        Route::get('/{id}', [RouteController::class, 'show'])->withoutMiddleware('organization.only');
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
        Route::get('/aging-summary', [InvoiceController::class, 'getAgingSummary']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::post('/resend', [InvoiceController::class, 'resendInvoices']);
    });
    
    // Bags management
    Route::prefix('bags')->group(function () {
        Route::get('/', [BagController::class, 'getOrganizationBags']);
        Route::post('/add', [BagController::class, 'addBags']);
        Route::post('/remove', [BagController::class, 'removeBags']);
        Route::post('/allocate', [BagController::class, 'allocateToDriver']);
        Route::post('/process-return', [BagController::class, 'processBagReturn']);
        
        // Bag issuing with OTP
        Route::post('/issue/request', [BagIssueController::class, 'requestOtp']);
        Route::post('/issue/verify', [BagIssueController::class, 'verifyOtp']);
        Route::get('/issues/list', [BagIssueController::class, 'index']);
        
        // Bag transfers for organization view
        Route::get('/transfers', [BagTransferController::class, 'getOrganizationTransfers']);
    });
    
    // Pickups management
    Route::prefix('pickups')->group(function () {
        Route::get('/', [\App\Http\Controllers\PickupController::class, 'getPickups']);
        Route::post('/', [\App\Http\Controllers\PickupController::class, 'createPickup']);
        Route::get('/clients', [\App\Http\Controllers\PickupController::class, 'getClientsToPickup']);
    });
});

// Driver routes
Route::prefix('driver')->middleware(['auth:sanctum', 'driver.only'])->group(function () {
    Route::get('/dashboard/stats', [AuthController::class, 'getDriverDashboardStats']);
    Route::get('/stats', [AuthController::class, 'getDriverStats']);
    Route::get('/drivers', [AuthController::class, 'getOrganizationDrivers']);
    
    Route::prefix('bags')->group(function () {
        Route::get('/', [BagController::class, 'getDriverBags']);
        Route::get('/stats', [BagController::class, 'getDriverBagStats']);
        Route::post('/issue/request', [BagIssueController::class, 'requestOtp']);
        Route::post('/issue/verify', [BagIssueController::class, 'verifyOtp']);
        
        // Bag transfers
        Route::post('/transfer/initiate', [BagTransferController::class, 'initiateBagTransfer']);
        Route::post('/transfer/complete', [BagTransferController::class, 'completeBagTransfer']);
        Route::get('/transfer/history', [BagTransferController::class, 'getTransferHistory']);
    });
    
    Route::prefix('pickups')->group(function () {
        Route::post('/mark', [\App\Http\Controllers\PickupController::class, 'markPickup']);
        Route::get('/', [\App\Http\Controllers\PickupController::class, 'getPickups']);
        Route::get('/all/picked', [\App\Http\Controllers\PickupController::class, 'getAllPicked']);
        Route::get('/all/unpicked', [\App\Http\Controllers\PickupController::class, 'getAllUnpicked']);
        Route::get('/clients', [\App\Http\Controllers\PickupController::class, 'getClientsToPickup']);
    });
    
    Route::prefix('routes')->group(function () {
        Route::get('/', [RouteController::class, 'getDriverRoutes']);
        Route::get('/{id}', [RouteController::class, 'getDriverRouteDetails']);
        Route::post('/activate', [\App\Http\Controllers\PickupController::class, 'activateRoute']);
        Route::post('/deactivate', [\App\Http\Controllers\PickupController::class, 'deactivateRoute']);
        Route::get('/active', [\App\Http\Controllers\PickupController::class, 'getActiveRoutes']);
    });
    
    Route::prefix('route')->group(function () {
        Route::get('/clients', [\App\Http\Controllers\PickupController::class, 'getRouteClients']);
    });
    
    // Dashboard endpoints
    Route::get('/stats', [\App\Http\Controllers\Auth\AuthController::class, 'getOrganizationDashboardStats']);
    Route::get('/recent-activity', [\App\Http\Controllers\Auth\AuthController::class, 'getRecentActivity']);
    Route::get('/pickups/today-summary', [\App\Http\Controllers\PickupController::class, 'getTodayPickupsSummary']);
    Route::get('/financial-summary', [\App\Http\Controllers\PaymentController::class, 'getFinancialSummary']);
    Route::post('/register-client', [\App\Http\Controllers\DriverController::class, 'registerClient'])->middleware('file.uploads');
    Route::get('/organization/routes', [\App\Http\Controllers\DriverController::class, 'getOrganizationRoutes']);
});

// M-Pesa Callback (no auth required)
Route::post('/mpesa/callback', [PaymentController::class, 'mpesaCallback']);

// Admin routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin.only'])->group(function () {
    // Dashboard
    Route::get('/dashboard/stats', [AuthController::class, 'getAdminDashboardStats']);
    
    // File uploads
    Route::post('/upload-files', [\App\Http\Controllers\FileController::class, 'uploadFiles']);
    
    // Organizations management
    Route::prefix('organizations')->group(function () {
        Route::get('/', [AuthController::class, 'listOrganizations']);
        Route::post('/send-credentials', [AuthController::class, 'sendCredentials']);
        Route::post('/deactivate', [AuthController::class, 'deactivateOrganization']);
        Route::get('/{id}', [AuthController::class, 'getOrganization']);
    });
    
    // Admins management
    Route::prefix('admins')->group(function () {
        Route::get('/list', [AuthController::class, 'listAdmins']);
        Route::post('/create', [AuthController::class, 'createAdminByAdmin']);
        Route::get('/{id}', [AuthController::class, 'getAdmin']);
        Route::put('/{id}', [AuthController::class, 'updateAdmin']);
        Route::delete('/{id}', [AuthController::class, 'deleteAdmin']);
        Route::post('/{id}/deactivate', [AuthController::class, 'deactivateAdmin']);
        Route::post('/{id}/make-super', [AuthController::class, 'makeSuperAdmin']);
    });
    
    // Profile management
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    
    // Activity logs
    Route::get('/activity-logs', [AuthController::class, 'getActivityLogs']);
    

});
