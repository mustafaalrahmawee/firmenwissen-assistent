<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
            'token' => $this->createDeviceToken($user),
        ]);
    }

    /**
     * Create an account with a new workspace named after the company and log it in on this device.
     *
     * Workspace, account and token are created together or not at all. If another registration took
     * the same address in the meantime, the unique index stops this one with the same answer
     * as a taken address.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $input = $request->validated();

        try {
            $token = DB::transaction(function () use ($input): string {
                $user = new User([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                ]);
                $user->tenant()->associate(Tenant::create(['name' => $input['company']]));
                $user->save();

                return $this->createDeviceToken($user);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => RegisterRequest::EMAIL_TAKEN]);
        }

        return JsonResource::make([
            'token' => $token,
        ])->response()->setStatusCode(201);
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

    /**
     * Issue a token for this device that expires after the configured lifetime.
     */
    private function createDeviceToken(User $user): string
    {
        return $user->createToken('web', ['*'], now()->addMinutes(config('sanctum.expiration')))->plainTextToken;
    }
}
