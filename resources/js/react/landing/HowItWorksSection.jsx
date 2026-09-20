import React from 'react';

const steps = [
    ['Register', 'Create your resident account with the household and contact details the HOA needs to verify.'],
    ['Verify', 'Confirm your email while HOA officers review and activate your Southville Phase I record.'],
    ['Access your panel', 'Sign in securely to the dedicated homeowner panel from your phone, tablet, or computer.'],
    ['Manage everything', 'Review dues, submit concerns and requests, follow updates, and download approved documents.'],
];

export default function HowItWorksSection() {
    return (
        <section id="how-it-works" className="hoa-section hoa-process" aria-labelledby="process-title">
            <div className="hoa-container">
                <div className="hoa-section-heading" data-reveal-group>
                    <p data-reveal className="hoa-eyebrow"><span /> How it works</p>
                    <h2 data-reveal id="process-title">From registration to everyday HOA service.</h2>
                    <p data-reveal>Four clear steps connect verified Southville Phase I residents with their records and community services.</p>
                </div>
                <ol className="hoa-process-list" data-reveal-group>
                    {steps.map(([title, description], index) => (
                        <li data-reveal key={title}>
                            <span className="hoa-process-number" aria-hidden="true">{String(index + 1).padStart(2, '0')}</span>
                            <div>
                                <h3>{title}</h3>
                                <p>{description}</p>
                            </div>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}
