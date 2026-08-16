import React from 'react';
import { services } from './content';

export default function ServicesSection() {
    return <section id="services" className="hoa-section hoa-services" aria-labelledby="services-title"><div className="hoa-container"><div className="hoa-section-heading" data-reveal-group><p data-reveal className="hoa-eyebrow"><span /> Resident services</p><h2 data-reveal id="services-title">Everyday HOA services, in one clear place.</h2><p data-reveal>Designed to reduce paperwork, improve follow-through, and keep residents connected to their association.</p></div><div className="hoa-service-grid" data-reveal-group>{services.map((service, index) => <article data-reveal className={`hoa-service-card ${index === 0 ? 'is-featured' : ''}`} key={service.title}><span className="hoa-service-mark" aria-hidden="true">{service.mark}</span><div><h3>{service.title}</h3><p>{service.description}</p></div><span className="hoa-card-index" aria-hidden="true">0{index + 1}</span></article>)}</div></div></section>;
}
