# Simple Portfolio Grid

A lightweight WordPress plugin that adds a "Projects" post type, a responsive portfolio grid shortcode, and a two-column single-project page (images on one side, text on the other).

Distributed and updated via this GitHub repository — no WordPress.org listing.

## Features

- A "Projects" post type with title, content, featured image, and a gallery of extra images.
- A drag-to-reorder image picker built on the native WordPress media library.
- The `[portfolio]` shortcode renders a responsive grid of all projects, linking each to its own page. Use `[portfolio columns="4"]` to change the column count (default 3).
- Each project gets an automatic two-column page: images on one side, title/content on the other.
- Ships its own updater (bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)) so installed sites get "Update Now" in wp-admin whenever a new tag is pushed here — no manual re-upload.

## Installation

1. Download the latest release zip from the [Releases page](https://github.com/chatgpta67-blip/simple-portfolio-grid/releases), or clone this repo.
2. Upload the plugin folder to `/wp-content/plugins/simple-portfolio-grid/` (or zip it and use Plugins → Add New → Upload Plugin in wp-admin).
3. Activate it through the Plugins screen.
4. Add projects from the new "Projects" menu, then place `[portfolio]` on any page.

## Shipping an update

1. Make your code changes.
2. Bump `Version:` in `simple-portfolio-grid.php`.
3. Commit, then tag and push:
   ```
   git tag vX.Y.Z
   git push origin main --tags
   ```
4. Every site with the plugin installed will see "Update Now" in wp-admin within about 12 hours (or immediately if someone clicks "Check for updates" on the Plugins page).

## Customization

- Copy `single-spg_project.php` into your active theme to override the single-project template.
- The "Back to Featured Works" link looks for a page with the slug `featuredworks`, falling back to the homepage. Change the destination with the `spg_back_link_url` filter.
