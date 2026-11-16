import React from 'react';
import { services } from './content';
import { useLocale } from '../LocaleContext';
import ServiceIcon from './ServiceIcon';

export default function ServicesSection() {
    const { t } = useLocale();

    return (
        <section id="services" className="hoa-section hoa-services" aria-label={t('Resident services')}>
            <div className="hoa-container">
                <div className="hoa-service-grid" data-reveal-group>
                    {services.map((service, index) => (
                        <article data-reveal className={`hoa-service-card ${index === 0 ? 'is-featured' : ''}`} key={service.title}>
                            <span className="hoa-service-mark" aria-hidden="true"><ServiceIcon name={service.icon} /></span>
                            <div className="hoa-service-content">
                                <h3>{t(service.title)}</h3>
                                <p>{t(service.description)}</p>
                            </div>
                            <span className="hoa-card-index" aria-hidden="true">0{index + 1}</span>
                        </article>
                    ))}
                </div>
            </div>
        </section>
    );
}
