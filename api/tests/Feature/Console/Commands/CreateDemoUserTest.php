<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Load config/demo.php as if no DEMO_USER_* or DEMO_TENANT_NAME variable were set, then restore the environment.
 *
 * @return array{user: array{name: string, email: string, password: string}, tenant: array{name: string}}
 */
function demoConfigWithoutEnvironment(): array
{
    $saved = [];

    foreach (['DEMO_USER_NAME', 'DEMO_USER_EMAIL', 'DEMO_USER_PASSWORD', 'DEMO_TENANT_NAME'] as $key) {
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
        'demo.tenant.name' => 'Demo Nordlicht IT',
    ]);
});

it('creates the demo user and its workspace with the default values when nothing is configured', function () {
    config(['demo' => demoConfigWithoutEnvironment()]);

    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('demo@firmenwissen.test angelegt')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->name)->toBe('Demo')
        ->and($user->email)->toBe('demo@firmenwissen.test')
        ->and(Hash::check('Demo2026', $user->password))->toBeTrue()
        ->and($user->tenant->name)->toBe('Demo-Arbeitsbereich');
});

it('creates the demo user in a new demo workspace with the configured values', function () {
    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('demo@nordlicht-it.test angelegt')
        ->assertSuccessful();

    $user = User::sole();
    expect($user->name)->toBe('Demo Nordlicht')
        ->and($user->email)->toBe('demo@nordlicht-it.test')
        ->and(Hash::check('Nordlicht2026', $user->password))->toBeTrue();
    expect(Tenant::sole()->is($user->tenant))->toBeTrue()
        ->and($user->tenant->name)->toBe('Demo Nordlicht IT');
});

it('resets the existing demo user and keeps its workspace with the same id', function () {
    $tenant = Tenant::factory()->create(['name' => 'Umbenannter Arbeitsbereich']);
    $user = User::factory()->for($tenant)->create([
        'name' => 'Umbenannt',
        'email' => 'demo@nordlicht-it.test',
        'password' => 'Anderes1Passwort',
    ]);

    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('demo@nordlicht-it.test zurückgesetzt')
        ->assertSuccessful();

    expect(User::count())->toBe(1)
        ->and(Tenant::count())->toBe(1);
    $user->refresh();
    expect($user->name)->toBe('Demo Nordlicht')
        ->and(Hash::check('Nordlicht2026', $user->password))->toBeTrue()
        ->and($user->tenant_id)->toBe($tenant->id);
    expect($tenant->refresh()->name)->toBe('Demo Nordlicht IT');
});

it('does not create a second demo workspace on a second run', function () {
    $this->artisan('app:create-demo-user')->assertSuccessful();
    $tenantId = Tenant::sole()->id;

    $this->artisan('app:create-demo-user')->assertSuccessful();

    expect(Tenant::sole()->id)->toBe($tenantId)
        ->and(User::sole()->tenant_id)->toBe($tenantId);
});

it('rolls back its new demo workspace when another run has created the demo user in the meantime', function () {
    $otherRunTenant = Tenant::factory()->create();
    User::creating(function (User $user) use ($otherRunTenant): void {
        DB::table('users')->insert([
            'tenant_id' => $otherRunTenant->id,
            'name' => 'Demo Nordlicht',
            'email' => $user->email,
            'password' => 'Nordlicht2026',
        ]);
    });

    expect(fn () => $this->artisan('app:create-demo-user')->run())
        ->toThrow(UniqueConstraintViolationException::class);

    expect(Tenant::sole()->is($otherRunTenant))->toBeTrue();
});

it('rejects a configured workspace name that is empty or too long and leaves the demo workspace unchanged', function (string $tenantName) {
    $tenant = Tenant::factory()->create(['name' => 'Demo Alt']);
    User::factory()->for($tenant)->create(['email' => 'demo@nordlicht-it.test']);
    config(['demo.tenant.name' => $tenantName]);

    $this->artisan('app:create-demo-user')
        ->expectsOutputToContain('falsch konfiguriert')
        ->assertFailed();

    expect($tenant->refresh()->name)->toBe('Demo Alt');
})->with([
    'empty' => '',
    'too long' => str_repeat('a', 256),
]);

it('creates its own demo workspace even when another workspace has the same name', function () {
    $tenant = Tenant::factory()->create(['name' => 'Demo Nordlicht IT']);
    $otherUser = User::factory()->for($tenant)->create();

    $this->artisan('app:create-demo-user')->assertSuccessful();

    expect(Tenant::count())->toBe(2)
        ->and($tenant->refresh()->name)->toBe('Demo Nordlicht IT')
        ->and($otherUser->refresh()->tenant_id)->toBe($tenant->id)
        ->and(User::firstWhere('email', 'demo@nordlicht-it.test')->tenant_id)->not->toBe($tenant->id);
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
