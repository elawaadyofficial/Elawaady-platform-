# EXD — Storefront Layout Spec

Layout geometry for the storefront: container widths, grid counts, media
dimensions, footer structure and carousel behaviour. Supplied by the project
owner 2026-08-30 as a structural reference derived from a competitor storefront.

**Structure and dimensions only.** Per the project's own reference-store rule,
no text, asset, branding or proprietary UI from any reference store enters this
project. The reference's colours, fonts, copy, product names, currency, footer
wording and brand marks are deliberately not carried over; EXD's own identity in
`exd-tokens.css` governs all of it.

---

## 1. Container

| Context | Width |
|---|---|
| Desktop | `max-width: 1200px`, centred, 15px side padding |
| Mobile | 100% |

Matches the storefront's existing `.container`.

## 2. Grid counts — RESOLVED

**4 desktop · 3 tablet / iPad · 2 mobile.** The owner settled on this spec's
counts after briefly trying 5 / 4 / 3; the denser variant was implemented,
measured and then withdrawn.

| Surface | Desktop | Tablet / iPad | Mobile |
|---|---|---|---|
| Categories (`.category-grid`) | 4 | 3 | 2 |
| Services / products (`.product-grid`, `.service-grid`) | 4 | 3 | 2 |

Breakpoints in `storefront.css`: 4 above 1000px · 3 at 1000–761px · 2 at 760px
and below. In `style.css`, which styles the categories, subcategories, service,
search and contact pages: 4 above 900px · 3 at 900–621px · 2 at 620px and below.
`.service-grid` no longer overrides to 3 on desktop, so services and categories
read as one system.

Measured at 4-across: 285px per card at 1440 and 221px at 1200. At 2-across on
a 360px screen each card is about 165px, which is comfortable — the aggressive
compact card (two-line clamp, hidden description, stacked CTA) that three
per row would have required is **not** needed and was reverted with the rest.

`.category-scroller` on the homepage stays the horizontal drag-scroller built
in `motion.css`, with its pointer handlers in `main.js`. It was converted to a
grid only to satisfy the withdrawn three-per-row rule, and is now restored.

**Real responsive breakpoints only — never page zoom.**

## 3. Media dimensions

| Slot | Desktop | Mobile |
|---|---|---|
| Hero carousel | 1200 × 400–500 | 600 × 300 |
| Square banner | 600 × 600 | — |
| Wide banner | 800 × 400 | — |
| Product / service card image | **1:1** | 1:1 |

The 1:1 card ratio is fixed by owner instruction and overrides any other
aspect ratio for cards. Crop and `object-fit` must never distort the source.

Sub-banners lay out on a grid at 2, 3 or 4 per row.

## 4. Footer — 4 columns

`روابط تهمك` · `من نحن` · `طرق الدفع` · `معلومات التواصل`

This is the geometry only. The storefront's actual footer groups are already
defined in `EXD_SOURCE_INVENTORY.md` from EXD's own live store (روابط رسمية ·
الحسابات · قانوني وسياسات) and those groups, not the reference's, supply the
content. The nine policy pages belong in the legal column.

## 5. Carousel

RTL note from the spec, recorded because it is correct and easy to get wrong:
in a sliding-track carousel under `dir="rtl"`, the track must be translated by a
**positive** X offset to advance, because CSS transforms are not mirrored by
writing direction while the flex items are.

It does not apply to the storefront as built: `main.js` crossfades absolutely
positioned `.hero-slide` elements rather than translating a track, so there is
no offset to sign. The existing implementation also carries dot navigation,
arrows, touch swipe, hover pause, a progress indicator, `aria-hidden` per slide
and a `prefers-reduced-motion` path. **It is not to be replaced by a plain
`setInterval` track slider**, which would drop all of the above.

The 5-second autoplay interval in the reference matches what is already built.

## 6. What this spec does not change

- Brand identity, colour ramps, typography and motion stay in `exd-tokens.css`.
  The Year of Handicrafts remains the global family.
- The catalogue stays dark-themed per EXD's identity; the reference's light
  grey ground is not adopted.
- Currency follows the legacy schema default (EGP), not the reference's SAR.
- Event handling stays in `main.js` via listeners; no inline `onclick`.
- No product, price or service copy is taken from any reference store.
