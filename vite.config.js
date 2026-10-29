import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/landing.js', 'resources/js/seminar-room.js', 'resources/js/seminar-p2p.js'],
            refresh: true,
        }),
    ],
})
