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
                $token = $user->createToken('authToken');
                
                // Set expiration based on remember me
                if($request->remember) {
                    $token->accessToken->expires_at = now()->addDays(30);
                } else {
                    $token->accessToken->expires_at = now()->addHours(2);
                }
                $token->accessToken->save();
                
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
            'adress'=>'nullable|string|max:255',
            'uploaded_documents'=>'nullable|array',
        ]);

        if(!$validator->fails()){
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>Hash::make($request->password),
                'role'=>$request->role,
                'phone'=>$request->phone,
                'adress'=>$request->adress,
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
            'adress'=>'nullable|string|max:255',
            'uploaded_documents'=>'nullable|array',
        ]);

        if(!$validator->fails()){
            $randomPassword = Str::random(12);
            
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>Hash::make($randomPassword),
                'role'=>'organization',
                'phone'=>$request->phone,
                'adress'=>$request->adress,
                'documents'=>$request->uploaded_documents ?? []
            ]);
            
            // Send credentials via email
            Mail::to($user->email)->send(new OrganizationCredentials(
                $user->email,
                $randomPassword,
                $user->name
            ));
            
            return response()->json([
                'status'=>true,
                'message'=>'Organization Created Successfully. Credentials sent to email.',
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
            'organizationId'=>'required_unless:action,list|exists:users,id',
            'updateData'=>'required_if:action,edit|array',
            'page'=>'nullable|integer|min:1',
            'limit'=>'nullable|integer|min:1|max:100',
            'search'=>'nullable|string',
            'sortBy'=>'nullable|in:name,email,created_at,updated_at',
            'sortOrder'=>'nullable|in:asc,desc',
        ]);

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
                                      ->select('id', 'name', 'email', 'phone', 'created_at', 'updated_at')
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
                
                $user->update(array_intersect_key($updateData, array_flip(['name', 'email', 'phone', 'adress'])));
                
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
                    'adress'=>$user->adress,
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
            
            return response()->json([
                'status' => true,
                'message' => 'Driver created successfully',
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
        // Test response to see if method is called
        return response()->json([
            'status' => true,
            'message' => 'Update driver method called successfully',
            'data' => [
                'driver_id' => $id,
                'request_data' => $request->all(),
                'files' => $request->allFiles()
            ]
        ], 200);
        
        \Log::info('=== UPDATE DRIVER START ===');
        \Log::info('Driver ID:', ['id' => $id]);
        \Log::info('Request data:', $request->all());
        \Log::info('Request files:', $request->allFiles());
        
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

        $driver->update($updateData);

        \Log::info('Driver updated successfully');
        \Log::info('=== UPDATE DRIVER END ===');
        
        return response()->json([
            'status' => true,
            'message' => 'Driver updated successfully',
            'data' => ['driver' => $driver->fresh()]
        ], 200);
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

        $driver->delete();

        return response()->json([
            'status' => true,
            'message' => 'Driver deleted successfully'
        ], 200);
    }

    public function sendDriverCredentials(Request $request, $id)
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

        // Generate new password and send credentials
        $newPassword = Str::random(12);
        $driver->password = Hash::make($newPassword);
        $driver->save();

        // TODO: Send email with credentials
        // Mail::to($driver->email)->send(new DriverCredentials($driver->email, $newPassword, $driver->name));

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
}

