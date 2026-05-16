import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// Import all SVG files from resources/svg
const svgFiles = import.meta.glob('./assets/svg/*.svg?react', { eager: true });

// Create a mapping of filenames to React components
const svgComponents = {};
Object.entries(svgFiles).forEach(([path, module]) => {
  const filename = path.split('/').pop().replace('.svg?react', '');
  // Use default export for SVG components from svgr v4
  svgComponents[filename] = module.default;
});

import { ThemeProvider } from './Tailadmin/context/ThemeContext';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            [`./Pages/${name}.tsx`, `./Pages/${name}.jsx`],
            import.meta.glob('./Pages/**/*.{jsx,tsx}'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ThemeProvider>
                <App {...props} />
            </ThemeProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
