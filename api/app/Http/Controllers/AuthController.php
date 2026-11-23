<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Bcrypt hash (cost 12) of a random value no one knows. An unknown email address
     * is checked against it, so both cases take the same path and about as long.
     */
    private const string DUMMY_PASSWORD_HASH = '$2y$12$Wd0AQYVFODC/NYu2uI4sQOXlULEZfz7NSuzpGtLWj5mzSTeeTNm8K';

    /**
     * Log the user in and issue a token for this device.
     *
     * An unknown email address and a wrong password give the same answer.
     */
    public function login(LoginRequest $request): JsonResource
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        $passwordMatches = Hash::check($credentials['password'], $user->password ?? self::DUMMY_PASSWORD_HASH);

        if ($user === null || $passwordMatches === false) {
            throw ValidationException::withMessages([
                'credentials' => 'Die E-Mail-Adresse oder das Passwort ist falsch.',
            ]);
        }

        return JsonResource::make([
            'token' => $user->createToken('web', ['*'], now()->addMinutes(config('sanctum.expiration')))->plainTextToken,
        ]);
    }

    /**
     * End the login on this device by deleting the token of this request.
     * Tokens of other devices stay valid.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Return the user the token belongs to.
     */
    public function user(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
