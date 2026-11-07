<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));
        $user->refresh();

        return response()->json(['data' => new UserResource($user), 'token' => $user->createToken('api')->plainTextToken, 'token_type' => 'Bearer'], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw new BusinessException('Неверный email или пароль', 401);
        }

        return response()->json(['data' => new UserResource($user), 'token' => $user->createToken('api')->plainTextToken, 'token_type' => 'Bearer']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
