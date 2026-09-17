DESCRIPTION OF FONT IMPORT INTO MOD_PLAYERCARDS
------------------------------------------------

Both families are downloaded from Google Fonts (https://fonts.google.com), self-hosted here
instead of loaded from fonts.googleapis.com at runtime (see thirdpartylibs.xml — a bundled
library must ship inside the plugin, not be fetched from a CDN/external host).

Only the Latin subset (U+0000-00FF, covers English and Portuguese diacritics) of each weight
actually used by styles.css was downloaded — not the full variable font family.

Cinzel (display face, card names and section headings):
  - Weight 600 (semibold) only.
  - Source: https://fonts.google.com/specimen/Cinzel
  - File: cinzel-semibold.woff2

Archivo (body/UI face, labels and stats):
  - Weights 400 (regular) and 700 (bold) only.
  - Source: https://fonts.google.com/specimen/Archivo
  - Files: archivo-regular.woff2, archivo-bold.woff2

To re-download (or add a weight), fetch the CSS with an old-browser User-Agent so Google
returns static per-weight files instead of a variable font bundle, then take the "latin"
(U+0000-00FF) block's URL for the weight needed:

  curl -A "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) \
    Chrome/60.0.3112.113 Safari/537.36" \
    "https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,700&family=Cinzel:wght@600"

Both families are licensed under the SIL Open Font License 1.1 — see OFL-Archivo.txt and
OFL-Cinzel.txt in this same folder.
