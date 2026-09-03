<?php
/**
 * Plugin Name:       Simple Portfolio Grid
 * Description:       Add projects with a title, a thumbnail, content and images. Shows a responsive grid via the [portfolio] shortcode, and a two-column page for each project (images left, text right).
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            pravinregi
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-portfolio-grid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Self-hosted updates via GitHub. To ship a new version: bump the
 * Version header above, commit, then `git tag vX.Y.Z && git push
 * origin main --tags`. Every site running this plugin will then see
 * "Update Now" in wp-admin, pulling straight from this repo.
 * ---------------------------------------------------------------- */

require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
	'https://github.com/chatgpta67-blip/simple-portfolio-grid/',
	__FILE__,
	'simple-portfolio-grid'
)->setBranch( 'main' );

/* ------------------------------------------------------------------ END TEMPORARY BLOCK */

/* ------------------------------------------------------------------
 * 1. The "Projects" post type
 * ---------------------------------------------------------------- */

add_action( 'init', 'spg_register_post_type' );
function spg_register_post_type() {

	register_post_type(
		'spg_project',
		array(
			'labels'        => array(
				'name'               => __( 'Projects', 'simple-portfolio-grid' ),
				'singular_name'      => __( 'Project', 'simple-portfolio-grid' ),
				'add_new'            => __( 'Add New Project', 'simple-portfolio-grid' ),
				'add_new_item'       => __( 'Add New Project', 'simple-portfolio-grid' ),
				'edit_item'          => __( 'Edit Project', 'simple-portfolio-grid' ),
				'all_items'          => __( 'All Projects', 'simple-portfolio-grid' ),
				'menu_name'          => __( 'Projects', 'simple-portfolio-grid' ),
				'featured_image'     => __( 'Thumbnail', 'simple-portfolio-grid' ),
				'set_featured_image' => __( 'Set thumbnail', 'simple-portfolio-grid' ),
			),
			'public'        => true,
			'has_archive'   => false,
			'menu_icon'     => 'dashicons-format-gallery',
			'menu_position' => 5,
			'rewrite'       => array( 'slug' => 'project', 'with_front' => false ),
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
		)
	);
}

// Use the classic editor here so the image uploader sits right under the content.
add_filter( 'use_block_editor_for_post_type', 'spg_use_classic_editor', 10, 2 );
function spg_use_classic_editor( $use, $post_type ) {
	return ( 'spg_project' === $post_type ) ? false : $use;
}

register_activation_hook( __FILE__, function () {
	spg_register_post_type();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* ------------------------------------------------------------------
 * 2. The image uploader
 * ---------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'spg_images', __( 'Project Images', 'simple-portfolio-grid' ), 'spg_render_images_metabox', 'spg_project', 'normal', 'high' );
} );

function spg_render_images_metabox( $post ) {
	wp_nonce_field( 'spg_save', 'spg_nonce' );
	$ids = get_post_meta( $post->ID, 'spg_images', true );
	?>
	<div id="spg-picker">
		<p style="margin-top:0;color:#666;"><?php esc_html_e( 'These appear on the left side of the project page. Drag to reorder inside the media window.', 'simple-portfolio-grid' ); ?></p>
		<input type="hidden" name="spg_images" id="spg-images" value="<?php echo esc_attr( $ids ); ?>" />
		<div id="spg-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
			<?php
			if ( $ids ) {
				foreach ( array_filter( explode( ',', $ids ) ) as $id ) {
					$img = wp_get_attachment_image( (int) $id, 'thumbnail', false, array( 'style' => 'width:100%;height:100%;object-fit:cover;' ) );
					if ( $img ) {
						echo '<span style="display:inline-block;width:80px;height:80px;overflow:hidden;border-radius:3px;">' . $img . '</span>';
					}
				}
			}
			?>
		</div>
		<button type="button" class="button button-primary" id="spg-select"><?php esc_html_e( 'Add / edit images', 'simple-portfolio-grid' ); ?></button>
		<button type="button" class="button" id="spg-clear"><?php esc_html_e( 'Clear all', 'simple-portfolio-grid' ); ?></button>
	</div>
	<?php
}

add_action( 'save_post_spg_project', function ( $post_id ) {

	if ( ! isset( $_POST['spg_nonce'] ) || ! wp_verify_nonce( $_POST['spg_nonce'], 'spg_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['spg_images'] ) ) {
		update_post_meta( $post_id, 'spg_images', sanitize_text_field( wp_unslash( $_POST['spg_images'] ) ) );
	}
} );

add_action( 'admin_enqueue_scripts', function () {

	global $post_type;
	if ( 'spg_project' !== $post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );

	$js = <<<'JS'
jQuery(function ($) {
	var frame;

	$('#spg-select').on('click', function (e) {
		e.preventDefault();

		frame = wp.media({
			title: 'Select project images',
			button: { text: 'Use these images' },
			library: { type: 'image' },
			multiple: 'add'
		});

		frame.on('open', function () {
			var selection = frame.state().get('selection');
			($('#spg-images').val() || '').split(',').filter(Boolean).forEach(function (id) {
				var a = wp.media.attachment(id);
				a.fetch();
				selection.add([a]);
			});
		});

		frame.on('select', function () {
			var ids = [];
			$('#spg-preview').empty();

			frame.state().get('selection').each(function (attachment) {
				var d = attachment.toJSON();
				ids.push(d.id);
				var src = (d.sizes && d.sizes.thumbnail) ? d.sizes.thumbnail.url : d.url;
				$('#spg-preview').append(
					'<span style="display:inline-block;width:80px;height:80px;overflow:hidden;border-radius:3px;">' +
					'<img src="' + src + '" style="width:100%;height:100%;object-fit:cover;" /></span>'
				);
			});

			$('#spg-images').val(ids.join(','));
		});

		frame.open();
	});

	$('#spg-clear').on('click', function (e) {
		e.preventDefault();
		$('#spg-images').val('');
		$('#spg-preview').empty();
	});
});
JS;

	wp_add_inline_script( 'jquery', $js );
} );

/* ------------------------------------------------------------------
 * 3. The grid shortcode  ->  [portfolio]
 * ---------------------------------------------------------------- */

add_shortcode( 'portfolio', function ( $atts ) {

	$atts = shortcode_atts( array( 'columns' => 3 ), $atts, 'portfolio' );

	$q = new WP_Query( array(
		'post_type'      => 'spg_project',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
	) );

	if ( ! $q->have_posts() ) {
		return '';
	}

	ob_start();
	echo '<div class="spg-grid" style="--spg-cols:' . (int) $atts['columns'] . ';">';

	while ( $q->have_posts() ) {
		$q->the_post();
		echo '<a class="spg-card" href="' . esc_url( get_permalink() ) . '">';
		if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'large' );
		}
		echo '<span class="spg-card-overlay"></span>';
		echo '<span class="spg-card-title">' . esc_html( get_the_title() ) . '</span>';
		echo '</a>';
	}

	echo '</div>';
	wp_reset_postdata();

	return ob_get_clean();
} );

