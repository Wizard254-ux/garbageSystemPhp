<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    //
    public function Login(Request $request)
    {
        $validator=Validator($request->all(),[
            'email'=>'required|email|exists:users,email',
            'password'=>'required|string|min:6|max:20',
        ]);

        if(!$validator->fails()){
            $credentials=$request->only('email','password');
            if(Auth::attempt($credentials)){
                $user=Auth::user();
                $tokenResult=$user->createToken('authToken')->plainTextToken;
                $request->session()->regenerate();
                return response()->json([
                    'status'=>true,
                    'message'=>'Login Successfully',
                    'access_token'=>$tokenResult,
                    'token_type'=>'Bearer',
                    'user'=>$user
                ],200);
            }else{
                return response()->json([
                    'status'=>false,
                    'message'=>'Invalid Credentials' 
                ],401);
            }
    }else{
        return response()->json([
            'status'=>false,
            'message'=>'Validation Error',
            'errors'=>$validator->errors()
        ],401);
    }
    }


    public function Logout(Request $request)
    {
        Auth::user()->tokens()->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json([
            'status'=>true,
            'message'=>'Logout Successfully'
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
                'access_token'=>$tokenResult,
                'token_type'=>'Bearer',
                'user'=>$user
            ],200);
        }else{
            return response()->json([
                'status'=>false,
                'message'=>'Validation Error',
                'errors'=>$validator->errors()
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
                'user'=>$user
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
            'password'=>'required|string|min:6|max:20',
            'phone'=>'nullable|string|max:20',
            'adress'=>'nullable|string|max:255',
            'uploaded_documents'=>'nullable|array',
        ]);

        if(!$validator->fails()){
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>Hash::make($request->password),
                'role'=>'organization',
                'phone'=>$request->phone,
                'adress'=>$request->adress,
                'documents'=>$request->uploaded_documents ?? []
            ]);
            return response()->json([
                'status'=>true,
                'message'=>'Organization Created Successfully',
                'user'=>$user
            ],200);
        }else{
            return response()->json([
                'status'=>false,
                'message'=>'Validation Error',
                'errors'=>$validator->errors()
            ],401);
        }
    }
}

