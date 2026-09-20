import React, { lazy, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import '@fontsource/poppins/latin-400.css';
import '@fontsource/poppins/latin-500.css';
import '@fontsource/poppins/latin-600.css';
import '@fontsource/poppins/latin-700.css';
import '../../css/landing.css';

const LandingPage = lazy(() => import('./LandingPage'));

const root = document.getElementById('landing-root');

if (root) {
    createRoot(root).render(
        <Suspense fallback={<main className="hoa-loading" aria-busy="true"><p>Loading community information…</p></main>}>
            <LandingPage announcements={JSON.parse(root.dataset.announcements || '[]')} branding={JSON.parse(root.dataset.branding || '{}')} contactSuccess={JSON.parse(root.dataset.contactSuccess || 'null')} />
        </Suspense>,
    );
}
