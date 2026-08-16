import React from 'react';

export default function CtaSection() {
    return <section className="hoa-cta" aria-labelledby="cta-title"><div className="hoa-container hoa-cta-inner" data-reveal-group><div data-reveal><p className="hoa-eyebrow hoa-eyebrow-light"><span /> Your HOA, within reach</p><h2 id="cta-title">Ready to access your resident services?</h2><p>Sign in to review your account, send a request, or check the latest verified community information.</p></div><div data-reveal className="hoa-cta-actions"><a className="hoa-button hoa-button-light" href="/portal/login">Resident login <span aria-hidden="true">→</span></a><a className="hoa-text-link hoa-text-link-light" href="#contact">Contact the HOA</a></div></div></section>;
}
