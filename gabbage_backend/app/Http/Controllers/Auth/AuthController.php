<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
            if(auth()->attempt($credentials)){
                $user=auth()->user();
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
        auth()->user()->tokens()->delete();
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
            'role'=>'required|in:client,organization,driver',
            'phone'=>'nullable|string|max:20',
            'adress'=>'nullable|string|max:255',
        ]);

        if(!$validator->fails()){
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>Hash::make($request->password),
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
}

