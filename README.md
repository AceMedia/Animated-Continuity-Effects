# Animated Continuity Effects

Animated Continuity Effects adds configurable View Transition API support to WordPress.

## Features

- Set custom CSS variables for transition duration, timing function, delay, and related motion controls.
- Choose from multiple root animation styles, including fade, side slide, zoom, rotate, and 3D flip.
- Exclude specific classes or IDs with a comma-separated list.
- Apply shared element transitions to common WordPress surfaces such as featured images, site logos, titles, and avatars.
- Mark persistent page regions like headers, footers, navigation, and sidebars so they stay visually stable during navigation.
- Skip transitions automatically when excluded elements are present on the source or destination page.

## Installation

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Configure the settings under `Settings` > `Animated Continuity Effects`.

## Notes

- The plugin uses the View Transition API where the browser supports it.
- Shared element transitions rely on stable selectors in the active theme or block markup.
