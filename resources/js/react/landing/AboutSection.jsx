import React from 'react';
import { statistics } from './content';

export default function AboutSection() {
    return <section id="about" className="hoa-section hoa-about" aria-labelledby="about-title"><div className="hoa-container"><div className="hoa-about-copy" data-reveal-group><div data-reveal><p className="hoa-eyebrow"><span /> About the association</p><h2 id="about-title">A community built on participation and trust.</h2></div><div data-reveal className="hoa-rich-copy"><p>The Southville Phase I Homeowners Association supports a well-managed neighborhood where residents stay informed, participate in community life, and receive dependable service.</p><p>Our information system brings essential HOA transactions closer to every household while helping officers maintain accurate and accountable records.</p></div></div><dl className="hoa-stats" data-reveal-group aria-label="Community statistics">{statistics.map((stat) => <div data-reveal key={stat.label}><dt data-counter={stat.value} data-suffix={stat.suffix}>{stat.value}{stat.suffix}</dt><dd>{stat.label}</dd></div>)}</dl></div></section>;
}
