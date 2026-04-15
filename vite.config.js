import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const hmrHost = env.VITE_HMR_HOST || env.APP_URL?.replace(/^https?:\/\//, '');

    return {
        server: {
            host: '0.0.0.0',
            hmr: hmrHost
                ? {
                    host: hmrHost,
                    protocol: 'wss',
                    clientPort: 443,
                }
                : undefined,
        },
        plugins: [
            react(),
            laravel({
                input: ['resources/js/app.jsx'],
                refresh: true,
            }),
        ],
    };
});
