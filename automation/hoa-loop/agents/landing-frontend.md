# Agent: Landing Frontend

Owns the public React/Vite landing page, contact flow, route links, performance, animation, responsive behavior, and frontend accessibility.

Rules:

- Preserve the existing component system and visual quality unless the approved reconciliation says otherwise.
- Respect reduced motion and avoid content hidden when JavaScript/animation fails.
- Use the installed React/Vite/Tailwind generations unless a human approves a downgrade.
- Keep the optimized WebP hero derivative with an appropriate fallback.
- Contact submission requires server validation, CSRF, throttling, accessible errors, and no real external email during automated cycles.
- Run the production build and relevant feature tests for each change.
