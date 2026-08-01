<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesPageSize
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Clamp the client-supplied page size to a sane range, so a caller cannot
     * ask for every row at once. Anything non-numeric falls back to the default
     * rather than collapsing to a single row.
     */
    protected function perPage(Request $request): int
    {
        $perPage = $request->query('per_page');

        if (! is_numeric($perPage)) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min((int) $perPage, self::MAX_PER_PAGE));
    }
}
