# Making a TorrentFlux theme

A theme is one folder under `html/themes/`. Everything the site shows — colours, fonts,
icons, images — comes from the active theme's folder, so a new theme never needs a PHP change.
Drop a folder in, and it appears in **Profile → Theme** and **Admin → Settings → Default Theme**.

Six themes ship with this build:

| Theme | Look | Icon style |
|---|---|---|
| `clay` | warm charcoal, terracotta, serif headings | outlined, AI glyphs |
| `mint` | dark slate, bright blue | rounded |
| `network` | light grey console, white tables, teal | outlined, network glyphs |
| `sage` | mid-2000s sage greens, Verdana | rounded, muted |
| `walnut` | warm charcoal, bronze | outlined, bronze |
| `workshop` | safety orange, charcoal | rounded, tool glyphs |

## Quick start

1. **Copy a theme that is close to what you want** (a dark theme for a dark idea, `workshop`
   for a light one). Copying gives you every file the site expects.

   ```bash
   cp -r html/themes/walnut html/themes/mytheme
   ```

   The folder name is the theme's name. Use a plain name such as letters, digits, `-` or `_`.
   TorrentFlux rejects a name that starts with a dot or contains a slash.

2. **Set the panel colours** in `mytheme/index.php` (see below).
3. **Restyle** `mytheme/style.css` — change the palette, fonts and the hooks listed below.
4. **Recolour the icons** with the build scripts (optional, see *Icons*).
5. **Test** every page (checklist at the end), then pick the theme in **Profile → Theme**.

## What is in a theme folder

```
themes/mytheme/
├── index.php      panel colours the PHP writes into the HTML
├── style.css      everything else: fonts, colours, layout hooks
├── dtree.css      the file tree on the torrent details page
└── images/        every image and icon (same file names in every theme)
    ├── files/     file-type icons, one per extension (mkv.png, iso.png, …, unknown.png)
    └── dtree/     file-tree lines and folders
```

The PHP builds image paths with `ThemeImages()` (`themes/<active theme>/images/`), so a
missing image in your theme shows as a broken image — copy the full folder rather than
starting empty.

## index.php — panel colours

TorrentFlux writes these straight into the HTML as `bgcolor` / `bordercolor` attributes:

```php
$cfg["main_bgcolor"]       = "#1e1c1b";  // page background
$cfg["table_data_bg"]      = "#2a2725";  // panels and table rows
$cfg["table_border_dk"]    = "#3a3633";  // table borders
$cfg["table_header_bg"]    = "#2a2725";  // ALSO the main page's upload and status panels
$cfg["table_admin_border"] = "#3a3633";  // admin / profile table borders
$cfg["body_data_bg"]       = "#2a2725";  // login box body
$cfg["bgLight"]            = "#2a2725";  // Directory list: alternating rows
$cfg["bgDark"]             = "#2f2c29";
```

> **Gotcha:** `table_header_bg` is used for section heads *and* for the big upload/search
> and status panels on the main page. Keep it the same as your panel colour, and give
> section heads their colour in `style.css` (they carry `background="…bar.gif"`, which the
> CSS below targets). Otherwise the main page's labels end up dark-on-dark.

Any CSS rule beats these attributes, so `style.css` can always override them.

## style.css — the hooks every theme needs

Copy these blocks from an existing theme's `style.css` and change the colours. They are
the parts of the old 2008 markup that need styling to look modern.

