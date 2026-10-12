# Dashboard: Colours and Fonts

The dashboard's visual scheme, read from src/styles/tokens.css,
src/styles/globals.css and index.html. It mirrors the public website's
tokens (../qayema/public/portal/css/app.css), so the dashboard and the site
look like one product. Change a value in tokens.css, then update this file.

## Fonts

| Use | Font | Weights | Fallbacks |
|---|---|---|---|
| Headings (page titles, big numbers) | *Instrument Serif* | 400, italic | El Messiri, Georgia, Times New Roman, serif |
| Body (the English interface) | *Geist* | 300, 400, 500, 600, 700 | El Messiri, ui-sans-serif, system-ui, sans-serif |
| Arabic (the whole interface in Arabic) | *El Messiri* | 400, 500, 600, 700 | Geist, system-ui, sans-serif |
| Monospace | system monospace | - | JetBrains Mono, SF Mono, Menlo, monospace |

- All three web fonts load from Google Fonts in index.html, with display=swap.
- Geist has the stylistic features cv11 and ss01 switched on.
- html[lang='ar'] switches the body to El Messiri.

CSS tokens: --font-display, --font-sans, --font-ar, --font-mono.

## Brand colours

The same in both themes.

| Name | Hex | Token | Use |
|---|---|---|---|
| Gold | #F8D38D | --color-gold | Brand accent, primary button |
| Gold deep | #E9BD66 | --color-gold-deep | Primary button on hover |
| Ink | #141109 | --color-ink | Text on gold, light theme text |
| Paper | #FBFAF6 | --color-paper | Light theme background |
| Sand | #E8DCCB | --color-sand | Warm neutral |
| Danger | #B45040 | --color-danger | Filled delete button |

## Light theme

| Role | Value | Token |
|---|---|---|
| Background | #FBFAF6 | --bg |
| Cards and surfaces | #FFFFFF | --surface |
| Input fields | #FBFAF6 | --field |
| Text | #141109 | --text |
| Secondary text | rgba(20, 17, 9, 0.62) | --muted |
| Quieter text | rgba(20, 17, 9, 0.60) | --faint |
| Placeholder | rgba(20, 17, 9, 0.32) | --placeholder |
| Border | rgba(20, 17, 9, 0.10) | --line |
| Strong border | rgba(20, 17, 9, 0.18) | --line-strong |
| Faint divider | rgba(20, 17, 9, 0.06) | --line-2 |
| Hover tint | rgba(20, 17, 9, 0.04) | --hover-wash |
| Disabled tint | rgba(20, 17, 9, 0.04) | --disabled-wash |
| Focus outline | #B08A2C | --gold-on |
| Gold text | #82631B | --accent-text |
| Gold fill | #F8D38D | --accent-fill |
| Gold tint (selected) | rgba(233, 189, 102, 0.16) | --accent-wash |
| Gold tint on hover | rgba(233, 189, 102, 0.24) | --accent-wash-hover |
| Gold border | rgba(233, 189, 102, 0.55) | --accent-border |

## Dark theme

| Role | Value | Token |
|---|---|---|
| Background | #000000 | --bg |
| Cards and surfaces | #0A0A0A | --surface |
| Input fields | #111111 | --field |
| Text | #F5F1E8 | --text |
| Secondary text | rgba(245, 241, 232, 0.60) | --muted |
| Quieter text | rgba(245, 241, 232, 0.55) | --faint |
| Placeholder | rgba(245, 241, 232, 0.32) | --placeholder |
| Border | rgba(245, 241, 232, 0.12) | --line |
| Strong border | rgba(245, 241, 232, 0.22) | --line-strong |
| Faint divider | rgba(245, 241, 232, 0.06) | --line-2 |
| Hover tint | rgba(245, 241, 232, 0.06) | --hover-wash |
| Disabled tint | rgba(245, 241, 232, 0.04) | --disabled-wash |
| Focus outline | #F8D38D | --gold-on |
| Gold text | #F8D38D | --accent-text |
| Gold fill (toned down on black) | #C39B50 | --accent-fill |
| Gold tint (selected) | rgba(248, 211, 141, 0.12) | --accent-wash |
| Gold tint on hover | rgba(248, 211, 141, 0.18) | --accent-wash-hover |
| Gold border | rgba(248, 211, 141, 0.35) | --accent-border |

On black the bright gold is the loudest thing on screen, so gold fills step
down to #C39B50. The primary button keeps the brand gold.

## Status colours

| Status | Light | Dark | Token |
|---|---|---|---|
| Danger | #A3483A | #E8907F | --status-danger |
| Warning | #9A5B12 | #F0B354 | --status-warn |
| Success | #506C46 | #A3C491 | --status-success |
| Info | #4A6B86 | #93B8D6 | --status-info |

Each status also has a tinted background (-wash) and a border (-border)
in its own colour:

| | Light | Dark |
|---|---|---|
| Tinted background (--status-*-wash) | 10% | 14% |
| Border (--status-*-border) | 30% | 38% |

## Focus rings

| Ring | Light | Dark | Token |
|---|---|---|---|
| Accent | rgba(248, 211, 141, 0.35) | rgba(248, 211, 141, 0.28) | --ring-accent |
| Danger | rgba(180, 80, 64, 0.10) | rgba(180, 80, 64, 0.22) | --ring-danger |
| Success | rgba(92, 122, 79, 0.12) | rgba(163, 196, 145, 0.22) | --ring-success |

## Overlays on photos

| Role | Light | Dark | Token |
|---|---|---|---|
| Dark badge (always white text) | rgba(20, 17, 9, 0.78) | rgba(0, 0, 0, 0.70) | --overlay-pill |
| Control on a photo | rgba(251, 250, 246, 0.92) | rgba(10, 10, 10, 0.78) | --overlay-chip |
| Control on hover | rgb(251, 250, 246) | rgba(10, 10, 10, 0.92) | --overlay-chip-hover |
| Control's text | #141109 | #F5F1E8 | --overlay-chip-ink |

## Shadows

| Shadow | Light | Dark | Token |
|---|---|---|---|
| Popups and menus | 0 24px 60px -20px rgba(20, 17, 9, 0.35), 0 4px 12px rgba(20, 17, 9, 0.06) | 0 24px 60px -20px rgba(0, 0, 0, 0.85), 0 0 0 0.5px rgba(245, 241, 232, 0.08) | --pop-shadow |
| Raised cards | 0 12px 32px -8px rgba(20, 17, 9, 0.18), 0 2px 8px -2px rgba(20, 17, 9, 0.08) | 0 12px 32px -8px rgba(0, 0, 0, 0.70), 0 2px 8px -2px rgba(0, 0, 0, 0.50) | --lift-shadow |

## Shapes and motion

| | Value | Token |
|---|---|---|
| Input field corners | 12px | --radius-field |
| Button and small control corners | 10px | --radius-control |
| Easing | cubic-bezier(0.22, 0.61, 0.36, 1) | --ease-qayema |

## Rules

- The theme switches on html[data-theme] (light or dark); light is the
  default when the attribute is absent.
- Every text colour reads at 4.5:1 or more (WCAG AA) on the surfaces and
  tints it sits on. src/styles/tokens.test.ts checks it.
- In components, use the tokens (var(--muted), text-accent,
  bg-status-warn-wash), never a hex value, so both themes stay right.
- Tailwind utilities read the theme tokens: bg-field, text-accent,
  bg-accent-fill, bg-accent-wash, border-accent-border,
  text-status-warn, bg-status-warn-wash, shadow-pop, shadow-lift.
