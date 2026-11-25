import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';

createInertiaApp({
    title: (title) => (title ? `${title} · Najik Admin` : 'Najik Admin'),
    resolve: (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Page not found: Pages/${name}.tsx`);
        }
        return page.default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#F5E94E' },
});
