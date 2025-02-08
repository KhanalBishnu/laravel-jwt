<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
   
    public function register(Request $request)
    {
        $validatedData=$request->validate([
            'name'=>'required|string|max:255',
            'email'=>'required|email|unique:users|max:255',
            'password'=>'required|string|confirmed'
        ]);

        $user=User::create([
            'name'=>$validatedData['name'],
            'email'=>$validatedData['email'],
            'password'=>bcrypt($validatedData['password'])
        ]);
        $token=Auth::login($user);

        return $this->respondWithToken($token);
    }
    public function login(Request $request)
    {
        $credentials = request(['email', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

  
    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

   
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

 
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
}