import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import svgr from 'vite-plugin-svgr';
// import react from '@vitejs/plugin-react-oxc';

import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const host = env.VITE_HOST || 'localhost';
    const port = env.VITE_PORT ? parseInt(env.VITE_PORT) : 5175;

    return {
        plugins: [
            svgr(),
            tailwindcss(),
            laravel({
                input: 'resources/js/app.jsx',
                refresh: true,
            }),
            react(),
        ],
        server: {
            host: '0.0.0.0', // Membuka akses agar bisa diakses lewat IP LAN
            port: port,
            hmr: {
                host: host,
            },
        },
        optimizeDeps: {
            include: ['@react-jvectormap/core', '@react-jvectormap/world'],
        },
    };
});
