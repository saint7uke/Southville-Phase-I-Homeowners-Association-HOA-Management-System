import React from 'react';
import { useLocale } from '../LocaleContext';

export default function ContactSection({ branding = {} }) {
    const { t } = useLocale();
    return <section id="contact" className="hoa-section hoa-contact" aria-labelledby="contact-title">
        <div className="hoa-container hoa-contact-layout" data-reveal-group>
            <div data-reveal>
                <p className="hoa-eyebrow"><span /> {t('Contact and office')}</p>
                <h2 id="contact-title">{t('We are here for the community.')}</h2>
                <p>{t('For account concerns or in-person assistance, visit the HOA office during posted service hours.')}</p>
                <p>{t('Residents can submit complaints and inquiries through My Concerns after signing in.')}</p>
                <a className="hoa-button hoa-button-primary" href="/homeowner/login">{t('Resident login')}</a>
            </div>
            <address data-reveal className="hoa-contact-details">
                <div><span>{t('Office')}</span><strong>{t(':hoa Office', { hoa: branding.hoaName || 'Southville Phase I HOA' })}</strong><p>{branding.address || 'Brgy. Inocencio, Trece Martires City, Cavite'}</p></div>
                <div><span>{t('Service hours')}</span><strong>{t('Monday to Friday')}</strong><p>{t('8:00 AM to 5:00 PM')}</p></div>
                <div><span>{t('Email')}</span>{branding.contactEmail ? <a href={`mailto:${branding.contactEmail}`}>{branding.contactEmail}</a> : <p>{t('Contact the HOA office')}</p>}</div>
            </address>
        </div>
    </section>;
}
