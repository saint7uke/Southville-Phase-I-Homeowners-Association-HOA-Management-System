import React from 'react';
import { statistics } from './content';
import { useLocale } from '../LocaleContext';

export default function AboutSection() {
    const { t, locale } = useLocale();
    return <section id="about" className="hoa-section hoa-about" aria-labelledby="about-title"><div className="hoa-container"><div className="hoa-about-copy" data-reveal-group><div data-reveal><p className="hoa-eyebrow"><span /> {t("About the association")}</p><h2 id="about-title">{t("A community built on participation and trust.")}</h2></div><div data-reveal className="hoa-rich-copy"><p>{t("The Southville Phase I Homeowners Association supports a well-managed neighborhood where residents stay informed, participate in community life, and receive dependable service.")}</p><p>{t("Our information system brings essential HOA transactions closer to every household while helping officers maintain accurate and accountable records.")}</p></div></div><dl className="hoa-stats" data-reveal-group aria-label={t("Community statistics")}>{statistics.map((stat) => <div data-reveal key={stat.label}><dt data-counter={stat.value} data-suffix={stat.suffix}>{stat.value}{stat.suffix}</dt><dd>{t(stat.label)}</dd></div>)}</dl></div></section>;
}
