import React, { useState } from 'react';
import { useLocale } from '../LocaleContext';

export default function HeroSection() {
    const { t, locale } = useLocale();
    const [heroSource, setHeroSource] = useState('/images/hoa-hero.webp');
    return (
        <section id="home" className="hoa-hero" aria-labelledby="hero-title">
            <img data-hero-image className="hoa-hero-image" src={heroSource} onError={() => setHeroSource('/images/southville-community-hero.png')} alt="" width="1537" height="1023" fetchPriority="high" aria-hidden="true" />
            <div className="hoa-hero-overlay" />
            <div data-hero-content className="hoa-container hoa-hero-content">
                <p data-hero-reveal className="hoa-eyebrow hoa-eyebrow-light"><span /> {t("Official community website")}</p>
                <h1 data-hero-reveal id="hero-title">{t("Building a safer, connected, and better community.")}</h1>
                <p data-hero-reveal className="hoa-hero-lead">{t("Southville Phase I residents can access verified HOA updates, services, and records through one dependable community platform.")}</p>
                <div data-hero-reveal className="hoa-hero-actions"><a className="hoa-button hoa-button-primary" href="/homeowner/login">{t("Open resident portal")} <span aria-hidden="true">→</span></a><a className="hoa-button hoa-button-ghost" href="#about">{t("Learn more")}</a></div>
                <p data-hero-reveal className="hoa-hero-note"><strong>{t("Resident-first service")}</strong><span aria-hidden="true" /> {t("Secure access. Clear updates. Faster assistance.")}</p>
            </div>
            <a data-scroll-indicator className="hoa-scroll-indicator" href="#about" aria-label={t("Scroll to about Southville Phase I")}><span /> {t("Explore")}</a>
        </section>
    );
}
