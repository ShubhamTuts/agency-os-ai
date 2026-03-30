import React from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './styles/index.css';

function hideBootPreloader(id: string) {
    const preloader = document.getElementById(id);
    if (!preloader) return;

    requestAnimationFrame(() => {
        preloader.classList.add('is-ready');
        window.setTimeout(() => preloader.remove(), 260);
    });
}

const container = document.getElementById('aosai-admin-root');
if (container) {
    const root = createRoot(container);
    root.render(<App />);
    hideBootPreloader('aosai-admin-preloader');
}