| What | Selector | Notes |
|---|---|---|
| Base text, links, inputs, buttons | `BODY`, `TD`, `A:link`…, `INPUT`, `INPUT[type=submit]` | Fonts can be loaded with an `@import` from Google Fonts at the top |
| Section heads | `TD[background*="bar.gif"]` and `TD[background*="bar.gif"] *` | Every boxed title bar ("User Details", "Activity Log") |
| Page header | the two long `… TR:first-child > TD[background*="bar.gif"]` / `BODY > TABLE TD[colspan="2"][background*="bar.gif"]` selectors | The top bar with the site title and menu |
| Menu links | `A.tf-nav` | Home · Directory · History · Profile · Messages · Admin · Logout |
| Admin sub-menu | `TD[background*="bar.gif"]:has(> DIV > A > FONT.adminlink)` and `… FONT.adminlink` | admin \| settings \| queue … |
| Table column headings | `TD:not([background]):has(> DIV.title)` and `TD:not([background]) > DIV.title` | "User", "Hits", "File", "Time Stamp" |
| Title text | `.title`, `.titleblack` | `.title` is used both in heads and column headings |
| Small text | `.tiny`, `.tinywhite`, `.tinypercent` | `.tinywhite` is the footer — not white on light themes! |
| Progress bars | `TD[background*="proglass.gif"]` (filled), `TD[background*="noglass.gif"]` (empty), `TD[background*="progressbar.gif"]` and `… + TD` | Storage bar, user quota, per-torrent progress |
| Status colours | `.tf-error`, `.tf-success`, `.tf-new`, `.tf-queued`, `.tf-incomplete`, `.tf-close`, `TABLE.tf-messages` | Colours the PHP used to hard-code |
| Login box | `BODY > DIV[align=center] > TABLE[border="1"]:has(FORM[name=theForm])` | Keep the `[border="1"]` — other pages also have a form named `theForm` |
| Page width | `BODY > DIV[align=center] > TABLE[border="0"][cellpadding="0"][cellspacing="0"]` and `TABLE[width="760"], TABLE[width="740"]` | Same 1100px centred column on every page |
| Long names | `DIV[align=center] > DIV.tiny[align=left]` with `OVERFLOW-WRAP: anywhere` | Stops magnet links widening the activity log |
| Make Torrent result | `LABEL#output:has(B.tf-success)`, `LABEL#output:has(B.tf-error)` and `LABEL#output B.tf-success / B.tf-error` | The success / failure box under Create on the Make Torrent page |
| Icon spacing | `A > IMG[align="absmiddle" i], A > IMG[align="bottom" i]` with `MARGIN-RIGHT: 6px` | Room between an icon and its link text (RSS Torrents, Drive Space, user names) |
| Hover popups | `.overCaption`, `.overClose`, `.overBody` | The overlib tooltips |

Header titles get long on admin pages ("TorrentFlux NX - Administration - Queue Manager").
If you use wide letter-spacing, check that they still fit on one line at 1280px.

## Icons

Every theme has the same icon file names at the same pixel sizes — the PHP hard-codes
`width`/`height` on most `<img>` tags. Scripts in `docs/theme-tools/<theme>/` (one folder per
built-in theme, with that theme's palette) render them from Google's Material Icons (Apache-2.0):

| Script | Builds | Usage |
|---|---|---|
| `build-flat-icons.sh` | the 47 interface icons in `images/` | `bash build-flat-icons.sh <svg-cache> <out-dir> <current-images-dir>` |
| `build-file-icons.sh` | the 120 file-type icons in `images/files/` | `bash build-file-icons.sh <svg-cache> <out-dir> <current-files-dir>` |

Requirements: `bash`, `curl`, `rsvg-convert` (librsvg) and ImageMagick (`convert`, `montage`).

To make your own set, edit near the top of each script:

- **Palette** — the colour variables (`BLUE`, `GREEN`, `RED`, `AMBER`, `TEXT`, `DIM`, `OFF`).
- **Style** — the `RAW=` line ends in `round`, `outlined`, `sharp`, `filled` or `two-tone`.
  Use a separate `<svg-cache>` folder per style; the SVG file names are the same in each.
- **Glyphs** — in the `MAP`, the third column is the Material icon name
  (search them at fonts.google.com/icons). Keep columns 1–2 (file name and size) as they are.

Each run writes a before/after sample sheet to `/tmp/flat-sample.png` or
`/tmp/files-sample.png`. The scripts **empty `<out-dir>` first**, so build into a temporary
folder and copy the PNGs into `themes/mytheme/images/` (and `images/files/`) afterwards.

All icons are PNG data even where the file name ends in `.gif` (the PHP refers to the old
names); browsers detect the real format, and PNG keeps smooth edges on any background.

## dtree.css

Styles the file tree on the torrent details page: `.dtree`, `.dtree a`, `.dtree a.node:hover`
and `.dtree a.nodeSel`. Copy one and change the colours; its images come from `images/dtree/`.

## Testing checklist

Switch your own profile to the theme (**Profile → Theme**), press Ctrl+F5, and check:

- [ ] Main page — upload panel labels readable, storage bar, status panel, footer
- [ ] Directory list, History, Profile (and Cookie Management), Messages, RSS
- [ ] Admin home, Activity, Settings, Queue, Links, RSS, New User, Edit User
- [ ] Popups — Run Torrent, Torrent Details (file tree), Server Stats, Drive Space
- [ ] Login page (log out) — only if you make it the default theme
- [ ] No text the same colour as its background, no broken images, no sideways scroll at 1280px

## Licensing and names

- Icons: Material Icons by Google, Apache License 2.0.
- Fonts: loaded from Google Fonts (SIL Open Font License); the visitor's browser fetches them from Google.
- Name and style themes after a *look*, not a brand: use your own colours and glyphs, and
  don't copy a company's logo, wordmark or exact brand colours.
