import React from 'react';
import { createRoot } from 'react-dom/client';
import '@fontsource/poppins/latin-400.css';
import '@fontsource/poppins/latin-500.css';
import '@fontsource/poppins/latin-600.css';
import '@fontsource/poppins/latin-700.css';
import '../../css/landing.css';
import LandingPage from './LandingPage';

const root = document.getElementById('landing-root');

if (root) {
    createRoot(root).render(<LandingPage announcements={JSON.parse(root.dataset.announcements || '[]')} />);
}
