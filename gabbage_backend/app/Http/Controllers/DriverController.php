<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Client;
use App\Models\Route;

class DriverController extends Controller
{
    public function registerClient(Request $request)
    {
        // Enhanced logging for React Native file upload debugging
        \Log::info('Client registration request data:', [
            'all_data' => $request->all(),
            'files' => $request->allFiles(),
            'has_documents' => $request->hasFile('documents'),
            'documents_count' => $request->hasFile('documents') ? count($request->file('documents')) : 0,
            'content_type' => $request->header('Content-Type'),
            'user_agent' => $request->header('User-Agent'),
            'request_method' => $request->method(),
            'is_multipart' => str_contains($request->header('Content-Type', ''), 'multipart/form-data')
        ]);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'route' => 'required|exists:routes,id',
            'clientType' => 'required|in:residential,commercial',
            'monthlyRate' => 'required|numeric|min:0',
            'numberOfUnits' => 'required|integer|min:1',
            'pickUpDay' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'serviceStartDate' => 'required|date',
            'gracePeriod' => 'required|integer|min:0|max:30',
            // Handle base64 encoded documents from React Native
            'documents' => 'sometimes|array',
            'documents.*.name' => 'required_with:documents|string',
            'documents.*.type' => 'required_with:documents|string',
            'documents.*.data' => 'required_with:documents|string', // base64 data
        ]);

        if ($validator->fails()) {
            \Log::error('Client registration validation failed:', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->except(['documents']),
                'files_received' => $request->allFiles()
            ]);
            
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 422);
        }

        try {
            $driver = $request->user();
            $organizationId = $driver->organization_id;

            // Create user account (inactive by default)
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make('password123'), // Default password
                'role' => 'client',
                'phone' => $request->phone,
                'address' => $request->address,
                'organization_id' => $organizationId,
                'isActive' => false, // Inactive until organization approves
            ]);

            // Generate account number
            $accountNumber = 'ACC' . str_pad($user->id, 6, '0', STR_PAD_LEFT);

            // Create client record
            $client = Client::create([
                'user_id' => $user->id,
                'organization_id' => $organizationId,
                'route_id' => $request->route,
                'clientType' => $request->clientType,
                'monthlyRate' => $request->monthlyRate,
                'numberOfUnits' => $request->numberOfUnits,
                'pickUpDay' => $request->pickUpDay,
                'serviceStartDate' => $request->serviceStartDate,
                'gracePeriod' => $request->gracePeriod,
                'accountNumber' => $accountNumber,
            ]);

            // Handle base64 encoded documents from React Native
            $documentPaths = [];
            if ($request->has('documents') && is_array($request->documents)) {
                foreach ($request->documents as $index => $document) {
                    if (isset($document['data']) && isset($document['name'])) {
                        try {
                            // Decode base64 data
                            $fileData = base64_decode($document['data']);
                            
                            // Generate unique filename
                            $filename = time() . '_' . uniqid() . '_' . $document['name'];
                            
                            // Save file to storage
                            $path = 'documents/' . $filename;
                            \Storage::disk('public')->put($path, $fileData);
                            
                            $documentPaths[] = url('/api/storage/documents/' . $filename);
                            
                            \Log::info('Document saved:', [
                                'filename' => $filename,
                                'size' => strlen($fileData),
                                'type' => $document['type'] ?? 'unknown'
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('Failed to save document:', [
                                'index' => $index,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
            
            \Log::info('Documents processed:', [
                'count' => count($documentPaths),
                'paths' => $documentPaths
            ]);

            // Update user with account number and documents
            $user->update([
                'account_number' => $accountNumber,
                'documents' => $documentPaths
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Client registered successfully. Account is pending organization approval.',
                'data' => [
                    'client' => $client,
                    'user' => $user,
                    'registered_by_driver' => $driver->name
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Driver client registration failed:', [
                'error' => $e->getMessage(),
                'driver_id' => $request->user()->id
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Registration failed',
                'message' => 'Failed to register client: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getOrganizationRoutes(Request $request)
    {
        try {
            $driver = $request->user();
            $organizationId = $driver->organization_id;

            $routes = Route::where('organization_id', $organizationId)
                ->select('id', 'name', 'path', 'description')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'data' => $routes
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Failed to fetch routes',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}