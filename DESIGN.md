# TelcoDash design system

Mode: **Operate**. The interface serves long working sessions across sales, support and ops screens.
The world lends type, palette, density and one signature move; navigation and controls stay standard.

## Direction: "Floating island" on deep ink

- The **island**: navigation lives in a floating, rounded, deep-ink panel detached from the window edge.
  It is ink in both themes, so Flux renders its dark variants inside it (the element carries `.dark`).
- The **canvas**: content sits directly on a tinted canvas, open and borderless. Hierarchy comes from
  large display type, spacing and hairlines, not from cards. Nested boxes are not allowed.
- **Ink pieces**: a few surfaces borrow the island's ink to mark them as personal or "shell"
  (the dashboard account card, the sign-in artwork panel, the welcome hero).

**Signature move: the network.** Nodes joined by lines. It appears as:
- the modules roadmap on the dashboard (nodes joined by a dashed line while nothing is live),
- section headings followed by a hairline that fades out,
- the network artwork (`x-network-art`) with signal travelling along its routes on sign-in and welcome,
- the lit node marking the current item in the island.

## Tokens (resources/css/app.css)

### Colour

Neutrals are an ink-tinted scale (OKLCH hue 275) that replaces Flux's zinc scale.

| Role | Light | Dark |
|---|---|---|
| Canvas | zinc-100 | zinc-950 |
| Island / ink pieces | `--color-ink` oklch(0.215 0.06 275) | same, ringed |
| Hairline | zinc-200 | white / 9% |
| Text strong / body / muted | zinc-950 / zinc-700 / zinc-500 | white / zinc-300 / zinc-400 |
| Electric (accent) | oklch(0.54 0.235 272) | oklch(0.74 0.16 275) |
| Attention (amber) | `--color-attention` oklch(0.80 0.155 72) | same |

- Electric marks primary actions, the current selection and focus only.
- Amber marks things a person must deal with: the admin-area warning, teams without a manager,
  two-factor switched off. Never decoration.
- Role badges keep Flux's colours; avatars use Flux `color="auto"` and are always round.

### Type

- **Bricolage Grotesque** (display): page titles (40px), section headings, Flux headings, big numerals, wordmark.
- **Geist** (UI and body): everything else, 14px base. **Geist Mono** only for codes, IDs, phone numbers.
- Sentence case. No eyebrow labels above headings.

### Shape and depth

- Radius by hierarchy: controls 8px, chips round, ink pieces 20–28px, island 20px.
- Shadows only on the island, ink pieces and overlays (menus, modals, toasts).

### Motion

| Token | Value | Use |
|---|---|---|
| `--ease-out` | `cubic-bezier(0.16, 1, 0.3, 1)` | all arrivals |
| `--dur-fast` | 140ms | hover, press |
| `--dur-base` | 220ms | state change, section rise |
| `--dur-slow` | 640ms | signal-mark entrance |

- `data-rise` on a container: its children settle in, staggered 50ms, on load and after `wire:navigate`.
- The signal mark builds bar by bar once per full page load.
- Network artwork: signal dashes travel the routes; two nodes ping. Stops entirely with reduced motion.
- Buttons press to 97%; stat links rotate their arrow; tabs grow their underline on hover.
- `prefers-reduced-motion`: movement removed, colour and opacity feedback kept.

## Components (resources/views/components)

| Component | Use |
|---|---|
| `x-page-header` | Every page: display title, description, optional `meta` chips and `actions`. |
| `x-section` | Open content group: display heading, optional count, fading hairline, optional actions. |
| `x-form-section` | Form band: heading and description on the left, fields on the right, hairline between bands. `danger` for destructive bands. |
| `x-empty-state` | Dashed outline, icon, one sentence, optional action. |
| `x-meta-chip` | Small round fact chip under a page title. |
| `x-network-art` | Decorative network for ink panels. |
| `x-app-logo` / `x-app-logo-icon` | Wordmark and signal-bar mark on an electric gradient tile. |

Settings uses horizontal tabs with an electric underline; lists use hairline rows, never boxed rows.
