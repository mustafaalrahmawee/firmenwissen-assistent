<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('identifies a workspace by a ULID that stays the same when the workspace is renamed', function () {
    $tenant = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
    $id = $tenant->id;

    $tenant->update(['name' => 'Nordlicht IT AG']);

    expect(Str::isUlid($id))->toBeTrue()
        ->and($tenant->refresh()->id)->toBe($id);
});

it('refuses to change the id of a saved workspace without accounts', function () {
    $tenant = Tenant::factory()->create();
    $id = $tenant->id;

    $tenant->id = (string) Str::ulid();

    expect(fn () => $tenant->save())->toThrow(LogicException::class);

    $this->assertDatabaseHas('tenants', ['id' => $id]);
    $this->assertDatabaseCount('tenants', 1);
});

it('keeps two workspaces with the same name apart', function () {
    $first = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
    $second = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
    $jana = User::factory()->for($first)->create();
    $mehmet = User::factory()->for($second)->create();

    expect($first->id)->not->toBe($second->id)
        ->and($jana->tenant->is($first))->toBeTrue()
        ->and($mehmet->tenant->is($second))->toBeTrue();
});

it('refuses to delete a workspace that still has accounts', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->for($tenant)->create();

    expect(fn () => $tenant->delete())->toThrow(QueryException::class);

    $this->assertModelExists($tenant);
});
