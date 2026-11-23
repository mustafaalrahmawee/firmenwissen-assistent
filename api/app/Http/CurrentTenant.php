<?php

namespace App\Http;

use App\Models\Tenant;
use LogicException;

/**
 * The workspace of the current request, set only by the middleware "tenant"
 * from the logged-in user. Queries for data of a workspace start from it,
 * e.g. $currentTenant->get()->documents().
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Without a workspace this throws instead of returning null, so no query
     * can run without the restriction to a workspace.
     */
    public function get(): Tenant
    {
        return $this->tenant ?? throw new LogicException('Für diese Anfrage ist kein Arbeitsbereich gesetzt.');
    }
}
