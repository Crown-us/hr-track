import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';

void createInertiaApp({
    title: (title) => `${title} · HRIS Lite`,
    strictMode: true,
    pages: { path: './pages', extension: '.tsx' },
    progress: { color: '#B42318' },
});
