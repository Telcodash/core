# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Staff of a telecoms business, organised into teams. Four roles, in ascending level:

- **Advisor / Advisor+** – front-line staff working inside the product for most of a shift.
- **Manager** – runs one or more teams; watches team activity and performance.
- **Administrator** – manages users, teams and roles across the whole organisation (Admin area).

## Product Purpose

TelcoDash is the internal operations platform for a telecoms business. It will eventually cover three jobs:
sales (advisors selling telecom products, managers tracking targets), support (advisors handling customer
contact, managers watching queues), and internal operations (managing accounts, lines and provisioning).

Current scope: authentication (password, passkeys, 2FA), teams and membership, role-based access, settings,
and an admin area for users and teams. Domain features (sales, support, ops) are not built yet.

## Operating Context

- Used for long stretches during a working day, on desktop monitors in an office or at home; occasionally on a phone.
- Everything is team-scoped: URLs are prefixed with the current team slug; users switch teams from the sidebar.
- Administrators work in a separate Admin area with its own navigation.

## Capabilities and Constraints

- Stack: Laravel 13, Livewire 4 single-file components, Flux UI, Tailwind CSS v4, Vite (vite-plus).
- Registration is disabled; accounts are created by administrators.
- Theme follows the operating system by default; users can override in Settings → Appearance.

## Brand Commitments

- Product name: **TelcoDash**.
- No existing logo or brand assets; the mark is the product's own (signal bars).

## Evidence on Hand

No real customer, sales or support data exists yet. Do not fabricate metrics, KPIs or charts on product
surfaces; show honest empty states until real data is wired up.

## Product Principles

1. The tool disappears into the task: familiar controls, predictable navigation, no ornament that slows work.
2. One shell for three jobs: the base design must suit sales, support and ops screens equally.
3. Calm over loud: colour is reserved for state, selection and primary actions.
4. Honest states: empty, loading and error states teach the next step instead of decorating absence.
