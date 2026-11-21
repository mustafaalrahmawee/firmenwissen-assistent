<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Load config/demo.php as if no DEMO_USER_* variable were set, then restore the environment.
 *
 * @return array{user: array{name: string, email: string, password: string}}
 */
function demoConfigWithoutEnvironment(): array
{
    $saved = [];

    foreach (['DEMO_USER_NAME', 'DEMO_USER_EMAIL', 'DEMO_USER_PASSWORD'] as $key) {
        $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    try {
        return require config_path('demo.php');
    } finally {
        foreach ($saved as $key => [$env, $server, $process]) {
            if ($env !== null) {
                $_ENV[$key] = $env;
            }
            if ($server !== null) {
                $_SERVER[$key] = $server;
            }
            if ($process !== false) {
                putenv("{$key}={$process}");
            }
        }
    }
}

beforeEach(function () {
    config([
        'demo.user.name' => 'Demo Nordlicht',
        'demo.user.email' => 'demo@nordlicht-it.test',
        'demo.user.password' => 'Nordlicht2026',
    ]);
});

it('creates the demo user with the default credentials when no DEMO_USER values are set', function () {
    config(['demo' => demoConfigWithoutEnvironment()]);

    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('demo@firmenwissen.test angelegt')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->name)->toBe('Demo')
        ->and($user->email)->toBe('demo@firmenwissen.test')
        ->and(Hash::check('Demo2026', $user->password))->toBeTrue();
});

it('creates the demo user with the configured name, email address and password', function () {
    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('demo@nordlicht-it.test angelegt')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->name)->toBe('Demo Nordlicht')
        ->and($user->email)->toBe('demo@nordlicht-it.test')
        ->and(Hash::check('Nordlicht2026', $user->password))->toBeTrue();
});

it('resets name and password of an existing demo user without creating a second one', function () {
    $user = User::factory()->create([
        'name' => 'Umbenannt',
        'email' => 'demo@nordlicht-it.test',
        'password' => 'Anderes1Passwort',
    ]);

    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('demo@nordlicht-it.test zurückgesetzt')
        ->assertSuccessful();

    expect(User::count())->toBe(1);
    $user->refresh();
    expect($user->name)->toBe('Demo Nordlicht')
        ->and(Hash::check('Nordlicht2026', $user->password))->toBeTrue();
});

it('ends all sessions of the demo user but not those of other users', function () {
    $demoUser = User::factory()->create(['email' => 'demo@nordlicht-it.test']);
    $demoUser->createToken('laptop');
    $demoUser->createToken('phone');
    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('laptop')->accessToken;

    $this->artisan('app:create-demo-user')->assertSuccessful();

    expect($demoUser->tokens()->count())->toBe(0);
    $this->assertModelExists($otherToken);
});

it('rejects a configured password that breaks the password rule and leaves the demo user unchanged', function (string $password) {
    $user = User::factory()->create([
        'name' => 'Demo Alt',
        'email' => 'demo@nordlicht-it.test',
        'password' => 'Altes1Passwort',
    ]);
    $token = $user->createToken('laptop')->accessToken;
    config(['demo.user.password' => $password]);

    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('falsch konfiguriert')
        ->assertFailed();

    $user->refresh();
    expect($user->name)->toBe('Demo Alt')
        ->and(Hash::check('Altes1Passwort', $user->password))->toBeTrue();
    $this->assertModelExists($token);
})->with([
    'too short' => 'Demo123',
    'without digit' => 'Demopasswort',
    'roman numeral instead of digit' => 'DemoTestⅣ',
    'without letter' => '12345678',
    'lowercase first letter' => 'demo2026',
    'digit first' => '2026Demo',
    'empty' => '',
]);

it('rejects a configured email address that does not start with demo and leaves that account unchanged', function () {
    $user = User::factory()->create([
        'name' => 'Jana Petrov',
        'email' => 'jana.petrov@nordlicht-it.test',
        'password' => 'Janas1Passwort',
    ]);
    $token = $user->createToken('laptop')->accessToken;
    config(['demo.user.email' => 'jana.petrov@nordlicht-it.test']);

    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('falsch konfiguriert')
        ->assertFailed();

    $user->refresh();
    expect($user->name)->toBe('Jana Petrov')
        ->and(Hash::check('Janas1Passwort', $user->password))->toBeTrue();
    $this->assertModelExists($token);
});

it('rejects a configured email address that is not valid', function () {
    config(['demo.user.email' => 'demo-keine-adresse']);

    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('falsch konfiguriert')
        ->assertFailed();

    $this->assertDatabaseEmpty('users');
});
