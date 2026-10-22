import React from 'react';
import { useLocale } from '../LocaleContext';

export default function ParallaxSection() {
    const { t, locale } = useLocale();
    return <section data-parallax-section className="hoa-parallax" aria-labelledby="parallax-title"><div data-parallax-back className="hoa-parallax-orb hoa-parallax-orb-one" /><div data-parallax-front className="hoa-parallax-orb hoa-parallax-orb-two" /><div className="hoa-container hoa-parallax-content" data-reveal-group><p data-reveal className="hoa-eyebrow hoa-eyebrow-light"><span /> {t("One connected community")}</p><h2 data-reveal id="parallax-title">{t("Better information creates better neighborhoods.")}</h2><p data-reveal>{t("When residents and HOA officers share reliable records and timely updates, everyone can make informed decisions and move community concerns forward.")}</p></div></section>;
}
