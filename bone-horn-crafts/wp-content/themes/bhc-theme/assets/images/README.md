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

### Sizes

A bundled file has no attachment record, so WordPress cannot build a srcset for
it and a phone would otherwise download the full-width image. Commit narrower
copies beside the base file and they are picked up automatically:

    hero-banner.webp        full width, and the srcset's largest candidate
    hero-banner-1440.webp
    hero-banner-960.webp

Only 960 and 1440 are looked for. Add or remove either and the srcset follows.

Convert with:

    cwebp -q 82 hero.png -o hero-banner.webp
    cwebp -q 82 -resize 1440 0 hero.png -o hero-banner-1440.webp
    cwebp -q 82 -resize 960 0 hero.png -o hero-banner-960.webp

The banner shipped here went from a 1.68MB PNG to 82KB at full width. Aim for
under 300KB; anything over about 500KB is worth another look.

### On small screens

Below 1024px the banner is not a background. A 2.5:1 photograph cropped into a
phone-shaped box shows a narrow vertical slice and stops being recognisable, so
the copy sits on a solid ground and the banner runs beneath it at close to its
own ratio, whole.
