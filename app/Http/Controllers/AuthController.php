<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request) {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'password' => ['required', 'string', 'min:8', 'confirmed', Password::defaults()],
        ]);

        $user = \App\Models\User::create($validatedData);

        return $this->tokenResponse($user, Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse {
        $validatedData = $request->validate([
            'email' => 'required|string|lowercase|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validatedData)) {
            throw ValidationException::withMessages([
                'email' => 'auth.failed',
            ])->status(Response::HTTP_UNAUTHORIZED);
        }

        return $this->tokenResponse(Auth::user());
    }

    public function logout(Request $request): Response {
        // Revoke only the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Mark the user as authenticated for this request (so UserResource shoes their email)
     * and issue a new token for the user.
     */
    private function tokenResponse(User $user, int $status = Response::HTTP_OK): JsonResponse {
        Auth::setUser($user);

        return response()->json([
            'data' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
        ], $status);
    }
}
