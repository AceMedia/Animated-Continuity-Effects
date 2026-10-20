# Animated Continuity Effects

Animated Continuity Effects adds configurable View Transition API support to WordPress. Page changes animate smoothly instead of cutting, and chosen elements such as the featured image, site logo or header can carry across between pages.

**Status: early-stage (version 0.1.0).** It works in browsers that support the View Transition API and does nothing special elsewhere.

## Features

- Cross-document transitions (the default) or a same-document mode, with an experimental router that can intercept normal internal links (off by default).
- Motion presets: Gentle, Airy, Dramatic and Slow, plus custom duration, timing function and delay (exposed as CSS variables).
- Root animation styles: Fade, Slide up, Slide from right, Slide from left, Zoom, 3D flip and Rotate.
- Shared element transitions for the featured image, site logo, entry title and avatar.
- Persistent regions that stay visually stable during navigation: site header, site footer, primary navigation and sidebar.
- Exclude classes or IDs with a comma-separated list, and add advanced selectors. Transitions are skipped when excluded elements are present on the source or destination page.
- Option to respect the visitor's reduced-motion preference (on by default).
- Name scope setting for generated transition names (post by default).

## Requirements

- A recent WordPress release (the plugin header does not declare minimum WordPress or PHP versions).
- A browser with View Transition API support for visitors to see the effect.

## Installation

### From a release

1. Download the zip from the [latest GitHub Release](https://github.com/AceMedia/Animated-Continuity-Effects/releases/latest).
2. In WordPress, go to **Plugins > Add New > Upload Plugin**, choose the zip and install.
3. Activate **Animated Continuity Effects**.

### From source

There is no build step. Clone the repository into `wp-content/plugins/` and activate it:

```bash
git clone https://github.com/AceMedia/Animated-Continuity-Effects.git
```

## Usage

1. Open **Continuity Effects** in the admin menu (also under **Settings > Animated Continuity Effects**).
2. Pick a motion preset and animation style.
3. Tick the shared elements and persistent regions you want.
4. Add any exclusions, then save. Saving flushes the object cache.

## Configuration

All settings are stored in one option, `ace_settings`. Deactivating the plugin keeps your settings.

Shared element transitions rely on stable selectors in your theme or block markup. If an element does not animate, check that it matches the selector for that preset or add your own under advanced selectors.

## Development

Plain PHP, CSS and JavaScript with no package manager:

- `animated-continuity-effects.php`: bootstrap
- `includes/class-ace-plugin.php`: settings, admin page and server-side transition names
- `assets/js/frontend.js` and `assets/css/frontend.css`: front-end behaviour and styles

## Licence

GPL-2.0-or-later. See the `LICENSE` file.

## Author and repository

Shane Rounce, [AceMedia](https://acemedia.ninja).
Repository: https://github.com/AceMedia/Animated-Continuity-Effects