/* ------------------------------------------------------------------
 * 4. The single project page
 * ---------------------------------------------------------------- */

add_filter( 'template_include', function ( $template ) {

	if ( is_singular( 'spg_project' ) ) {
		$theme = locate_template( array( 'single-spg_project.php' ) );
		return $theme ? $theme : plugin_dir_path( __FILE__ ) . 'single-spg_project.php';
	}

	return $template;
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'spg-style', false );
	wp_enqueue_style( 'spg-style' );
	wp_add_inline_style( 'spg-style', spg_css() );

	if ( is_singular( 'spg_project' ) ) {
		wp_register_script( 'spg-parallax', false, array(), false, true );
		wp_enqueue_script( 'spg-parallax' );
		wp_add_inline_script( 'spg-parallax', spg_parallax_js() );
	}
} );

function spg_parallax_js() {
	return <<<'JS'
(function () {
	if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}

	var imgs = document.querySelectorAll( '.spg-image-wrap img' );
	if ( ! imgs.length ) {
		return;
	}

	var maxShift = 30;
	var ticking  = false;

	function update() {
		var wh = window.innerHeight;

		imgs.forEach( function ( img ) {
			var rect     = img.parentElement.getBoundingClientRect();
			var center   = rect.top + rect.height / 2;
			var progress = ( center - wh / 2 ) / ( wh / 2 + rect.height / 2 );
			progress     = Math.max( -1, Math.min( 1, progress ) );
			img.style.transform = 'translateY(' + ( progress * maxShift ).toFixed( 2 ) + 'px)';
		} );

		ticking = false;
	}

	function onScroll() {
		if ( ! ticking ) {
			window.requestAnimationFrame( update );
			ticking = true;
		}
	}

	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'resize', onScroll );
	update();
})();
JS;
}

function spg_css() {
	return '
/* grid */
.spg-grid{display:grid;grid-template-columns:repeat(var(--spg-cols,3),1fr);gap:24px}
.spg-card{position:relative;display:block;aspect-ratio:16/7;overflow:hidden;text-decoration:none}
.spg-card img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .6s ease}
.spg-card:hover img{transform:scale(1.06)}
.spg-card-overlay{position:absolute;inset:0;background:rgba(0,0,0,.3);transition:background .4s ease}
.spg-card:hover .spg-card-overlay{background:rgba(0,0,0,.45)}
.spg-card-title{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
text-align:center;padding:0 18px;color:#fff;font-size:17px;font-weight:600;letter-spacing:.14em;
text-transform:uppercase;line-height:1.35}

/* single project: images left, text right */
.spg-single{display:grid;grid-template-columns:1.45fr 1fr;gap:60px;
max-width:1500px;margin:0 auto;padding:60px 30px}
.spg-images{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.spg-image-wrap{position:relative;overflow:hidden;aspect-ratio:4/3}
.spg-image-wrap img{position:absolute;top:-18%;left:0;width:100%;height:136%;object-fit:cover;display:block;will-change:transform}
/* a lone final image spans the full width instead of leaving a gap */
.spg-image-wrap:last-child:nth-child(3n+1){grid-column:1/-1;aspect-ratio:21/9}
.spg-text{position:sticky;top:100px;align-self:start}
.spg-text h1{font-size:clamp(30px,3.4vw,44px);font-weight:300;letter-spacing:.02em;margin:0 0 24px}
.spg-text .spg-body{font-size:17px;line-height:1.85}
.spg-text .spg-body h2{font-size:26px;font-weight:400;margin:0 0 16px}
.spg-back{display:inline-block;margin-top:36px;font-size:13px;letter-spacing:.1em;
text-transform:uppercase;text-decoration:none;opacity:.7}
.spg-back:hover{opacity:1}

@media(max-width:1024px){.spg-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.spg-single{grid-template-columns:1fr;gap:36px;padding:40px 20px}
.spg-text{position:static}}
@media(max-width:640px){.spg-grid{grid-template-columns:1fr}.spg-card-title{font-size:15px}}
@media(max-width:560px){.spg-images{grid-template-columns:1fr}
.spg-image-wrap:last-child:nth-child(3n+1){aspect-ratio:4/3}}
';
}
