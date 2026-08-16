import React from 'react';
import { benefits } from './content';

export default function BenefitsSection() {
    return <section id="community" className="hoa-section hoa-benefits" aria-labelledby="benefits-title"><div className="hoa-container hoa-benefits-layout"><div className="hoa-benefits-intro"><p className="hoa-eyebrow"><span /> Why it matters</p><h2 id="benefits-title">Built around real resident needs.</h2><p>Simple, accountable digital service strengthens the relationship between the association and every household it serves.</p></div><ol className="hoa-benefit-list">{benefits.map(([title, description], index) => <li data-benefit key={title}><span aria-hidden="true">{String(index + 1).padStart(2, '0')}</span><div><h3>{title}</h3><p>{description}</p></div></li>)}</ol></div></section>;
}
