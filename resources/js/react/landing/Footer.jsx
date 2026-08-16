import React from 'react';
import { navigation } from './content';

export default function Footer() {
    return <footer className="hoa-footer"><div className="hoa-container"><div className="hoa-footer-main"><a className="hoa-brand hoa-brand-footer" href="#home"><span className="hoa-brand-mark">S1</span><span><strong>Southville Phase I</strong><small>Homeowners Association</small></span></a><nav aria-label="Footer navigation">{navigation.slice(1).map(([label, href]) => <a key={href} href={href}>{label}</a>)}</nav><a className="hoa-button hoa-button-small hoa-button-light" href="/portal/login">Resident portal</a></div><div className="hoa-footer-bottom"><p>© {new Date().getFullYear()} Southville Phase I Homeowners Association.</p><p>Community information system</p></div></div></footer>;
}
