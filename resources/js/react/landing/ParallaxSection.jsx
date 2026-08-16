import React from 'react';

export default function ParallaxSection() {
    return <section data-parallax-section className="hoa-parallax" aria-labelledby="parallax-title"><div data-parallax-back className="hoa-parallax-orb hoa-parallax-orb-one" /><div data-parallax-front className="hoa-parallax-orb hoa-parallax-orb-two" /><div className="hoa-container hoa-parallax-content" data-reveal-group><p data-reveal className="hoa-eyebrow hoa-eyebrow-light"><span /> One connected community</p><h2 data-reveal id="parallax-title">Better information creates better neighborhoods.</h2><p data-reveal>When residents and HOA officers share reliable records and timely updates, everyone can make informed decisions and move community concerns forward.</p></div></section>;
}
