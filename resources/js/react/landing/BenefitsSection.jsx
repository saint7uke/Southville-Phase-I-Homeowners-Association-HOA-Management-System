import React from 'react';
import { benefits } from './content';
import { useLocale } from '../LocaleContext';

export default function BenefitsSection() {
    const { t, locale } = useLocale();
    return <section id="community" className="hoa-section hoa-benefits" aria-labelledby="benefits-title"><div className="hoa-container hoa-benefits-layout"><div className="hoa-benefits-intro"><p className="hoa-eyebrow"><span /> {t("Why it matters")}</p><h2 id="benefits-title">{t("Built around real resident needs.")}</h2><p>{t("Simple, accountable digital service strengthens the relationship between the association and every household it serves.")}</p></div><ol className="hoa-benefit-list">{benefits.map(([title, description], index) => <li data-benefit key={title}><span aria-hidden="true">{String(index + 1).padStart(2, '0')}</span><div><h3>{t(title)}</h3><p>{t(description)}</p></div></li>)}</ol></div></section>;
}
