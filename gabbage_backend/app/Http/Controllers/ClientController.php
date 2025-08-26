<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $request->user()->id;
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $search = $request->get('search', '');
        
        $query = Client::where('organization_id', $organizationId)
            ->with(['user', 'route']);
            
        // Add search functionality
        if (!empty($search)) {
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('email', 'LIKE', '%' . $search . '%')
                  ->orWhere('phone', 'LIKE', '%' . $search . '%');
            })->orWhere('accountNumber', 'LIKE', '%' . $search . '%');
        }
        
        // Get total count for pagination
        $totalClients = $query->count();
        $totalPages = ceil($totalClients / $limit);
        
        // Apply pagination and ordering
        $clients = $query->orderBy('created_at', 'desc')
                        ->skip(($page - 1) * $limit)
                        ->take($limit)
                        ->get()
                        ->map(function($client) {
                            return [
                                'id' => $client->user->id,
                                'name' => $client->user->name,
                                'email' => $client->user->email,
                                'phone' => $client->user->phone,
                                'address' => $client->user->address,
                                'isActive' => $client->user->isActive ?? true,
                                'accountNumber' => $client->accountNumber,
                                'clientType' => $client->clientType,
                                'monthlyRate' => $client->monthlyRate,
                                'numberOfUnits' => $client->numberOfUnits,
                                'pickUpDay' => $client->pickUpDay,
                                'gracePeriod' => $client->gracePeriod,
                                'serviceStartDate' => $client->serviceStartDate,
                                'route' => $client->route,
                                'documents' => $client->user->documents ?? []
                            ];
                        });

        return response()->json([
            'status' => true,
            'data' => [
                'users' => $clients,
                'pagination' => [
                    'currentPage' => (int)$page,
                    'totalPages' => $totalPages,
                    'totalItems' => $totalClients,
                    'itemsPerPage' => (int)$limit,
                    'hasNextPage' => $page < $totalPages,
                    'hasPrevPage' => $page > 1
                ]
            ]
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'route' => 'required|exists:routes,id',
            'clientType' => 'required|in:residential,commercial',
            'monthlyRate' => 'required|numeric|min:0',
            'numberOfUnits' => 'required|integer|min:1',
            'pickUpDay' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'gracePeriod' => 'required|integer|min:0|max:30',
            'serviceStartDate' => 'required|date',
            'uploaded_documents' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        // Create user first
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('password123'), // Default password
            'role' => 'client',
            'phone' => $request->phone,
            'address' => $request->address,
            'documents' => $request->uploaded_documents ?? []
        ]);

        // Create client record (accountNumber auto-generated)
        $client = Client::create([
            'user_id' => $user->id,
            'organization_id' => $request->user()->id,
            'route_id' => $request->route,
            'clientType' => $request->clientType,
            'monthlyRate' => $request->monthlyRate,
            'numberOfUnits' => $request->numberOfUnits,
            'pickUpDay' => $request->pickUpDay,
            'gracePeriod' => $request->gracePeriod,
            'serviceStartDate' => $request->serviceStartDate
        ]);

        // Format the response similar to the list format
        $clientData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'isActive' => $user->isActive ?? true,
            'accountNumber' => $client->accountNumber,
            'clientType' => $client->clientType,
            'monthlyRate' => $client->monthlyRate,
            'numberOfUnits' => $client->numberOfUnits,
            'pickUpDay' => $client->pickUpDay,
            'gracePeriod' => $client->gracePeriod,
            'serviceStartDate' => $client->serviceStartDate,
            'route' => $client->route,
            'documents' => $user->documents ?? []
        ];

        return response()->json([
            'status' => true,
            'message' => 'Client created successfully',
            'data' => ['client' => $clientData]
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $user = User::find($id);
        
        if (!$user || $user->role !== 'client') {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Client not found'
            ], 404);
        }

        $client = Client::where('user_id', $id)
            ->where('organization_id', $organizationId)
            ->with(['user', 'route'])
            ->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Client not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => ['client' => $client]
        ], 200);
    }

    public function update(Request $request, $id)
    {
        // Debug: Log all request data
        // \Log::info('Client Update Request Data:', $request->all());
        // \Log::info('Files:', $request->allFiles());
        
        $organizationId = $request->user()->id;
        $client = Client::where('user_id', $id)
            ->where('organization_id', $organizationId)
            ->with('user')
            ->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Client not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $client->user_id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'route' => 'nullable|exists:routes,id',
            'clientType' => 'nullable|in:residential,commercial',
            'monthlyRate' => 'nullable|numeric|min:0',
            'numberOfUnits' => 'nullable|integer|min:1',
            'pickUpDay' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'gracePeriod' => 'nullable|integer|min:0|max:30',
            'isActive' => 'nullable|in:true,false,1,0',
            'uploaded_documents' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        // Handle document updates - add new documents to existing ones
        $currentDocuments = $client->user->documents ?? [];
        $newDocuments = $request->uploaded_documents ?? [];
        \Log::info('Current documents:', $currentDocuments);
        \Log::info('New documents from middleware:', $newDocuments);
        
        // Only merge if there are new documents, otherwise keep existing
        if (!empty($newDocuments)) {
            $allDocuments = array_merge($currentDocuments, $newDocuments);
        } else {
            $allDocuments = $currentDocuments;
        }
        \Log::info('All documents combined:', $allDocuments);

        // Update user data including documents
        $userUpdateData = [];
        if ($request->has('name')) $userUpdateData['name'] = $request->name;
        if ($request->has('email')) $userUpdateData['email'] = $request->email;
        if ($request->has('phone')) $userUpdateData['phone'] = $request->phone;
        if ($request->has('address')) $userUpdateData['address'] = $request->address;
        if ($request->has('isActive')) $userUpdateData['isActive'] = filter_var($request->isActive, FILTER_VALIDATE_BOOLEAN);
        $userUpdateData['documents'] = $allDocuments;
        
        \Log::info('User Update Data:', $userUpdateData);
        $client->user->update($userUpdateData);
        
        // Update client data
        $clientData = [];
        if ($request->has('route')) $clientData['route_id'] = $request->route;
        if ($request->has('clientType')) $clientData['clientType'] = $request->clientType;
        if ($request->has('monthlyRate')) $clientData['monthlyRate'] = $request->monthlyRate;
        if ($request->has('numberOfUnits')) $clientData['numberOfUnits'] = $request->numberOfUnits;
        if ($request->has('pickUpDay')) $clientData['pickUpDay'] = $request->pickUpDay;
        if ($request->has('gracePeriod')) $clientData['gracePeriod'] = $request->gracePeriod;
        
        \Log::info('Client Update Data:', $clientData);
        if (!empty($clientData)) {
            $client->update($clientData);
            \Log::info('Client updated successfully');
        } else {
            \Log::info('No client data to update');
        }

        return response()->json([
            'status' => true,
            'message' => 'Client updated successfully',
            'data' => ['client' => $client->fresh(['user', 'route'])]
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $client = Client::where('user_id', $id)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Client not found'
            ], 404);
        }

        $client->user->delete(); // This will cascade delete the client record
        
        return response()->json([
            'status' => true,
            'message' => 'Client deleted successfully'
        ], 200);
    }

    public function deleteDocument(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'documentPath' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Document path is required'
            ], 401);
        }

        $organizationId = $request->user()->id;
        $client = Client::where('user_id', $id)
            ->where('organization_id', $organizationId)
            ->with('user')
            ->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Client not found'
            ], 404);
        }

        $documentPath = $request->documentPath;
        $documents = $client->user->documents ?? [];
        
        // Remove the document from the array
        $updatedDocuments = array_filter($documents, function($doc) use ($documentPath) {
            return $doc !== $documentPath;
        });
        
        // Update user documents
        $client->user->update(['documents' => array_values($updatedDocuments)]);
        
        // Delete physical file
        $filename = basename(parse_url($documentPath, PHP_URL_PATH));
        $filePath = storage_path('app/public/documents/' . $filename);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return response()->json([
            'status' => true,
            'message' => 'Document deleted successfully'
        ], 200);
    }

    public function toggleStatus(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $client = Client::where('user_id', $id)
            ->where('organization_id', $organizationId)
            ->with('user')
            ->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Client not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'isActive' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data'
            ], 401);
        }

        $client->user->update(['isActive' => $request->isActive]);

        return response()->json([
            'status' => true,
            'message' => $request->isActive ? 'Client activated successfully' : 'Client deactivated successfully',
            'data' => ['client' => $client->fresh(['user'])]
        ], 200);
    }

    public function search(Request $request)
    {
        \Log::info('=== CLIENT SEARCH START ===');
        \Log::info('Search params:', $request->all());
        
        $organizationId = $request->user()->id;
        
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|min:2'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }
        
        try {
            $query = Client::where('organization_id', $organizationId)
                ->with(['user', 'route']);
            
            if ($request->has('name') && !empty($request->name)) {
                $query->whereHas('user', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->name . '%');
                });
            }
            
            $clients = $query->get()->map(function($client) {
                return [
                    'id' => $client->user->id,
                    'name' => $client->user->name,
                    'email' => $client->user->email,
                    'phone' => $client->user->phone,
                    'address' => $client->user->adress,
                    'accountNumber' => $client->accountNumber,
                    'route' => $client->route
                ];
            });
            
            $response = [
                'status' => true,
                'data' => ['users' => $clients]
            ];
            
            \Log::info('🔍 Client search response:', $response);
            return response()->json($response, 200);
            
        } catch (\Exception $e) {
            \Log::error('Client search failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}