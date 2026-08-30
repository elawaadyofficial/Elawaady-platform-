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

## 2. Grid counts — DECISION PENDING

Two instructions from the owner conflict. Nothing is changed until this is
resolved.

| Surface | Option A (this spec) | Option B (owner's earlier brief) | Currently built |
|---|---|---|---|
| Desktop / laptop | 4 per row | **5 per row** | 4 |
| Tablet / iPad | 3 per row | **4 per row** | 3 |
| Mobile | 2 per row | **3 per row** | 2 |

Option B was stated as a minimum ("3 كروت جنب بعض كحد أدنى" on mobile) and applies
to categories, subcategories, services, products, offers and stores alike.
Option A matches what `storefront.css` already ships.

Card width follows from the choice: at a 1200px container with 20px gaps,
4-across is ~285px and 5-across is ~208px. The compact mobile card (image,
title, price, badge, button, line-clamped name, fixed height, aligned baseline)
is required under either option, and is tighter under Option B.

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
