import { router } from '@inertiajs/react';

type QueryValue = string | number | boolean | null | undefined;

/**
 * Reloads the current list with merged filters, dropping empty values and the page number.
 */
export function applyFilters(path: string, current: Record<string, QueryValue>, patch: Record<string, QueryValue>): void {
    const merged = { ...current, ...patch, page: undefined };
    const query: Record<string, string> = Object.fromEntries(
        Object.entries(merged)
            .filter(([, v]) => v !== '' && v !== null && v !== undefined)
            .map(([k, v]) => [k, String(v)]),
    );

    router.get(path, query, { preserveState: true, preserveScroll: true, replace: true });
}
