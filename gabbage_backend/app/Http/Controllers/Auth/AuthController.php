<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrganizationCredentials;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    //
    public function Login(Request $request)
    {
        $validator=Validator($request->all(),[
            'email'=>'required|email|exists:users,email',
            'password'=>'required|string|min:6|max:20',
            'remember'=>'nullable|boolean',
        ]);

        if(!$validator->fails()){
            $credentials=$request->only('email','password');
            if(Auth::attempt($credentials)){
                $user=Auth::user();
                
                // Create token with proper expiration
                if($request->remember) {
                    $token = $user->createToken('authToken', ['*'], now()->addDays(30));
                } else {
                    $token = $user->createToken('authToken', ['*'], now()->addHours(24));
                }
                
                $tokenResult = $token->plainTextToken;
                return response()->json([
                    'status'=>true,
                    'message'=>'Login Successfully',
                    'data'=>[
                        'access_token'=>$tokenResult,
                        'token_type'=>'Bearer',
                        'user'=>$user
                    ]
                ],200);
            }else{
                return response()->json([
                    'status'=>false,
                    'error'=>'Invalid credentials',
                    'message'=>'Email or password is incorrect'
                ],401);
            }
    }else{
        return response()->json([
            'status'=>false,
            'error'=>'Validation failed',
            'message'=>'Invalid input data',
            'details'=>collect($validator->errors())->map(function($messages, $field) {
                return ['field' => $field, 'message' => $messages[0]];
            })->values()
        ],401);
    }
    }


    public function Logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status'=>true,
            'message'=>'Logout Successfully',
            'data'=>null
        ],200);
    }

    public function Register(Request $request)
    {
        $validator=Validator($request->all(),[
            'name'=>'required|string|max:255',
            'email'=>'required|email|unique:users,email',
            'password'=>'required|string|min:6|max:20',
            'role'=>'required|in:client,driver',
            'phone'=>'nullable|string|max:20',
            'address'=>'nullable|string|max:255',
            'uploaded_documents'=>'nullable|array',
        ]);

        if(!$validator->fails()){
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>Hash::make($request->password),
                'role'=>$request->role,
                'phone'=>$request->phone,
                'address'=>$request->address,
                'documents'=>$request->uploaded_documents ?? []
            ]);
            $tokenResult=$user->createToken('authToken')->plainTextToken;
            return response()->json([
                'status'=>true,
                'message'=>'Register Successfully',
                'data'=>[
                    'access_token'=>$tokenResult,
                    'token_type'=>'Bearer',
                    'user'=>$user
                ]
            ],200);
        }else{
            return response()->json([
                'status'=>false,
                'error'=>'Validation failed',
                'message'=>'Invalid input data',
                'details'=>collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ],401);
        }
    }

    public function CreateAdmin(Request $request)
    {
        $validator=Validator($request->all(),[
            'name'=>'required|string|max:255',
            'email'=>'required|email|unique:users,email',
            'password'=>'required|string|min:6|max:20',
        ]);

        if(!$validator->fails()){
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>Hash::make($request->password),
                'role'=>'admin'
            ]);
            return response()->json([
                'status'=>true,
                'message'=>'Admin Created Successfully',
                'data'=>['user'=>$user]
            ],200);
        }else{
            return response()->json([
                'status'=>false,
                'message'=>'Validation Error',
                'errors'=>$validator->errors()
            ],401);
        }
    }

    public function CreateOrganization(Request $request)
    {

        
        $validator=Validator($request->all(),[
            'name'=>'required|string|max:255',
            'email'=>'required|email|unique:users,email',
            'phone'=>'nullable|string|max:20',
            'address'=>'nullable|string|max:255',
            'uploaded_documents'=>'nullable|array',
        ]);

        if(!$validator->fails()){
            $randomPassword = Str::random(12);
            
            // Get processed documents from middleware
            $processedDocuments = $request->attributes->get('processed_documents', []);
            
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>Hash::make($randomPassword),
                'role'=>'organization',
                'phone'=>$request->phone,
                'address'=>$request->address,
                'documents'=>$processedDocuments,
                'isSent'=>false
            ]);
            

            
            return response()->json([
                'status'=>true,
                'message'=>'Organization Created Successfully.',
                'data'=>['user'=>$user]
            ],200);
        }else{
            \Log::error('CreateOrganization validation failed', $validator->errors()->toArray());
            return response()->json([
                'status'=>false,
                'message'=>'Validation Error',
                'errors'=>$validator->errors()
            ],422);
        }
    }

    public function SendCredentials(Request $request)
    {
        $validator=Validator($request->all(),[
            'organizationId'=>'required|exists:users,id',
        ]);

        if(!$validator->fails()){
            $user = User::find($request->organizationId);
            
            if($user->role !== 'organization') {
                return response()->json([
                    'status'=>false,
                    'message'=>'User is not an organization'
                ],400);
            }
            
            $newPassword = Str::random(12);
            $user->password = Hash::make($newPassword);
            $user->save();
            
            // Send new credentials via email
            Mail::to($user->email)->send(new OrganizationCredentials(
                $user->email,
                $newPassword,
                $user->name
            ));
            
            // Mark as sent
            $user->isSent = true;
            $user->save();
            
            return response()->json([
                'status'=>true,
                'message'=>'New credentials sent to organization email.',
                'data'=>null
            ],200);
        }else{
            return response()->json([
                'status'=>false,
                'message'=>'Validation Error',
                'errors'=>$validator->errors()
            ],401);
        }
    }

    public function ManageOrganization(Request $request)
    {
        $validator=Validator($request->all(),[
            'action'=>'required|in:edit,delete,list',
            'organizationId'=>'nullable|exists:users,id',
            'updateData'=>'required_if:action,edit|array',
            'page'=>'nullable|integer|min:1',
            'limit'=>'nullable|integer|min:1|max:100',
            'search'=>'nullable|string',
            'sortBy'=>'nullable|in:name,email,created_at,updated_at',
            'sortOrder'=>'nullable|in:asc,desc',
        ]);

        // Additional validation for organizationId when action is not 'list'
        if ($request->action !== 'list' && !$request->organizationId) {
            return response()->json([
                'status'=>false,
                'message'=>'Organization ID is required for this action',
                'errors'=>['organizationId' => ['Organization ID is required']]
            ],401);
        }

        if(!$validator->fails()){
            if($request->action === 'list') {
                $page = $request->page ?? 1;
                $limit = $request->limit ?? 10;
                $search = $request->search ?? '';
                $sortBy = $request->sortBy ?? 'created_at';
                $sortOrder = $request->sortOrder ?? 'desc';
                
                $query = User::where('role', 'organization');
                
                if($search) {
                    $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                }
                
                $total = $query->count();
                $organizations = $query->orderBy($sortBy, $sortOrder)
                                      ->skip(($page - 1) * $limit)
                                      ->take($limit)
                                      ->select('id', 'name', 'email', 'phone', 'address', 'isActive', 'isSent', 'documents', 'created_at', 'updated_at')
                                      ->get();
                
                return response()->json([
                    'status'=>true,
                    'data'=>[
                        'organizations'=>$organizations,
                        'total'=>$total,
                        'page'=>$page,
                        'limit'=>$limit
                    ]
                ],200);
            }
            
            $user = User::find($request->organizationId);
            
            if($user->role !== 'organization') {
                return response()->json([
                    'status'=>false,
                    'message'=>'User is not an organization'
                ],400);
            }
            
            if($request->action === 'edit') {
                $updateData = $request->updateData;
                
                // Validate email uniqueness if being updated
                if(isset($updateData['email']) && $updateData['email'] !== $user->email) {
                    if(User::where('email', $updateData['email'])->exists()) {
                        return response()->json([
                            'status'=>false,
                            'message'=>'Email already exists'
                        ],400);
                    }
                }
                
                $user->update(array_intersect_key($updateData, array_flip(['name', 'email', 'phone', 'address'])));
                
                return response()->json([
                    'status'=>true,
                    'message'=>'Organization updated successfully',
                    'data'=>['user'=>$user->fresh()]
                ],200);
                
            } elseif($request->action === 'delete') {
                $user->delete();
                
                return response()->json([
                    'status'=>true,
                    'message'=>'Organization deleted successfully',
                    'data'=>null
                ],200);
            }
        }else{
            return response()->json([
                'status'=>false,
                'message'=>'Validation Error',
                'errors'=>$validator->errors()
            ],401);
        }
    }

    public function ListAdmins(Request $request)
    {
        $admins = User::where('role', 'admin')
                     ->select('id', 'name', 'email', 'phone', 'created_at')
                     ->orderBy('created_at', 'desc')
                     ->get();
        
        return response()->json([
            'status'=>true,
            'data'=>[
                'admins'=>$admins
            ]
        ],200);
    }

    public function GetOrganization(Request $request, $id)
    {
        $user = User::find($id);
        
        if(!$user || $user->role !== 'organization') {
            return response()->json([
                'status'=>false,
                'error'=>'Not found',
                'message'=>'Organization not found'
            ],404);
        }
        
        return response()->json([
            'status'=>true,
            'data'=>[
                'organization'=>[
                    'id'=>$user->id,
                    'name'=>$user->name,
                    'email'=>$user->email,
                    'phone'=>$user->phone,
                    'address'=>$user->address,
                    'role'=>$user->role,
                    'isActive'=>true,
                    'isSent'=>true,
                    'createdAt'=>$user->created_at,
                    'updatedAt'=>$user->updated_at,
                    'documents'=>$user->documents ?? []
                ]
            ]
        ],200);
    }

    public function CreateAdminByAdmin(Request $request)
    {
        $validator=Validator($request->all(),[
            'name'=>'required|string|max:255',
            'email'=>'required|email|unique:users,email',
            'password'=>'required|string|min:6|max:20',
            'phone'=>'nullable|string|max:20',
        ]);

        if(!$validator->fails()){
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>Hash::make($request->password),
                'role'=>'admin',
                'phone'=>$request->phone
            ]);
            return response()->json([
                'status'=>true,
                'message'=>'Admin created successfully',
                'data'=>['admin'=>$user]
            ],200);
        }else{
            return response()->json([
                'status'=>false,
                'message'=>'Validation Error',
                'errors'=>$validator->errors()
            ],401);
        }
    }

    // Organization-specific methods
    public function getOrganizationStats(Request $request)
    {
        $organizationId = $request->user()->id;
        
        $totalDrivers = User::where('role', 'driver')->where('organization_id', $organizationId)->count();
        $totalClients = User::where('role', 'client')->where('organization_id', $organizationId)->count();
        $activeDrivers = User::where('role', 'driver')->where('organization_id', $organizationId)->where('isActive', true)->count();
        $activeClients = User::where('role', 'client')->where('organization_id', $organizationId)->where('isActive', true)->count();
        
        return response()->json([
            'status'=>true,
            'data'=>[
                'totalDrivers'=>$totalDrivers,
                'totalClients'=>$totalClients,
                'totalRoutes'=>0,
                'activeDrivers'=>$activeDrivers,
                'activeClients'=>$activeClients
            ]
        ],200);
    }

    public function listOrganizationDrivers(Request $request)
    {
        $organizationId = $request->user()->id;
        $drivers = User::where('role', 'driver')->where('organization_id', $organizationId)->get();
        
        return response()->json([
            'status'=>true,
            'data'=>['users'=>$drivers]
        ],200);
    }

    public function listOrganizationClients(Request $request)
    {
        $organizationId = $request->user()->id;
        $clients = User::where('role', 'client')->where('organization_id', $organizationId)->get();
        
        return response()->json([
            'status'=>true,
            'data'=>['users'=>$clients]
        ],200);
    }

    public function createDriver(Request $request)
    {
        \Log::info('=== CREATE DRIVER START ===');
        \Log::info('Request data:', $request->all());
        \Log::info('Request files:', $request->allFiles());
        \Log::info('User ID:', ['id' => $request->user()->id]);
        \Log::info('Content-Type:', ['header' => $request->header('Content-Type')]);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'uploaded_documents' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        \Log::info('Validation passed, creating user...');
        
        try {
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make('password123'),
                'role' => 'driver',
                'phone' => $request->phone,
                'documents' => $request->uploaded_documents ?? [],
                'organization_id' => $request->user()->id
            ];
            
            \Log::info('User data to create:', $userData);
            
            $user = User::create($userData);
            
            \Log::info('User created successfully:', ['id' => $user->id]);
            
            // Send credentials via email
            try {
                Mail::to($user->email)->send(new \App\Mail\DriverCredentials($user->email, 'password123', $user->name));
                \Log::info('Driver credentials email sent successfully');
            } catch (\Exception $e) {
                \Log::error('Failed to send driver credentials email:', ['error' => $e->getMessage()]);
            }
            
            return response()->json([
                'status' => true,
                'message' => 'Driver created successfully. Credentials sent to email.',
                'data' => ['driver' => $user]
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error creating driver:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'error' => 'Database error',
                'message' => 'Failed to create driver: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDriverDetails(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $driver = User::where('id', $id)
            ->where('role', 'driver')
            ->where('organization_id', $organizationId)
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Driver not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => ['driver' => $driver]
        ], 200);
    }

    public function updateDriver(Request $request, $id)
    {
        \Log::info('=== UPDATE DRIVER START ===');
        \Log::info('Driver ID:', ['id' => $id]);
        \Log::info('Request data:', $request->all());
        
        $organizationId = $request->user()->id;
        $driver = User::where('id', $id)
            ->where('role', 'driver')
            ->where('organization_id', $organizationId)
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Driver not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
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

        // Handle document updates
        $currentDocuments = $driver->documents ?? [];
        $newDocuments = $request->uploaded_documents ?? [];
        $allDocuments = !empty($newDocuments) ? array_merge($currentDocuments, $newDocuments) : $currentDocuments;

        // Update driver data
        $updateData = [];
        if ($request->has('name')) $updateData['name'] = $request->name;
        if ($request->has('email')) $updateData['email'] = $request->email;
        if ($request->has('phone')) $updateData['phone'] = $request->phone;
        if ($request->has('isActive')) $updateData['isActive'] = filter_var($request->isActive, FILTER_VALIDATE_BOOLEAN);
        $updateData['documents'] = $allDocuments;

        \Log::info('Update data:', $updateData);
        
        try {
            $result = $driver->update($updateData);
            \Log::info('Database update result:', ['success' => $result]);
            
            $freshDriver = $driver->fresh();
            \Log::info('Fresh driver data:', ['driver' => $freshDriver->toArray()]);
            
            return response()->json([
                'status' => true,
                'message' => 'Driver updated successfully',
                'data' => ['driver' => $freshDriver]
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error updating driver:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'error' => 'Update failed',
                'message' => 'Failed to update driver: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteDriver(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $driver = User::where('id', $id)
            ->where('role', 'driver')
            ->where('organization_id', $organizationId)
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Driver not found'
            ], 404);
        }

        // Check if driver has allocated bags
        $allocation = \App\Models\DriverBagsAllocation::where('driver_id', $id)->first();
        if ($allocation && $allocation->available_bags > 0) {
            return response()->json([
                'status' => false,
                'error' => 'Cannot delete driver',
                'message' => 'Driver has pending allocated bags. Please clear all bags before deletion.',
                'data' => ['pending_bags' => $allocation->available_bags]
            ], 400);
        }

        $driver->delete();
        
        // Clean up allocation record if exists
        if ($allocation) {
            $allocation->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Driver deleted successfully'
        ], 200);
    }

    public function sendDriverCredentials(Request $request, $id)
    {
        \Log::info('=== SEND DRIVER CREDENTIALS START ===');
        \Log::info('Driver ID:', ['id' => $id]);
        \Log::info('Organization ID:', ['org_id' => $request->user()->id]);
        
        $organizationId = $request->user()->id;
        
        \Log::info('Searching for driver...');
        $driver = User::where('id', $id)
            ->where('role', 'driver')
            ->where('organization_id', $organizationId)
            ->first();

        if (!$driver) {
            \Log::error('Driver not found', ['driver_id' => $id, 'org_id' => $organizationId]);
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Driver not found'
            ], 404);
        }

        \Log::info('Driver found:', ['driver_email' => $driver->email, 'driver_name' => $driver->name]);
        
        // Generate new password and send credentials
        $newPassword = Str::random(12);
        \Log::info('Generated new password for driver');
        
        $driver->password = Hash::make($newPassword);
        $driver->save();
        \Log::info('Password updated in database');

        // Send email with credentials
        try {
            Mail::to($driver->email)->send(new \App\Mail\DriverCredentials($driver->email, $newPassword, $driver->name));
            \Log::info('Email sent successfully to driver');
        } catch (\Exception $e) {
            \Log::error('Failed to send email:', ['error' => $e->getMessage()]);
        }

        \Log::info('=== SEND DRIVER CREDENTIALS END ===');
        return response()->json([
            'status' => true,
            'message' => 'Driver credentials sent successfully'
        ], 200);
    }

    public function deleteDriverDocument(Request $request, $id)
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
        $driver = User::where('id', $id)
            ->where('role', 'driver')
            ->where('organization_id', $organizationId)
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Driver not found'
            ], 404);
        }

        $documentPath = $request->documentPath;
        $documents = $driver->documents ?? [];
        
        // Remove the document from the array
        $updatedDocuments = array_filter($documents, function($doc) use ($documentPath) {
            return $doc !== $documentPath;
        });
        
        // Update driver documents
        $driver->update(['documents' => array_values($updatedDocuments)]);
        
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

    // Admin Dashboard Methods
    public function getAdminDashboardStats(Request $request)
    {
        $totalOrganizations = User::where('role', 'organization')->count();
        $activeOrganizations = User::where('role', 'organization')->where('isActive', true)->count();
        $totalDrivers = User::where('role', 'driver')->count();
        $totalClients = User::where('role', 'client')->count();
        $totalAdmins = User::where('role', 'admin')->count();
        
        return response()->json([
            'status' => true,
            'data' => [
                'totalOrganizations' => $totalOrganizations,
                'activeOrganizations' => $activeOrganizations,
                'totalDrivers' => $totalDrivers,
                'totalClients' => $totalClients,
                'totalAdmins' => $totalAdmins
            ]
        ], 200);
    }

    public function listOrganizations(Request $request)
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 10;
        $search = $request->search ?? '';
        $sortBy = $request->sortBy ?? 'created_at';
        $sortOrder = $request->sortOrder ?? 'desc';
        
        $query = User::where('role', 'organization');
        
        if($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        $total = $query->count();
        $organizations = $query->orderBy($sortBy, $sortOrder)
                              ->skip(($page - 1) * $limit)
                              ->take($limit)
                              ->select('id', 'name', 'email', 'phone', 'address', 'isActive', 'isSent', 'created_at', 'updated_at')
                              ->get();
        
        // Transform data to match frontend expectations
        $organizations = $organizations->map(function($org) {
            return [
                'id' => $org->id,
                'name' => $org->name,
                'email' => $org->email,
                'phone' => $org->phone,
                'address' => $org->address,
                'isActive' => (bool)$org->isActive,
                'isSent' => (bool)$org->isSent,
                'createdAt' => $org->created_at->toISOString(),
                'updatedAt' => $org->updated_at->toISOString()
            ];
        });
        
        return response()->json([
            'status' => true,
            'data' => [
                'organizations' => $organizations,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]
        ], 200);
    }

    public function getActivityLogs(Request $request)
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        $action = $request->action;
        $userId = $request->user_id;
        
        $query = \App\Models\ActivityLog::with('user:id,name,email,role');
        
        if($action) {
            $query->where('action', $action);
        }
        
        if($userId) {
            $query->where('user_id', $userId);
        }
        
        $total = $query->count();
        $logs = $query->orderBy('created_at', 'desc')
                      ->skip(($page - 1) * $limit)
                      ->take($limit)
                      ->get();
        
        return response()->json([
            'status' => true,
            'data' => [
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]
        ], 200);
    }

    public function getSystemStats(Request $request)
    {
        $totalBags = \App\Models\Bag::sum('total_bags');
        $allocatedBags = \App\Models\Bag::sum('allocated_bags');
        $availableBags = \App\Models\Bag::sum('available_bags');
        $totalBagIssues = \App\Models\BagIssue::where('is_verified', true)->count();
        $totalRoutes = \App\Models\Route::count();
        
        return response()->json([
            'status' => true,
            'data' => [
                'totalBags' => $totalBags,
                'allocatedBags' => $allocatedBags,
                'availableBags' => $availableBags,
                'totalBagIssues' => $totalBagIssues,
                'totalRoutes' => $totalRoutes
            ]
        ], 200);
    }

    public function deactivateOrganization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organizationId' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 401);
        }

        $user = User::find($request->organizationId);
        
        if ($user->role !== 'organization') {
            return response()->json([
                'status' => false,
                'message' => 'User is not an organization'
            ], 400);
        }
        
        $user->isActive = !$user->isActive;
        $user->save();
        
        $action = $user->isActive ? 'activated' : 'deactivated';
        
        return response()->json([
            'status' => true,
            'message' => "Organization {$action} successfully",
            'data' => ['organization' => $user]
        ], 200);
    }

    // Driver-specific methods
    public function getOrganizationDrivers(Request $request)
    {
        $organizationId = $request->user()->organization_id;
        $drivers = User::where('role', 'driver')
            ->where('organization_id', $organizationId)
            ->where('isActive', true)
            ->select('id', 'name', 'email', 'phone')
            ->get();
        
        return response()->json([
            'status' => true,
            'data' => $drivers
        ], 200);
    }
}

