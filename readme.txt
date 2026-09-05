=== Simple Portfolio Grid ===
Contributors: pravinregi
Tags: portfolio, gallery, projects, grid, showcase
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add projects with a title, thumbnail, content and images. Shows a Commercial/Residential tabbed grid via a shortcode, and a two-column page for each project.

== Description ==

Simple Portfolio Grid adds a lightweight "Projects" post type to WordPress, so you can showcase work without a page builder.

**Features**

* A "Projects" post type with title, content, featured image, and a gallery of extra images.
* Each project is tagged Commercial or Residential; the `[portfolio]` shortcode renders both as switchable tabs.
* A drag-to-reorder image picker built on the native WordPress media library — no new uploader to learn.
* Project thumbnails and the images on each project's page have a subtle scroll-parallax effect.
* Each project gets an automatic page: up to three images on one side, title/content on the other.
* No settings screens, no page builder, no external services — everything runs on core WordPress APIs.

**Usage**

1. Add projects from the new "Projects" menu in wp-admin.
2. Upload a featured image, write your content, and use "Add / edit images" to pick the images shown on the project's page.
3. Place `[portfolio]` on any page to show the grid. Use `[portfolio columns="4"]` to change the number of columns (default 3).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through Plugins → Add New in wp-admin.
2. Activate the plugin through the 'Plugins' screen.
3. Add your first project from the new "Projects" menu.
4. Add the `[portfolio]` shortcode to any page to display the grid.

== Frequently Asked Questions ==

= How do I change the number of grid columns? =

Use the `columns` attribute on the shortcode, e.g. `[portfolio columns="4"]`. It defaults to 3.

= Can I customize the single-project page layout? =

Yes. Copy `single-spg_project.php` from the plugin folder into your active theme's folder and edit it there — the plugin will use your theme's copy automatically.

= Where does the "Back to Featured Works" link go? =

It links to a page with the slug `featuredworks` if one exists, otherwise it falls back to your homepage. Developers can change the destination with the `spg_back_link_url` filter.

== Screenshots ==

1. The portfolio grid produced by the `[portfolio]` shortcode.
2. A single project page with images on the left and content on the right.
3. The image picker in the Projects editor.

== Changelog ==

= 1.7.0 =
* Fixed project links landing on the home page: permalink rules are now refreshed after every update, not only when the plugin is activated by hand.
* The Commercial / Residential tabs now switch in CSS instead of JavaScript, so they keep working in any theme and a click can never navigate away.

= 1.6.0 =
* Smoothed the parallax on iPhone and iPad: it now runs off a frame loop instead of scroll events, only moves the images actually on screen, composites on the GPU, and no longer jumps when the Safari address bar slides away.
* The popup no longer lets the page scroll behind it on iOS, taps no longer leave a card stuck in its hover state, and buttons respond without the tap delay.

= 1.5.1 =
* Fixed the slow image change in the popup: it now serves a 2048px version instead of the untouched original, preloads the next and previous images, and shows the already-loaded image immediately while the larger one downloads.

= 1.5.0 =
* Redesigned the project page: banner with subtitle and pull quote, a hero image with counter/caption/arrows, a thumbnail grid, and a story column with a highlighted callout.
* New "Project Details" box on the project editor for the subtitle, pull quote, about heading and callout. Image captions come from the media library.

= 1.4.0 =
* Clicking an image on a project page opens it full size in a popup, with arrow/swipe navigation between that project's images.

= 1.3.0 =
* Parallax now moves each image's container, not the image itself, with neighboring images alternating direction as you scroll — a more pronounced, checkerboard-style effect.

= 1.2.0 =
* The `[portfolio]` grid is now split into Commercial and Residential tabs (new "Project Type" field on each project).
* Grid thumbnails now have the same scroll-parallax effect as project-page images.

= 1.1.0 =
* Project pages now show up to three images per row instead of two.
* Project-page images have a scroll-parallax effect.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Existing projects are automatically marked Commercial on update — open each project and check "Residential" where it applies.
