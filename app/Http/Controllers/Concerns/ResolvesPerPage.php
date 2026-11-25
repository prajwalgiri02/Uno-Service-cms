<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * The chosen page size is remembered in the session and shared by every CMS list.
 */
trait ResolvesPerPage
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public const DEFAULT_PER_PAGE = 10;

    protected function resolvePerPage(Request $request, string $key = 'per_page'): int
    {
        $requested = $request->integer($key);

        if (in_array($requested, self::PER_PAGE_OPTIONS, true)) {
            $request->session()->put('pagination.per_page', $requested);

            return $requested;
        }

        $stored = (int) $request->session()->get('pagination.per_page', self::DEFAULT_PER_PAGE);

        return in_array($stored, self::PER_PAGE_OPTIONS, true) ? $stored : self::DEFAULT_PER_PAGE;
    }
}
