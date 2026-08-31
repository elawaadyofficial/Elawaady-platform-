# Section banners

Drop the finished banner artwork here. The storefront places the file as-is:
it does not crop it, does not force an aspect ratio, and draws nothing of its
own on top of it.

## Naming

    <section-slug>-desktop.<ext>      e.g. verification-desktop.png
    <section-slug>-mobile.<ext>       optional, used below 560px

`webp` or `png` for artwork with transparency, `jpg` for full-bleed artwork
with no transparent edges.

## Size

Open. Any width and any height. The banner keeps the artwork's own proportions
at every screen size, so the shape you export is the shape that ships.

Export at roughly twice the display width so it stays sharp on high-density
screens — around 2400px wide for a full-width banner is plenty.

If the artwork has rounded corners or elements that break past the edge, bake
them into a transparent PNG or WebP. The page behind stays transparent and no
container is drawn around the file.
