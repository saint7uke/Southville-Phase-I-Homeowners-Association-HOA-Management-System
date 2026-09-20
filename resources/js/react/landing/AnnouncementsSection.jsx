import React from 'react';

function readableDate(value) {
    if (!value) return 'Community update';
    return new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value));
}

export default function AnnouncementsSection({ announcements }) {
    return <section id="announcements" className="hoa-section hoa-announcements" aria-labelledby="announcements-title"><div className="hoa-container"><div className="hoa-section-heading hoa-heading-row" data-reveal-group><div><p data-reveal className="hoa-eyebrow"><span /> Latest announcements</p><h2 data-reveal id="announcements-title">What residents need to know.</h2></div><a data-reveal href="/homeowner/login" className="hoa-text-link">View resident updates <span aria-hidden="true">→</span></a></div>{announcements.length > 0 ? <div className="hoa-announcement-grid" data-reveal-group>{announcements.map((announcement, index) => <article data-reveal className="hoa-announcement-card" key={announcement.id}><div><span className="hoa-announcement-category">{announcement.category || 'HOA advisory'}</span><time dateTime={announcement.published_at || announcement.created_at}>{readableDate(announcement.published_at || announcement.created_at)}</time></div><h3>{announcement.title}</h3><p>{announcement.excerpt || announcement.content?.replace(/<[^>]*>/g, '').slice(0, 145) || 'Sign in to the resident portal to read this community update.'}</p><span className="hoa-card-index" aria-hidden="true">0{index + 1}</span></article>)}</div> : <div className="hoa-empty-state"><strong>No new public announcements.</strong><p>Resident-specific notices remain available in the secure portal.</p></div>}</div></section>;
}
