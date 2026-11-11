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
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));
        $user->refresh();
        Log::channel('api')->info('auth.registered', ['user_id' => $user->id]);

        return response()->json(['data' => new UserResource($user), 'token' => $user->createToken('api')->plainTextToken, 'token_type' => 'Bearer'], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            Log::channel('api')->warning('auth.login_rejected');
            throw new BusinessException('Неверный email или пароль', 401);
        }

        Log::channel('api')->info('auth.logged_in', ['user_id' => $user->id]);

        return response()->json(['data' => new UserResource($user), 'token' => $user->createToken('api')->plainTextToken, 'token_type' => 'Bearer']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        Log::channel('api')->info('auth.logged_out', ['user_id' => $request->user()->id]);

        return response()->noContent();
    }
}
