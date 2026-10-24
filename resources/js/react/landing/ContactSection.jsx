import axios from 'axios';
import React, { useState } from 'react';
import Swal from 'sweetalert2';
import { useLocale } from '../LocaleContext';

export default function ContactSection({ branding = {}, success = null, reference = null }) {
    const { t } = useLocale();
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const [status, setStatus] = useState(success ? { message: success, reference } : null);
    const [error, setError] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const submitContact = async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        setSubmitting(true);
        setError(null);

        try {
            const response = await axios.post(form.action, new FormData(form), {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            setStatus(response.data.data);
            await Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: t(response.data.data.message),
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            form.reset();
        } catch (requestError) {
            const validationErrors = requestError.response?.data?.errors;
            setError(validationErrors ? Object.values(validationErrors).flat()[0] : 'We could not send your message. Please try again.');
        } finally {
            setSubmitting(false);
        }
    };

    return <section id="contact" className="hoa-section hoa-contact" aria-labelledby="contact-title">
        <div className="hoa-container hoa-contact-layout" data-reveal-group>
            <div data-reveal>
                <p className="hoa-eyebrow"><span /> {t('Contact and office')}</p>
                <h2 id="contact-title">{t('We are here for the community.')}</h2>
                <p>{t('For account concerns or in-person assistance, visit the HOA office during posted service hours.')}</p>
                {status && <p className="hoa-contact-success" role="status" aria-live="polite">{t(status.message)} {status.reference && <strong>{t('Inquiry reference')}: {status.reference}</strong>}</p>}
                {error && <p className="hoa-contact-error" role="alert">{t(error)}</p>}
                <form className="hoa-contact-form" method="post" action="/contact" onSubmit={submitContact}>
                    <input type="hidden" name="_token" value={csrf} />
                    <div className="hoa-contact-honeypot" aria-hidden="true"><label htmlFor="website">{t('Website')}</label><input id="website" name="website" tabIndex="-1" autoComplete="off" /></div>
                    <label htmlFor="contact-name">{t('Name')}<input id="contact-name" name="name" required maxLength="120" autoComplete="name" /></label>
                    <label htmlFor="contact-email">{t('Email address')}<input id="contact-email" name="email" type="email" required maxLength="255" autoComplete="email" /></label>
                    <label htmlFor="contact-phone">{t('Contact number')} <small>{t('(optional)')}</small><input id="contact-phone" name="phone" maxLength="20" autoComplete="tel" /></label>
                    <label htmlFor="contact-subject">{t('Subject')}<input id="contact-subject" name="subject" required maxLength="160" /></label>
                    <label htmlFor="contact-message">{t('Message')}<textarea id="contact-message" name="message" required maxLength="5000" rows="5" /></label>
                    <button className="hoa-button hoa-button-primary" type="submit" disabled={submitting}>{t(submitting ? 'Sending...' : 'Send message')}</button>
                </form>
            </div>
            <address data-reveal className="hoa-contact-details"><div><span>{t('Office')}</span><strong>{t(':hoa Office', { hoa: branding.hoaName || 'Southville Phase I HOA' })}</strong><p>{branding.address || 'Brgy. Inocencio, Trece Martires City, Cavite'}</p></div><div><span>{t('Service hours')}</span><strong>{t('Monday to Friday')}</strong><p>{t('8:00 AM to 5:00 PM')}</p></div><div><span>{t('Email')}</span>{branding.contactEmail ? <a href={`mailto:${branding.contactEmail}`}>{branding.contactEmail}</a> : <p>{t('Contact the HOA office')}</p>}<p>{t('For non-urgent resident inquiries')}</p></div></address>
            <div data-reveal className="hoa-map-placeholder" role="img" aria-label={t('Map placeholder showing the Southville Phase I HOA office in Barangay Inocencio')}><span className="hoa-map-pin"><b>S1</b></span><div><strong>Southville Phase I</strong><small>Brgy. Inocencio</small></div></div>
        </div>
    </section>;
}
