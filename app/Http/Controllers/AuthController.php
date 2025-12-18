<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{

    public function createAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|',
            'password' => 'required|string|min:8',
        ]);

        $validated['password'] = bcrypt($validated['password']);

        $admin = User::create($validated);

        return response()->json([
            'message' => 'Admin created successfully',
            'user' => $admin
        ], 201);
    }


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|',
            'password' => 'required|string|min:8',
        ]);

        if (!auth()->attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = auth()->user();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'user' => $user,
            'token' => $token
        ], 200);
    }


    public function logout(Request $request)
    {
        $user = auth()->user();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }


    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!\Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 403);
        }

        $user->password = bcrypt($validated['new_password']);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully',
        ], 200);
    }
}
