# EXD — standing design rules

Decisions from the project owner that hold across every page. Read this before
styling anything.

## 1. Every service and every subcategory has its own artwork

Not an icon. The owner produces a dedicated image for each one, and the card is
built around that image, not around a glyph.

- Services read `store_services.image`.
- Subcategories read `store_subcategories.image`, added by
  `migrations/002_subcategory_image.sql`.
- Artwork is square, 1:1, and runs to the card edges with no inset padding.
- The emoji icon stays only as the fallback for a row with no artwork yet, and
  is hidden the moment artwork exists.

Never build a card whose main visual is an icon tile when artwork is the plan.

## 2. Restraint with colour

The brand gradient — violet → magenta → orange → gold — is loud on purpose, so
it is spent on **one thing per view: the action that costs money.**

- `اطلب الآن` / `شراء الآن` carry the gradient.
- Everything else is quiet: outlined buttons, muted badges on
  `--exd-bg-inset`, a dark tile for the fallback icon.
- Browsing is not buying. A "view section" button is secondary, never gradient.

Sampled from the live store for reference: badges sit at `#4b287c`, the details
button at `#250f4d`, card grounds at `#1f153a`. Those are the surrounding tones
the gradient is meant to punctuate — it stops reading as an accent once more
than one element in a card carries it.

## 3. Never invent artwork

Media slots stay empty until the owner supplies files. No generated decoration,
no guessed service-to-image mapping. An empty slot is correct; something made
up is not.
