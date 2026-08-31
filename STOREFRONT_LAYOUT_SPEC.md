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

The owner chose **5 / 4 / 3**, confirming the earlier brief over this spec's
4 / 3 / 2. Implemented and verified in Chromium.

| Surface | Desktop | Tablet / iPad | Mobile |
|---|---|---|---|
| Categories (`.category-scroller`, `.category-grid`) | 5 | 4 | 3 |
| Services / products (`.product-grid`, `.service-grid`) | 5 | 4 | 3 |

Breakpoints: 5 above 1000px · 4 at 1000–761px · 3 at 760px and below.
Applies to categories, subcategories, services, products, offers and stores
alike. **Real breakpoints only — never page zoom.**

Measured card widths: 235px at 1440 · 221px at 1200 · 186px at 1024 ·
172px at 768 · 115px at 390 · 105px at 360. No horizontal overflow and no
overlap at 360px.

Because three cards on a 360px screen leaves ~105px each, the mobile card is
compact: 9px padding, the name clamped to two lines at a fixed 32px height so
every card in a row keeps the same height and baseline, the description hidden,
and the price and CTA stacked with the CTA at full card width.

**Known edge:** a tablet in landscape at 1024px gets 5 columns, since the
breakpoint is 1000px. Every iPad in portrait (768–834px) gets 4. Raising the
breakpoint to 1024px is a one-line change if landscape should also show 4.

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
