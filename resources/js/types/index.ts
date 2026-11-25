export type AuthUser = {
    id: number;
    name: string | null;
    email: string | null;
};

export type Badges = Record<string, number>;

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: { user: AuthUser | null };
    badges: Badges | null;
    errors: Record<string, string>;
    flash: {
        success?: string | null;
        error?: string | null;
        flash_id?: number;
    };
};

export type PaginationLink = { url: string | null; label: string; active: boolean };

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};
