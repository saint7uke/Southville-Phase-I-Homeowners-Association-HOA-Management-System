import React, { useRef } from 'react';
import AboutSection from './landing/AboutSection';
import AnnouncementsSection from './landing/AnnouncementsSection';
import BenefitsSection from './landing/BenefitsSection';
import ContactSection from './landing/ContactSection';
import CtaSection from './landing/CtaSection';
import Footer from './landing/Footer';
import HeroSection from './landing/HeroSection';
import HowItWorksSection from './landing/HowItWorksSection';
import Navbar from './landing/Navbar';
import ParallaxSection from './landing/ParallaxSection';
import ServicesSection from './landing/ServicesSection';
import useLandingAnimations from './landing/useLandingAnimations';

export default function LandingPage({ announcements = [], branding = {}, contactSuccess = null }) {
    const pageRef = useRef(null);

    useLandingAnimations(pageRef);

    return (
        <div ref={pageRef} className="hoa-landing">
            <a className="hoa-skip-link" href="#main-content">Skip to main content</a>
            <Navbar branding={branding} />
            <main id="main-content" tabIndex="-1">
                <HeroSection />
                <AboutSection />
                <ServicesSection />
                <HowItWorksSection />
                <ParallaxSection />
                <AnnouncementsSection announcements={announcements} />
                <BenefitsSection />
                <CtaSection />
                <ContactSection branding={branding} success={contactSuccess} />
            </main>
            <Footer branding={branding} />
        </div>
    );
}
