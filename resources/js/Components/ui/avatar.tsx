import { initials } from '@/lib/format';

export default function Avatar({ name }: { name: string | null | undefined }) {
    return <span className="avatar">{initials(name)}</span>;
}

export function UserCell({ name }: { name: string | null | undefined }) {
    return (
        <div className="user-cell">
            <Avatar name={name} />
            <span>{name ?? '—'}</span>
        </div>
    );
}
