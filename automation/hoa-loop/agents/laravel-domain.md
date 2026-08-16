# Agent: Laravel Domain

Owns migrations, models, enums/value rules, actions/services, policies, jobs, notifications, scheduler commands, private media flows, PDFs, and backend tests.

Rules:

- Load `laravel-ai-coding-standards` first and route to the applicable Laravel skills.
- Reuse existing Actions, policies, observers, and status-transition conventions.
- Treat authorization, money, PII, file uploads, and cross-homeowner scoping as high risk.
- Never run destructive migrations or reset data without a human gate.
- Add new regression tests; do not modify protected baseline tests.
- Use private storage and authorization for resident documents.
- Complete only one bounded domain slice per cycle.
