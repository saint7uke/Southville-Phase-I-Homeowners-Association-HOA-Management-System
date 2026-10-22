import React from 'react';
import { useLocale } from '../LocaleContext';

export default function CtaSection() {
    const { t, locale } = useLocale();
    return <section className="hoa-cta" aria-labelledby="cta-title"><div className="hoa-container hoa-cta-inner" data-reveal-group><div data-reveal><p className="hoa-eyebrow hoa-eyebrow-light"><span /> {t("Your HOA, within reach")}</p><h2 id="cta-title">{t("Ready to access your resident services?")}</h2><p>{t("Sign in to review your account, send a request, or check the latest verified community information.")}</p></div><div data-reveal className="hoa-cta-actions"><a className="hoa-button hoa-button-light" href="/homeowner/login">{t("Resident login")} <span aria-hidden="true">→</span></a><a className="hoa-text-link hoa-text-link-light" href="#contact">{t("Contact the HOA")}</a></div></div></section>;
}
