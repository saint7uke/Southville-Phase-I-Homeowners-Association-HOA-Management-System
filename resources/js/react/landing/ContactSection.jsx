import axios from 'axios';
import React, { useState } from 'react';
import Swal from 'sweetalert2';

export default function ContactSection({ branding = {}, success = null }) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const [status, setStatus] = useState(success);
    const [error, setError] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const submitContact = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        try {
            const response = await axios.post('/api/contact', new FormData(event.currentTarget), {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            setStatus(response.data.data.message);
            await Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: response.data.data.message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            event.currentTarget.reset();
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
                <p className="hoa-eyebrow"><span /> Contact and office</p>
                <h2 id="contact-title">We are here for the community.</h2>
                <p>For account concerns or in-person assistance, visit the HOA office during posted service hours.</p>
                {status && <p className="hoa-contact-success" role="status" aria-live="polite">{status}</p>}
                {error && <p className="hoa-contact-error" role="alert">{error}</p>}
                <form className="hoa-contact-form" method="post" action="/contact" onSubmit={submitContact}>
                    <input type="hidden" name="_token" value={csrf} />
                    <div className="hoa-contact-honeypot" aria-hidden="true"><label htmlFor="website">Website</label><input id="website" name="website" tabIndex="-1" autoComplete="off" /></div>
                    <label htmlFor="contact-name">Name<input id="contact-name" name="name" required maxLength="120" autoComplete="name" /></label>
                    <label htmlFor="contact-email">Email address<input id="contact-email" name="email" type="email" required maxLength="255" autoComplete="email" /></label>
                    <label htmlFor="contact-phone">Phone <small>(optional)</small><input id="contact-phone" name="phone" maxLength="20" autoComplete="tel" /></label>
                    <label htmlFor="contact-subject">Subject<input id="contact-subject" name="subject" required maxLength="160" /></label>
                    <label htmlFor="contact-message">Message<textarea id="contact-message" name="message" required maxLength="5000" rows="5" /></label>
                    <button className="hoa-button hoa-button-primary" type="submit" disabled={submitting}>{submitting ? 'Sending...' : 'Send message'}</button>
                </form>
            </div>
            <address data-reveal className="hoa-contact-details"><div><span>Office</span><strong>{branding.hoaName || 'Southville Phase I HOA'} Office</strong><p>{branding.address || 'Brgy. Inocencio, Trece Martires City, Cavite'}</p></div><div><span>Service hours</span><strong>Monday to Friday</strong><p>8:00 AM to 5:00 PM</p></div><div><span>Email</span>{branding.contactEmail ? <a href={`mailto:${branding.contactEmail}`}>{branding.contactEmail}</a> : <p>Contact the HOA office</p>}<p>For non-urgent resident inquiries</p></div></address>
            <div data-reveal className="hoa-map-placeholder" role="img" aria-label="Map placeholder showing the Southville Phase I HOA office in Barangay Inocencio"><span className="hoa-map-pin"><b>S1</b></span><div><strong>Southville Phase I</strong><small>Brgy. Inocencio</small></div></div>
        </div>
    </section>;
}
