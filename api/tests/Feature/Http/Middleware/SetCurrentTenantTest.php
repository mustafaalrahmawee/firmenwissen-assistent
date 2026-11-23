<?php

use App\Http\CurrentTenant;
use App\Http\Middleware\SetCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * A record with a workspace that exists only in these tests, because TEN-01 has no such data yet.
 */
class WorkspaceNote extends Model
{
    /** @use HasFactory<WorkspaceNoteFactory> */
    use HasFactory;

    protected $table = 'workspace_notes';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function newFactory(): WorkspaceNoteFactory
    {
        return WorkspaceNoteFactory::new();
    }
}

/**
 * @extends Factory<WorkspaceNote>
 */
class WorkspaceNoteFactory extends Factory
{
    protected $model = WorkspaceNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(3),
        ];
    }
}

const CURRENT_TENANT_URL = '/api/v1/test/current-tenant';
const NOTES_URL = '/api/v1/test/notes';

beforeEach(function () {
    Schema::create('workspace_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignUlid('tenant_id')->constrained();
        $table->string('title');
        $table->timestamps();
    });

    Route::middleware(['api', 'auth:sanctum', 'tenant'])->prefix('api/v1/test')->group(function () {
        Route::get('current-tenant', fn (CurrentTenant $currentTenant): array => [
            'data' => ['name' => $currentTenant->get()->name],
        ]);
        Route::get('notes/{id}', fn (CurrentTenant $currentTenant, string $id): array => [
            'data' => ['title' => $currentTenant->get()->hasMany(WorkspaceNote::class)->findOrFail($id)->title],
        ]);
    });
});

it('sets the workspace of the logged-in user for each request', function () {
    $jana = User::factory()->for(Tenant::factory()->state(['name' => 'Nordlicht IT GmbH']))->create();
    $mehmet = User::factory()->for(Tenant::factory()->state(['name' => 'Hafenkante Systemhaus GmbH']))->create();

    $this->withToken($jana->createToken('web')->plainTextToken)
        ->getJson(CURRENT_TENANT_URL)
        ->assertOk()
        ->assertExactJson(['data' => ['name' => 'Nordlicht IT GmbH']]);

    Auth::forgetGuards();

    $this->withToken($mehmet->createToken('web')->plainTextToken)
        ->getJson(CURRENT_TENANT_URL)
        ->assertOk()
        ->assertExactJson(['data' => ['name' => 'Hafenkante Systemhaus GmbH']]);
});

it('ignores a workspace that the request names in the query, the body or a header', function (string $place) {
    $mehmet = User::factory()->for(Tenant::factory()->state(['name' => 'Hafenkante Systemhaus GmbH']))->create();
    $nordlicht = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
    $request = $this->withToken($mehmet->createToken('web')->plainTextToken);

    $response = match ($place) {
        'query' => $request->getJson(CURRENT_TENANT_URL.'?tenant_id='.$nordlicht->id),
        'body' => $request->json('GET', CURRENT_TENANT_URL, ['tenant_id' => $nordlicht->id]),
        'header' => $request->withHeader('X-Tenant-Id', $nordlicht->id)->getJson(CURRENT_TENANT_URL),
    };

    $response->assertOk()->assertExactJson(['data' => ['name' => 'Hafenkante Systemhaus GmbH']]);
})->with(['query', 'body', 'header']);

it('runs after auth:sanctum on every protected route of the API', function () {
    $protectedRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'api/v1/') && $route->getName() !== 'v1.login');

    expect($protectedRoutes->map->getName())->toContain('v1.user', 'v1.logout');

    $protectedRoutes->each(function (Illuminate\Routing\Route $route): void {
        $middleware = app('router')->gatherRouteMiddleware($route);
        $authenticate = array_search('Illuminate\Auth\Middleware\Authenticate:sanctum', $middleware, true);
        $setCurrentTenant = array_search(SetCurrentTenant::class, $middleware, true);

        expect($authenticate)->not->toBeFalse("{$route->uri()} ohne auth:sanctum")
            ->and($setCurrentTenant)->not->toBeFalse("{$route->uri()} ohne tenant")
            ->and($setCurrentTenant)->toBeGreaterThan($authenticate);
    });
});

it('returns 401 without a token', function () {
    $this->getJson(CURRENT_TENANT_URL)->assertUnauthorized();
});

it('returns a record of the own workspace', function () {
    $nordlicht = Tenant::factory()->create();
    $jana = User::factory()->for($nordlicht)->create();
    $note = WorkspaceNote::factory()->for($nordlicht)->create(['title' => 'Netzwerkplan']);

    $this->withToken($jana->createToken('web')->plainTextToken)
        ->getJson(NOTES_URL.'/'.$note->id)
        ->assertOk()
        ->assertExactJson(['data' => ['title' => 'Netzwerkplan']]);
});

it('returns 404 for a record of another workspace exactly as for a record that does not exist', function () {
    $hafenkante = Tenant::factory()->create();
    $mehmet = User::factory()->for($hafenkante)->create();
    $foreignNote = WorkspaceNote::factory()->create(['title' => 'Kundenakte Nordlicht']);
    $missingId = $foreignNote->id + 1000;
    $token = $mehmet->createToken('web')->plainTextToken;
    config(['app.debug' => false]);

    $foreign = $this->withToken($token)->getJson(NOTES_URL.'/'.$foreignNote->id);
    $missing = $this->withToken($token)->getJson(NOTES_URL.'/'.$missingId);

    $foreign->assertNotFound()->assertJsonMissing(['title' => 'Kundenakte Nordlicht']);
    $missing->assertNotFound();
    expect(str_replace((string) $foreignNote->id, '<id>', $foreign->json('message')))
        ->toBe(str_replace((string) $missingId, '<id>', $missing->json('message')))
        ->and(array_keys($foreign->json()))->toBe(array_keys($missing->json()));
});
