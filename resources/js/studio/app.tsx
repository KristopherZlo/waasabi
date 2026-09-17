import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '@fontsource-variable/golos-text';
import './studio.css';
import { Shell } from './components';

const pages = import.meta.glob('./pages/*.tsx');
void createInertiaApp({
    title: (title) => `${title} · waasabi`,
    resolve: async (name) => {
        const loader = pages[`./pages/${name}.tsx`];
        if (!loader) throw new Error(`Unknown page: ${name}`);
        const module = await loader() as {default: React.ComponentType<Record<string, unknown>> & {layout?: (page: React.ReactNode) => React.ReactNode}};
        module.default.layout = (page) => <Shell>{page}</Shell>;
        return module.default;
    },
    setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />),
    progress: { color: '#d68b77' },
});
