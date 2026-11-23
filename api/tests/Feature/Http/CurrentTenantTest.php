<?php

use App\Http\CurrentTenant;

it('throws when no workspace is set, so no query can run without the restriction', function () {
    expect(fn () => app(CurrentTenant::class)->get())
        ->toThrow(LogicException::class, 'kein Arbeitsbereich gesetzt');
});

it('starts every request and every queued job with a fresh instance', function () {
    $first = app(CurrentTenant::class);

    expect(app(CurrentTenant::class))->toBe($first);

    app()->forgetScopedInstances();

    expect(app(CurrentTenant::class))->not->toBe($first);
});
