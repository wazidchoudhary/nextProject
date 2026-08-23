# Theme images

Files committed here ship with the theme. Keep it to a handful: anything a shop
owner might want to change belongs in the media library, not in a deploy.

## hero-banner

The home page hero looks for a banner in three places, in order:

1. The media-library attachment set under **Bone Horn Crafts → Settings →
   Home page banner** (or `wp bhc setup hero <file>`).
2. `hero-banner.webp`, `.jpg`, `.jpeg` or `.png` in this folder.
3. Failing both, the section's own gradient.

Tier 2 exists so a fresh install — or a rebuilt staging site with an empty
uploads folder — still looks finished before anybody opens the settings screen.
Drop a file in with one of those names and it is picked up automatically;
nothing else needs changing.

### What the image needs to be

* **Landscape and wide.** Roughly 2.5:1. The supplied banner is 1965 × 788.
* **Subject to the right, space to the left.** The headline is set over the left
  of the image on desktop, and the crop holds the right edge as the window
  narrows — so the left is the part that gets given up.
* **Dark, or tolerant of being darkened.** A scrim is laid over it to guarantee
  text contrast, so a bright image will read considerably darker than the file.
* **WebP if you can.** This is the page's LCP element and typically the largest
  file the home page loads. WebP is usually half the bytes of the equivalent
  JPEG, and a photographic PNG can be several times larger than either.

Convert with:

    cwebp -q 82 hero.png -o hero-banner.webp

Aim for under 300KB. Anything over about 500KB is worth another look.
