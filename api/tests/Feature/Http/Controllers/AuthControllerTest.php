<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

const LOGIN_URL = '/api/v1/login';
const USER_URL = '/api/v1/user';
const LOGOUT_URL = '/api/v1/logout';
const REGISTER_URL = '/api/v1/register';
const EMAIL_TAKEN = 'Zu dieser E-Mail-Adresse gibt es bereits ein Konto.';
const WRONG_CREDENTIALS = 'Die E-Mail-Adresse oder das Passwort ist falsch.';
const PASSWORD_RULE = 'Das Passwort muss mindestens 8 Zeichen lang sein, Buchstaben und Ziffern enthalten und mit einem Großbuchstaben beginnen.';

/**
 * Resolve the user the sanctum guard sees for a bearer token, as a protected route would.
 */
function userForToken(string $token): ?User
{
    Auth::forgetGuards();

    return Auth::guard('sanctum')
        ->setRequest(Request::create('/', 'GET', server: ['HTTP_AUTHORIZATION' => "Bearer {$token}"]))
        ->user();
}

/**
 * A valid registration of Carla Brandt, with single fields replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registration(array $overrides = []): array
{
    return [
        'name' => 'Carla Brandt',
        'company' => 'Elbwerk Software GmbH',
        'email' => 'carla.brandt@elbwerk.test',
        'password' => 'Elbwerk2026',
        'password_confirmation' => 'Elbwerk2026',
        ...$overrides,
    ];
}

describe('POST /api/v1/login', function () {
    it('returns a token for this device when email address and password are correct', function () {
        $user = User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);

        $response = $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);

        $response->assertOk()->assertExactJsonStructure(['data' => ['token']]);
        expect(PersonalAccessToken::findToken($response->json('data.token'))->tokenable->is($user))->toBeTrue()
            ->and(userForToken($response->json('data.token'))?->is($user))->toBeTrue();
    });

    it('keeps the tokens of other devices when logging in again', function () {
        $user = User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        $otherDevice = $user->createToken('phone')->accessToken;

        $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026'])->assertOk();

        $this->assertModelExists($otherDevice);
        expect($user->tokens()->count())->toBe(2);
    });

    it('returns 422 with the same message for an unknown email address and a wrong password', function (string $email, string $password) {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);

        $response = $this->postJson(LOGIN_URL, ['email' => $email, 'password' => $password]);

        $response->assertUnprocessable()->assertExactJsonStructure(['message', 'errors' => ['credentials']]);
        expect($response->json('errors.credentials'))->toBe([WRONG_CREDENTIALS]);
        $this->assertDatabaseEmpty('personal_access_tokens');
    })->with([
        'wrong password' => ['jana.petrov@nordlicht-it.test', 'Nordlicht2027'],
        'unknown email address' => ['tobias.kern@nordlicht-it.test', 'Nordlicht2026'],
        'wrong password with null byte' => ['jana.petrov@nordlicht-it.test', "Nordlicht\x002026"],
        'unknown email address with null byte' => ['tobias.kern@nordlicht-it.test', "Nordlicht\x002026"],
    ]);

    it('returns 422 with a field error for missing or malformed input', function (array $input, string $field, string $message) {
        $response = $this->postJson(LOGIN_URL, $input);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseEmpty('personal_access_tokens');
    })->with([
        'missing email address' => [['password' => 'Nordlicht2026'], 'email', 'Bitte gib deine E-Mail-Adresse ein.'],
        'malformed email address' => [['email' => 'jana.petrov', 'password' => 'Nordlicht2026'], 'email', 'Bitte gib eine gültige E-Mail-Adresse ein.'],
        'missing password' => [['email' => 'jana.petrov@nordlicht-it.test'], 'password', 'Bitte gib dein Passwort ein.'],
        'email address as a list' => [['email' => [], 'password' => 'Nordlicht2026'], 'email', 'Bitte gib deine E-Mail-Adresse ein.'],
        'email address as a list with values' => [['email' => ['jana.petrov@nordlicht-it.test'], 'password' => 'Nordlicht2026'], 'email', 'Bitte gib eine gültige E-Mail-Adresse ein.'],
    ]);

    it('returns 422 naming the password rule for a password that breaks it', function (string $password) {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => $password]);

        $response = $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => $password]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['password' => PASSWORD_RULE]);
        expect($response->json('errors.password'))->toBe([PASSWORD_RULE]);
        $this->assertDatabaseEmpty('personal_access_tokens');
    })->with([
        'too short' => 'Nord26',
        'without digit' => 'Nordlicht',
        'lowercase first letter' => 'nordlicht2026',
        'without letter' => '12345678',
    ]);

    it('returns 429 after five login attempts within a minute for the same email address', function () {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        foreach (range(1, 5) as $attempt) {
            $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Falsch2026'])->assertUnprocessable();
        }

        $response = $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);

        $response->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseEmpty('personal_access_tokens');
    });

    it('counts successful logins and requests with an empty field towards the limit', function () {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        foreach (range(1, 3) as $attempt) {
            $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026'])->assertOk();
        }
        foreach (range(1, 2) as $attempt) {
            $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => ''])->assertUnprocessable();
        }

        $response = $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);

        $response->assertTooManyRequests();
        $this->assertDatabaseCount('personal_access_tokens', 3);
    });

    it('does not limit the same email address from another IP address', function () {
        $user = User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        foreach (range(1, 5) as $attempt) {
            $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Falsch2026'])->assertUnprocessable();
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);

        $response->assertOk();
        expect($user->tokens()->count())->toBe(1);
    });

    it('returns 429 for a spelling of the same email address with accents or capitals after five attempts', function (string $spelling) {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        foreach (range(1, 5) as $attempt) {
            $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Falsch2026'])->assertUnprocessable();
        }

        $response = $this->postJson(LOGIN_URL, ['email' => $spelling, 'password' => 'Falsch2026']);

        $response->assertTooManyRequests();
    })->with([
        'accent' => 'jána.petrov@nordlicht-it.test',
        'umlaut' => 'jana.petröv@nordlicht-it.test',
        'capitals' => 'Jana.Petrov@nordlicht-it.test',
    ]);

    it('does not limit another email address after five attempts for one address', function () {
        $tobias = User::factory()->create(['email' => 'tobias.kern@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        foreach (range(1, 5) as $attempt) {
            $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Falsch2026']);
        }

        $response = $this->postJson(LOGIN_URL, ['email' => 'tobias.kern@nordlicht-it.test', 'password' => 'Nordlicht2026']);

        $response->assertOk();
        expect($tobias->tokens()->count())->toBe(1);
    });

    it('accepts login attempts again once the minute is over', function () {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        foreach (range(1, 5) as $attempt) {
            $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Falsch2026']);
        }

        $this->travel(61)->seconds();
        $response = $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);

        $response->assertOk();
    });

    it('issues a token that expires after seven days', function () {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        $token = $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026'])->json('data.token');

        $this->travel(7)->days();
        $this->travel(-1)->minutes();
        $stillValid = userForToken($token);
        $this->travel(2)->minutes();
        $expired = userForToken($token);

        expect($stillValid)->not->toBeNull()
            ->and($expired)->toBeNull();
    });

    it('keeps an expired token invalid when the lifetime is raised later', function () {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        $token = $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026'])->json('data.token');

        $this->travel(8)->days();
        config(['sanctum.expiration' => 60 * 24 * 14]);

        expect(userForToken($token))->toBeNull();
    });
});

describe('GET /api/v1/user', function () {
    it('returns id, name, email and the workspace name of the user the token belongs to', function () {
        $nordlicht = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
        $jana = User::factory()->for($nordlicht)->create(['name' => 'Jana Petrov', 'email' => 'jana.petrov@nordlicht-it.test']);
        User::factory()->create(['name' => 'Mehmet Aydin', 'email' => 'mehmet.aydin@hafenkante.test']);
        $token = $jana->createToken('web')->plainTextToken;

        $response = $this->withToken($token)->getJson(USER_URL);

        $response->assertOk()->assertExactJson([
            'data' => [
                'id' => $jana->id,
                'name' => 'Jana Petrov',
                'email' => 'jana.petrov@nordlicht-it.test',
                'tenant' => ['name' => 'Nordlicht IT GmbH'],
            ],
        ]);
    });

    it('returns the same workspace for two users of one firm and only its own workspace for another firm', function () {
        $nordlicht = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
        $hafenkante = Tenant::factory()->create(['name' => 'Hafenkante Systemhaus GmbH']);
        $jana = User::factory()->for($nordlicht)->create();
        $tobias = User::factory()->for($nordlicht)->create();
        $mehmet = User::factory()->for($hafenkante)->create();

        $workspaceOf = function (User $user): ?string {
            Auth::forgetGuards();

            return $this->withToken($user->createToken('web')->plainTextToken)
                ->getJson(USER_URL)
                ->assertOk()
                ->json('data.tenant.name');
        };

        expect($workspaceOf($jana))->toBe('Nordlicht IT GmbH')
            ->and($workspaceOf($tobias))->toBe('Nordlicht IT GmbH')
            ->and($workspaceOf($mehmet))->toBe('Hafenkante Systemhaus GmbH');
    });

    it('returns the same workspace on every device of the user', function () {
        $jana = User::factory()->for(Tenant::factory()->state(['name' => 'Nordlicht IT GmbH']))->create();
        $laptopToken = $jana->createToken('laptop')->plainTextToken;
        $phoneToken = $jana->createToken('phone')->plainTextToken;

        $this->withToken($laptopToken)->getJson(USER_URL)->assertJsonPath('data.tenant.name', 'Nordlicht IT GmbH');
        Auth::forgetGuards();
        $this->withToken($phoneToken)->getJson(USER_URL)->assertJsonPath('data.tenant.name', 'Nordlicht IT GmbH');
    });

    it('ignores a workspace that the request names in the query, the body or a header', function (string $place) {
        $hafenkante = Tenant::factory()->create(['name' => 'Hafenkante Systemhaus GmbH']);
        $nordlicht = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
        $mehmet = User::factory()->for($hafenkante)->create(['name' => 'Mehmet Aydin', 'email' => 'mehmet.aydin@hafenkante.test']);
        $request = $this->withToken($mehmet->createToken('web')->plainTextToken);

        $response = match ($place) {
            'query' => $request->getJson(USER_URL.'?tenant_id='.$nordlicht->id),
            'body' => $request->json('GET', USER_URL, ['tenant_id' => $nordlicht->id]),
            'header' => $request->withHeader('X-Tenant-Id', $nordlicht->id)->getJson(USER_URL),
        };

        $response->assertOk()->assertExactJson([
            'data' => [
                'id' => $mehmet->id,
                'name' => 'Mehmet Aydin',
                'email' => 'mehmet.aydin@hafenkante.test',
                'tenant' => ['name' => 'Hafenkante Systemhaus GmbH'],
            ],
        ]);
    })->with(['query', 'body', 'header']);

    it('returns 401 without a token', function () {
        $response = $this->getJson(USER_URL);

        $response->assertUnauthorized();
    });

    it('returns 401 for a token that has expired', function () {
        User::factory()->create(['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026']);
        $token = $this->postJson(LOGIN_URL, ['email' => 'jana.petrov@nordlicht-it.test', 'password' => 'Nordlicht2026'])->json('data.token');
        $this->travel(7)->days();
        $this->travel(1)->minutes();

        $response = $this->withToken($token)->getJson(USER_URL);

        $response->assertUnauthorized();
    });

    it('returns 401 for a token that no longer exists', function () {
        $jana = User::factory()->create();
        $token = $jana->createToken('web')->plainTextToken;
        $jana->tokens()->delete();

        $response = $this->withToken($token)->getJson(USER_URL);

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/logout', function () {
    it('returns 204 and makes the token of this device unusable', function () {
        $jana = User::factory()->create();
        $token = $jana->createToken('laptop')->plainTextToken;

        $response = $this->withToken($token)->postJson(LOGOUT_URL);

        $response->assertNoContent();
        expect(userForToken($token))->toBeNull();
    });

    it('keeps the tokens of other devices', function () {
        $jana = User::factory()->create();
        $laptop = $jana->createToken('laptop')->plainTextToken;
        $phone = $jana->createToken('phone')->plainTextToken;

        $this->withToken($laptop)->postJson(LOGOUT_URL)->assertNoContent();

        expect(userForToken($phone)?->is($jana))->toBeTrue()
            ->and($jana->tokens()->count())->toBe(1);
    });

    it('returns 401 without a token', function () {
        $response = $this->postJson(LOGOUT_URL);

        $response->assertUnauthorized();
    });

    it('returns 401 for a token that was already logged out', function () {
        $jana = User::factory()->create();
        $token = $jana->createToken('laptop')->plainTextToken;
        $jana->tokens()->delete();

        $response = $this->withToken($token)->postJson(LOGOUT_URL);

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/register', function () {
    it('creates an account in a new workspace named after the company and returns a token for this device', function () {
        $response = $this->postJson(REGISTER_URL, registration());

        $response->assertCreated()->assertExactJsonStructure(['data' => ['token']]);
        $user = User::sole();
        expect($user->only('name', 'email'))->toBe(['name' => 'Carla Brandt', 'email' => 'carla.brandt@elbwerk.test'])
            ->and(Hash::check('Elbwerk2026', $user->password))->toBeTrue()
            ->and($user->tenant->name)->toBe('Elbwerk Software GmbH')
            ->and(userForToken($response->json('data.token'))?->is($user))->toBeTrue();
    });

    it('shows the new workspace and nothing else to the new account', function () {
        $token = $this->postJson(REGISTER_URL, registration())->json('data.token');

        $response = $this->withToken($token)->getJson(USER_URL);

        $response->assertOk()->assertExactJson(['data' => [
            'id' => User::sole()->id,
            'name' => 'Carla Brandt',
            'email' => 'carla.brandt@elbwerk.test',
            'tenant' => ['name' => 'Elbwerk Software GmbH'],
        ]]);
    });

    it('lets the new account log in with the same data on another device', function () {
        $this->postJson(REGISTER_URL, registration())->assertCreated();

        $response = $this->postJson(LOGIN_URL, ['email' => 'carla.brandt@elbwerk.test', 'password' => 'Elbwerk2026']);

        $response->assertOk();
        expect(User::sole()->tokens()->count())->toBe(2);
    });

    it('issues a token that expires after seven days', function () {
        $token = $this->postJson(REGISTER_URL, registration())->json('data.token');

        $this->travel(7)->days();
        $this->travel(-1)->minutes();
        $stillValid = userForToken($token);
        $this->travel(2)->minutes();
        $expired = userForToken($token);

        expect($stillValid)->not->toBeNull()
            ->and($expired)->toBeNull();
    });

    it('never puts the new account into an existing workspace, even when the request names one', function () {
        $nordlicht = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
        User::factory()->for($nordlicht)->create(['email' => 'jana.petrov@nordlicht-it.test']);

        $this->postJson(REGISTER_URL, registration([
            'tenant_id' => $nordlicht->id,
            'tenant' => ['id' => $nordlicht->id],
        ]))->assertCreated();

        $carla = User::firstWhere('email', 'carla.brandt@elbwerk.test');
        expect($carla->tenant_id)->not->toBe($nordlicht->id)
            ->and($carla->tenant->name)->toBe('Elbwerk Software GmbH')
            ->and(Tenant::count())->toBe(2);
    });

    it('gives a second person of the same company a workspace of their own', function () {
        $this->postJson(REGISTER_URL, registration())->assertCreated();

        $this->postJson(REGISTER_URL, registration(['name' => 'Ole Brandt', 'email' => 'ole.brandt@elbwerk.test']))->assertCreated();

        $carla = User::firstWhere('email', 'carla.brandt@elbwerk.test');
        $ole = User::firstWhere('email', 'ole.brandt@elbwerk.test');
        expect($ole->tenant_id)->not->toBe($carla->tenant_id)
            ->and($ole->tenant->name)->toBe($carla->tenant->name);
    });

    it('returns 422 for a taken email address in any spelling of capitals and leaves the existing account as it was', function (string $email) {
        $carla = User::factory()->create(['name' => 'Carla Brandt', 'email' => 'carla.brandt@elbwerk.test', 'password' => 'Elbwerk2026']);
        $passwordHash = $carla->password;

        $response = $this->postJson(REGISTER_URL, registration(['name' => 'Mehmet Aydin', 'email' => $email, 'password' => 'Hafenkante2026', 'password_confirmation' => 'Hafenkante2026']));

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => EMAIL_TAKEN]);
        expect(User::count())->toBe(1)
            ->and(Tenant::count())->toBe(1)
            ->and($carla->fresh()->only('name', 'email', 'password'))->toBe(['name' => 'Carla Brandt', 'email' => 'carla.brandt@elbwerk.test', 'password' => $passwordHash]);
        $this->assertDatabaseEmpty('personal_access_tokens');
    })->with([
        'same spelling' => 'carla.brandt@elbwerk.test',
        'capitals' => 'Carla.Brandt@Elbwerk.test',
    ]);

    it('returns 422 instead of 500 when another registration takes the address just before the account is saved', function () {
        User::creating(function (User $user): void {
            DB::table('users')->insert([
                'tenant_id' => Tenant::factory()->create()->id,
                'name' => 'Mehmet Aydin',
                'email' => $user->email,
                'password' => 'x',
            ]);
        });

        $response = $this->postJson(REGISTER_URL, registration());

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => EMAIL_TAKEN]);
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('tenants');
    });

    it('leaves neither account nor workspace behind when the token cannot be saved', function () {
        PersonalAccessToken::creating(fn () => throw new RuntimeException('Datenbank weg'));

        $response = $this->postJson(REGISTER_URL, registration());

        $response->assertServerError();
        $this->assertDatabaseEmpty('personal_access_tokens');
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('tenants');
    });

    it('leaves no workspace behind when the account cannot be saved', function () {
        User::creating(fn () => throw new RuntimeException('Datenbank weg'));

        $response = $this->postJson(REGISTER_URL, registration());

        $response->assertServerError();
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('tenants');
    });

    it('returns 422 for an email address that starts with demo', function (string $email) {
        $response = $this->postJson(REGISTER_URL, registration(['email' => $email]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => 'E-Mail-Adressen, die mit „demo“ beginnen, sind für den Demo-Zugang reserviert.']);
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('tenants');
    })->with([
        'address of the demo account' => 'demo@firmenwissen.test',
        'capitals' => 'Demo.Team@elbwerk.test',
        'longer word' => 'demokrit@elbwerk.test',
        'accent' => 'démo@elbwerk.test',
    ]);

    it('keeps the demo account working next to registered accounts', function () {
        $this->postJson(REGISTER_URL, registration(['email' => 'demo@firmenwissen.test']))->assertUnprocessable();
        $this->postJson(REGISTER_URL, registration())->assertCreated();

        $this->artisan('app:create-demo-user')->assertSuccessful();
        $this->artisan('app:create-demo-user')->assertSuccessful();

        $demo = User::firstWhere('email', 'demo@firmenwissen.test');
        $carla = User::firstWhere('email', 'carla.brandt@elbwerk.test');
        expect(User::count())->toBe(2)
            ->and($demo->tenant_id)->not->toBe($carla->tenant_id)
            ->and($carla->tenant->name)->toBe('Elbwerk Software GmbH');
        $this->postJson(LOGIN_URL, ['email' => 'demo@firmenwissen.test', 'password' => 'Demo2026'])->assertOk();
    });

    it('returns 422 naming the password rule for a password that breaks it', function (string $password) {
        $response = $this->postJson(REGISTER_URL, registration(['password' => $password, 'password_confirmation' => $password]));

        $response->assertUnprocessable();
        expect($response->json('errors.password'))->toBe([PASSWORD_RULE]);
        $this->assertDatabaseEmpty('users');
    })->with([
        'too short' => 'Elbe26',
        'without digit' => 'Elbwerk',
        'lowercase first letter' => 'elbwerk2026',
        'without letter' => '12345678',
    ]);

    it('returns 422 when the repeated password differs or is missing', function (array $overrides) {
        $response = $this->postJson(REGISTER_URL, registration($overrides));

        $response->assertUnprocessable()->assertJsonValidationErrors(['password' => 'Die beiden Passwörter stimmen nicht überein.']);
        $this->assertDatabaseEmpty('users');
    })->with([
        'typo' => [['password_confirmation' => 'Elbwerk2062']],
        'missing' => [['password_confirmation' => null]],
    ]);

    it('returns 422 for a password with a null character', function () {
        $password = "Elbwerk2026\0";

        $response = $this->postJson(REGISTER_URL, registration(['password' => $password, 'password_confirmation' => $password]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['password' => 'Das Passwort darf kein Nullzeichen enthalten.']);
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('tenants');
    });

    it('returns 422 for a password longer than 72 bytes', function () {
        $password = 'Elbwerk2026'.str_repeat('ä', 31);

        $response = $this->postJson(REGISTER_URL, registration(['password' => $password, 'password_confirmation' => $password]));

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'password' => 'Das Passwort ist zu lang. Erlaubt sind 72 Byte: 72 Zeichen ohne Umlaute und Sonderzeichen, mit ihnen weniger.',
        ]);
        expect(mb_strlen($password))->toBe(42)
            ->and(strlen($password))->toBe(73);
        $this->assertDatabaseEmpty('users');
    });

    it('accepts a password of exactly 72 bytes', function () {
        $password = 'Elbwerk2026'.str_repeat('a', 61);

        $this->postJson(REGISTER_URL, registration(['password' => $password, 'password_confirmation' => $password]))->assertCreated();

        expect(Hash::check($password, User::sole()->password))->toBeTrue();
    });

    it('returns 422 with a field error for missing or malformed input', function (array $overrides, string $field, string $message) {
        $response = $this->postJson(REGISTER_URL, registration($overrides));

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseEmpty('users');
        $this->assertDatabaseEmpty('tenants');
    })->with([
        'missing name' => [['name' => null], 'name', 'Bitte gib deinen Namen ein.'],
        'blank name' => [['name' => '   '], 'name', 'Bitte gib deinen Namen ein.'],
        'name too long' => [['name' => str_repeat('a', 256)], 'name', 'Der Name darf höchstens 255 Zeichen lang sein.'],
        'missing company' => [['company' => null], 'company', 'Bitte gib den Namen deiner Firma ein.'],
        'company as a list' => [['company' => ['Elbwerk']], 'company', 'Bitte gib den Namen deiner Firma ein.'],
        'company too long' => [['company' => str_repeat('a', 256)], 'company', 'Der Name der Firma darf höchstens 255 Zeichen lang sein.'],
        'missing email address' => [['email' => null], 'email', 'Bitte gib deine E-Mail-Adresse ein.'],
        'malformed email address' => [['email' => 'carla.brandt'], 'email', 'Bitte gib eine gültige E-Mail-Adresse ein.'],
        'email address as a list' => [['email' => ['carla.brandt@elbwerk.test']], 'email', 'Bitte gib eine gültige E-Mail-Adresse ein.'],
        'missing password' => [['password' => null], 'password', 'Bitte gib ein Passwort ein.'],
    ]);

    it('returns 429 after five registrations within a minute from the same IP address', function () {
        foreach (range(1, 5) as $attempt) {
            $this->postJson(REGISTER_URL, registration(['email' => "person{$attempt}@elbwerk.test"]))->assertCreated();
        }

        $response = $this->postJson(REGISTER_URL, registration(['email' => 'person6@elbwerk.test']));

        $response->assertTooManyRequests()->assertHeader('Retry-After');
        expect(User::count())->toBe(5);
    });

    it('counts requests with an invalid field towards the limit', function () {
        foreach (range(1, 5) as $attempt) {
            $this->postJson(REGISTER_URL, registration(['email' => 'carla.brandt']))->assertUnprocessable();
        }

        $this->postJson(REGISTER_URL, registration())->assertTooManyRequests();
        $this->assertDatabaseEmpty('users');
    });

    it('does not limit another IP address', function () {
        foreach (range(1, 5) as $attempt) {
            $this->postJson(REGISTER_URL, registration(['email' => 'carla.brandt']));
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson(REGISTER_URL, registration());

        $response->assertCreated();
    });

    it('returns 429 after twenty registrations within a day from the same IP address', function () {
        foreach (range(1, 4) as $minute) {
            foreach (range(1, 5) as $attempt) {
                $this->postJson(REGISTER_URL, registration(['email' => 'carla.brandt']))->assertUnprocessable();
            }
            $this->travel(61)->seconds();
        }

        $response = $this->postJson(REGISTER_URL, registration());

        $response->assertTooManyRequests();
        expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(60);
        $this->assertDatabaseEmpty('users');

        $this->travel(1)->days();
        $this->postJson(REGISTER_URL, registration())->assertCreated();
    });

    it('counts requests turned away by the minute limit towards the day', function () {
        foreach (range(1, 20) as $attempt) {
            $this->postJson(REGISTER_URL, registration(['email' => 'carla.brandt']));
        }

        $this->travel(61)->seconds();
        $response = $this->postJson(REGISTER_URL, registration());

        $response->assertTooManyRequests();
        expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(60);
        $this->assertDatabaseEmpty('users');
    });
});
