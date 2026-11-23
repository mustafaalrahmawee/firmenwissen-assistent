<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;

it('rejects an account without a workspace', function () {
    expect(fn () => User::factory()->create(['tenant_id' => null]))
        ->toThrow(QueryException::class);

    $this->assertDatabaseEmpty('users');
});

it('does not take the workspace from mass assignment', function () {
    $user = User::factory()->create();
    $ownTenantId = $user->tenant_id;
    $otherTenant = Tenant::factory()->create();

    $user->fill(['tenant_id' => $otherTenant->id])->save();

    expect($user->refresh()->tenant_id)->toBe($ownTenantId);
});

it('gives every factory account its own workspace', function () {
    $jana = User::factory()->create();
    $mehmet = User::factory()->create();

    expect($jana->tenant_id)->not->toBe($mehmet->tenant_id);
});
