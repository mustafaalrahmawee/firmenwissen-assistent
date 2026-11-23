<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stops with a hint to migrate:fresh and keeps the accounts of a users table without workspaces', function () {
    $migration = require database_path('migrations/2026_11_23_081341_add_tenant_id_to_users_table.php');
    $migration->down();
    DB::table('users')->insert([
        'name' => 'Jana Petrov',
        'email' => 'jana.petrov@nordlicht-it.test',
        'password' => 'Nordlicht2026',
    ]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'php artisan migrate:fresh');

    expect(Schema::hasColumn('users', 'tenant_id'))->toBeFalse();
    $this->assertDatabaseHas('users', ['email' => 'jana.petrov@nordlicht-it.test']);
});
