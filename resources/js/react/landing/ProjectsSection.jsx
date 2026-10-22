import React from 'react';
import { useLocale } from '../LocaleContext';

export default function ProjectsSection({ projects }) {
    const { t, locale } = useLocale();
    return <section id="projects" className="hoa-section" aria-labelledby="projects-title"><div className="hoa-container">
        <div className="hoa-section-heading"><h2 id="projects-title">{t('Completed HOA projects')}</h2></div>
        {projects.length ? <div className="hoa-announcement-grid">{projects.map(project => <article className="hoa-announcement-card" key={project.id}>
            {project.images.map(image => <img key={image.url} src={image.url} alt={image.alt} loading="lazy" style={{ width: '100%', height: 'auto', borderRadius: 12 }} />)}
            <h3>{project.title}</h3><time dateTime={project.date}>{new Intl.DateTimeFormat(locale === 'fil' ? 'fil-PH' : 'en-PH', { dateStyle: 'long' }).format(new Date(project.date + 'T00:00:00'))}</time><p style={{ whiteSpace: 'pre-line' }}>{project.description}</p>
        </article>)}</div> : <div className="hoa-empty-state"><strong>{t('No completed projects have been published yet.')}</strong><p>{t('Verified project updates will appear here.')}</p></div>}
    </div></section>;
}
