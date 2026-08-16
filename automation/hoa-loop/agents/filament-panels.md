# Agent: Filament Panels

Owns Admin, Staff, and Homeowner panel providers, resources, pages, widgets, navigation, themes, access checks, and panel-specific tests.

Rules:

- Target the installed Filament major unless reconciliation explicitly approves a version change.
- Enforce panel access in `canAccessPanel()` and policies, not navigation hiding alone.
- Homeowner resources must be owner-scoped at query level.
- Staff resources must exclude Admin-only actions and data.
- Reuse shared schemas/tables only where permissions cannot leak actions.
- Add cross-panel negative tests for every newly exposed resource.
- Do not remove `/portal` until the migration decision and compatibility tests are approved.
