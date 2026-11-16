import React, { useRef } from 'react';
import AboutSection from './landing/AboutSection';
import AnnouncementsSection from './landing/AnnouncementsSection';
import ContactSection from './landing/ContactSection';
import Footer from './landing/Footer';
import HeroSection from './landing/HeroSection';
import Navbar from './landing/Navbar';
import ParallaxSection from './landing/ParallaxSection';
import ServicesSection from './landing/ServicesSection';
import useLandingAnimations from './landing/useLandingAnimations';
import ProjectsSection from './landing/ProjectsSection';
import { useLocale } from './LocaleContext';

export default function LandingPage({ announcements = [], projects = [], branding = {}, contactSuccess = null, contactReference = null }) {
    const pageRef = useRef(null);
    const { t } = useLocale();

    useLandingAnimations(pageRef);

    return (
        <div ref={pageRef} className="hoa-landing">
            <a className="hoa-skip-link" href="#main-content">{t('Skip to main content')}</a>
            <Navbar branding={branding} />
            <main id="main-content" tabIndex="-1">
                <HeroSection />
                <AboutSection />
                <ServicesSection />
                <ParallaxSection />
                <AnnouncementsSection announcements={announcements} />
                <ProjectsSection projects={projects} />
                <ContactSection branding={branding} success={contactSuccess} reference={contactReference} />
            </main>
            <Footer branding={branding} />
        </div>
    );
}
