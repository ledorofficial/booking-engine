# Karuna Booking Search

A WordPress plugin for **karunasiargao.com** — a branded availability search that
replaces the Smoobu embed. A compact one-month calendar (live nightly prices,
sold-out nights greyed out) that sends the visitor to:

```
https://bookings.karunasiargao.com/?arrival=YYYY-MM-DD&departure=YYYY-MM-DD&guests=N#rooms
```

## Install

1. Download the latest zip from **[Releases](../../releases)** (or *Code → Download
   ZIP* and rename the folder to `karuna-booking-search`).
2. WP Admin → **Plugins → Add New → Upload Plugin** → the zip → **Install** →
   **Activate**.
3. Place it:
   - **Search shortcode:** `[karuna_booking_search]` — in Elementor use a
     *Shortcode* element (never a Text / Heading / HTML widget)
   - **Per-room calendar shortcode:** `[karuna_room_calendar room_id="123"]` —
     same rule, use a *Shortcode* element
   - **Widget:** *Appearance → Widgets → Karuna Booking Search*

## Updates

The plugin checks this repo's **[Releases](../../releases)**. When a release has a
higher version than what's installed, WordPress shows the normal **update
available** notice in *Plugins* and updates with one click — no re-upload.
(WordPress checks automatically every ~12h; force it from *Dashboard → Updates →
Check again*.)

Shipping an update = bump the `Version:` header in `karuna-booking-search.php`
and push to `main`. A GitHub Actions workflow
(`.github/workflows/release.yml`) then tags and publishes the matching
`vX.Y.Z` release with a zip automatically — no manual release step needed.

Uses [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
(bundled in `plugin-update-checker/`). Works token-free while this repo is public.

## Using it on another property's website

One install of the booking engine serves several brands, each on its own
domain. To use this plugin on, say, the El Nido glamping WordPress site, install
it there and point `base` at that property's booking domain:

```
[karuna_booking_search base="https://bookings.glampingatkarunaelnido.com/"]
[karuna_room_calendar  base="https://bookings.glampingatkarunaelnido.com/" room_id="123"]
```

The booking site only returns that property's own rooms, prices and availability,
and only allows requests from its own WordPress domain (CORS) — so the WordPress
site's address must be listed in that property's CORS origins on the booking
engine. The paste-in HTML snippet takes the same change via its `action` and
`data-kbs-api` attributes.

## Shortcode attributes

| Attribute    | Default                              | Notes                                             |
|--------------|--------------------------------------|---------------------------------------------------|
| `base`       | `https://bookings.karunasiargao.com/`| The booking site: where the search submits **and** where prices / sold-out nights are read from (`<base>api/calendar`). |
| `target`     | `_self`                              | `_blank` opens results in a new tab.               |
| `max_guests` | `16`                                 | Ceiling for Adults + Children in the Guests picker.|
| `prices`     | `on`                                 | `off` hides the per-night prices.                  |
| `layout`     | `stacked`                            | `stacked` = teal card; `inline` = light wide row.  |
| `button`     | `Search`                             | Submit button label.                              |

```
[karuna_booking_search max_guests="4"]                       Skygazer (sleeps 4)
[karuna_booking_search prices="off"]                         calendar without prices
[karuna_booking_search layout="inline" button="Check availability"]   homepage row
```

**Layouts:** `stacked` is the compact teal card for a sidebar (like the Smoobu
widget). `inline` is a full-width white row — Arrival · Departure · Guests ·
button — for a homepage hero on a light background; it wraps to two columns
below 640px.

## Room calendar shortcode

`[karuna_room_calendar]` — a read-only, branded replacement for Smoobu's
embeddable per-apartment calendar iframe (no date picking, no form; just
shows sold-out nights). Place one per room page.

| Attribute | Default                               | Notes                                    |
|-----------|----------------------------------------|-------------------------------------------|
| `room_id` | *(required)*                          | The room's Smoobu apartment ID.           |
| `months`  | `6`                                    | Total months of data fetched; 2 shown at a time, paged with the `›`/`‹` arrows. |
| `base`    | `https://bookings.karunasiargao.com/` | Where the calendar JSON is fetched from.  |

```
[karuna_room_calendar room_id="3492081"]
```

Find a room's Smoobu apartment ID in the Smoobu dashboard, or in this plugin's
existing search results (the booking engine's admin/rooms list uses the same
IDs).

## Backend dependency

The search widget's price calendar reads `GET /api/calendar` on the booking
engine — the lowest-nightly-rate map, as JSON, with an
`Access-Control-Allow-Origin` header for `karunasiargao.com`. If that endpoint
or flatpickr's CDN is unreachable the widget falls back to plain native date
fields, so it always works.

The room calendar shortcode reads `GET /api/calendar/room/{apartmentId}` —
that room's own sold-out nights, same CORS treatment, no flatpickr dependency
(a plain read-only grid).

Both endpoints live in the booking-engine repo (`bookings.karunasiargao.com`),
origins controlled by `CORS_ORIGINS`.

## No-plugin alternative

`elementor-html-widget.html` is the whole widget as one paste-in block (markup +
`<style>` + `<script>`, flatpickr from cdnjs) for an Elementor **HTML** widget or
a **Custom HTML** block — but it does not self-update.

## Files

| Path | Purpose |
|---|---|
| `karuna-booking-search.php` | Plugin bootstrap, shortcodes, widget, update checker. |
| `assets/kbs.css`, `assets/kbs.js` | Search widget styles + logic (`__KBS_API__` swapped for the endpoint URL at runtime). |
| `assets/kbs-room.css`, `assets/kbs-room.js` | Room calendar shortcode styles + logic (`__KBS_API__` swapped the same way). |
| `elementor-html-widget.html` | Standalone paste-in copy of the search widget only — keep in sync with `assets/kbs.*`. No standalone copy of the room calendar; shortcode-only. |
| `plugin-update-checker/` | Bundled updater library (MIT). |

## Styling

Brand tokens: brown `#9D6B2B`, dark teal `#00333D`, sand `#F0EADC`, font
**Trirong**. Override `.kbs-widget` / `.flatpickr-calendar.kbs-fp` in the theme's
Additional CSS.
