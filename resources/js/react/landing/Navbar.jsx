import React, { useEffect, useState } from 'react';
import { navigation } from './content';

export default function Navbar() {
    const [menuOpen, setMenuOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const [activeSection, setActiveSection] = useState('home');

    useEffect(() => {
        const updateScrollState = () => setScrolled(window.scrollY > 36);
        updateScrollState();
        window.addEventListener('scroll', updateScrollState, { passive: true });
        const observer = new IntersectionObserver(
            (entries) => entries.forEach((entry) => entry.isIntersecting && setActiveSection(entry.target.id)),
            { rootMargin: '-28% 0px -62% 0px' },
        );
        navigation.forEach(([, href]) => {
            const section = document.querySelector(href);
            if (section) observer.observe(section);
        });
        const closeOnEscape = (event) => event.key === 'Escape' && setMenuOpen(false);
        window.addEventListener('keydown', closeOnEscape);
        return () => {
            window.removeEventListener('scroll', updateScrollState);
            window.removeEventListener('keydown', closeOnEscape);
            observer.disconnect();
        };
    }, []);

    return (
        <header data-nav className={`hoa-navbar ${scrolled || menuOpen ? 'is-scrolled' : ''}`}>
            <div className="hoa-navbar-inner">
                <a className="hoa-brand" href="#home" aria-label="Southville Phase I HOA home"><span className="hoa-brand-mark" aria-hidden="true">S1</span><span><strong>Southville Phase I</strong><small>Homeowners Association</small></span></a>
                <button className="hoa-menu-toggle" type="button" aria-expanded={menuOpen} aria-controls="primary-navigation" aria-label={menuOpen ? 'Close navigation menu' : 'Open navigation menu'} onClick={() => setMenuOpen((open) => !open)}><span /><span /><span /></button>
                <nav id="primary-navigation" className={`hoa-nav ${menuOpen ? 'is-open' : ''}`} aria-label="Primary navigation">
                    {navigation.map(([label, href]) => <a key={href} href={href} className={activeSection === href.slice(1) ? 'is-active' : ''} aria-current={activeSection === href.slice(1) ? 'location' : undefined} onClick={() => setMenuOpen(false)}>{label}</a>)}
                    <a className="hoa-button hoa-button-small hoa-button-primary" href="/portal/login">Resident login</a>
                </nav>
            </div>
        </header>
    );
}
