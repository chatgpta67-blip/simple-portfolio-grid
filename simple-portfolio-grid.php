<?php
/**
 * Plugin Name:       Simple Portfolio Grid
 * Description:       Add projects with a title, a thumbnail, content and images. Shows a responsive grid via the [portfolio] shortcode, and an editorial page for each project (banner, gallery left, story right).
 * Version:           1.11.0
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

define( 'SPG_VERSION', '1.11.0' );

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

add_action( 'init', 'spg_register_taxonomy' );
function spg_register_taxonomy() {

	register_taxonomy(
		'spg_project_type',
		'spg_project',
		array(
			'labels'            => array(
				'name'          => __( 'Project Type', 'simple-portfolio-grid' ),
				'singular_name' => __( 'Project Type', 'simple-portfolio-grid' ),
			),
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => false,
		)
	);
}

// Makes sure the Commercial / Residential terms exist, and puts any project
// created before this taxonomy existed into Commercial so it doesn't just
// vanish from the grid. Runs once per site (fresh install or update alike).
function spg_seed_project_types() {

	foreach ( array( 'Commercial', 'Residential' ) as $name ) {
		if ( ! term_exists( $name, 'spg_project_type' ) ) {
			wp_insert_term( $name, 'spg_project_type' );
		}
	}

	$untagged = get_posts( array(
		'post_type'      => 'spg_project',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'tax_query'      => array(
			array(
				'taxonomy' => 'spg_project_type',
				'operator' => 'NOT EXISTS',
			),
		),
	) );

	foreach ( $untagged as $post_id ) {
		wp_set_post_terms( $post_id, array( 'Commercial' ), 'spg_project_type' );
	}
}

add_action( 'init', function () {
	if ( get_option( 'spg_terms_seeded' ) ) {
		return;
	}
	spg_seed_project_types();
	update_option( 'spg_terms_seeded', 1 );
}, 20 );

// Updates arrive through the GitHub updater, which never fires the activation
// hook, so the permalink rules registered above would stay stale and project
// URLs would fall through to the home page. Refresh them once per release.
add_action( 'init', function () {
	if ( get_option( 'spg_rewrite_version' ) === SPG_VERSION ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'spg_rewrite_version', SPG_VERSION );
}, 20 );

register_activation_hook( __FILE__, function () {
	spg_register_post_type();
	spg_register_taxonomy();
	spg_seed_project_types();
	update_option( 'spg_terms_seeded', 1 );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* ------------------------------------------------------------------
 * 2. The image uploader
 * ---------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'spg_images', __( 'Project Images', 'simple-portfolio-grid' ), 'spg_render_images_metabox', 'spg_project', 'normal', 'high' );
	add_meta_box( 'spg_details', __( 'Project Details', 'simple-portfolio-grid' ), 'spg_render_details_metabox', 'spg_project', 'normal', 'high' );
} );

function spg_render_details_metabox( $post ) {

	$lines = array(
		'spg_subtitle' => array( __( 'Subtitle', 'simple-portfolio-grid' ), __( 'Sits under the project title in the banner.', 'simple-portfolio-grid' ) ),
		'spg_quote'    => array( __( 'Pull quote', 'simple-portfolio-grid' ), __( 'Short italic line on the right of the banner.', 'simple-portfolio-grid' ) ),
		'spg_heading'  => array( __( 'About heading', 'simple-portfolio-grid' ), __( 'Heading above the description, e.g. "A Home Nestled in Nature".', 'simple-portfolio-grid' ) ),
	);

	foreach ( $lines as $key => $line ) {
		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br />
			<input type="text" class="widefat" id="%1$s" name="%1$s" value="%3$s" />
			<span class="description">%4$s</span></p>',
			esc_attr( $key ),
			esc_html( $line[0] ),
			esc_attr( get_post_meta( $post->ID, $key, true ) ),
			esc_html( $line[1] )
		);
	}
	?>
	<p>
		<label for="spg_callout"><strong><?php esc_html_e( 'Callout', 'simple-portfolio-grid' ); ?></strong></label><br />
		<textarea class="widefat" rows="2" id="spg_callout" name="spg_callout"><?php echo esc_textarea( get_post_meta( $post->ID, 'spg_callout', true ) ); ?></textarea>
		<span class="description"><?php esc_html_e( 'Highlighted box at the end of the description.', 'simple-portfolio-grid' ); ?></span>
	</p>
	<?php
}

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

	foreach ( array( 'spg_subtitle', 'spg_quote', 'spg_heading' ) as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
		}
	}

	if ( isset( $_POST['spg_callout'] ) ) {
		update_post_meta( $post_id, 'spg_callout', sanitize_textarea_field( wp_unslash( $_POST['spg_callout'] ) ) );
	}

	// Both tabs filter on the taxonomy, so a project saved without a type would
	// quietly show up in neither. Default it rather than let it disappear.
	if ( ! wp_get_post_terms( $post_id, 'spg_project_type', array( 'fields' => 'ids' ) ) ) {
		wp_set_post_terms( $post_id, array( 'Commercial' ), 'spg_project_type' );
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

	$atts = shortcode_atts( array( 'columns' => 3, 'type' => '', 'title' => '' ), $atts, 'portfolio' );

	$args = array(
		'post_type'      => 'spg_project',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
	);

	// type="" shows every project; otherwise one or more Project Type slugs.
	$types = array_filter( array_map( 'sanitize_title', explode( ',', $atts['type'] ) ) );

	if ( $types ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'spg_project_type',
				'field'    => 'slug',
				'terms'    => $types,
			),
		);
	}

	$q = new WP_Query( $args );

	if ( ! $q->have_posts() ) {
		wp_reset_postdata();
		return '';
	}

	ob_start();
	echo '<div class="spg-portfolio">';

	if ( $atts['title'] ) {
		// A pipe in the title is set apart, as in "RESIDENTIAL | MULTI-FAMILY".
		$parts = array_map( 'esc_html', array_map( 'trim', explode( '|', $atts['title'] ) ) );
		echo '<h2 class="spg-section-title">' . implode( '<span>|</span>', $parts ) . '</h2>';
	}

	echo '<div class="spg-grid" style="--spg-cols:' . (int) $atts['columns'] . ';">';

	$i = 0;
	while ( $q->have_posts() ) {
		$q->the_post();
		$dir = ( 0 === $i % 2 ) ? 1 : -1;
		echo '<a class="spg-card" href="' . esc_url( get_permalink() ) . '">';
		if ( has_post_thumbnail() ) {
			echo '<span class="spg-parallax-layer" data-spg-dir="' . esc_attr( $dir ) . '">' . get_the_post_thumbnail( get_the_ID(), 'large' ) . '</span>';
		}
		echo '<span class="spg-card-overlay"></span>';
		echo '<span class="spg-card-title">' . esc_html( get_the_title() ) . '</span>';
		echo '</a>';
		$i++;
	}

	echo '</div></div>';
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

	wp_register_script( 'spg-frontend', false, array(), false, true );
	wp_enqueue_script( 'spg-frontend' );
	wp_add_inline_script( 'spg-frontend', spg_frontend_js() );

	// The project page headings use the display serif the theme already names in
	// its own CSS, but nothing on the page actually loads it. Filterable so a site
	// using different type can drop this request.
	if ( is_singular( 'spg_project' ) ) {
		$font_url = apply_filters(
			'spg_heading_font_url',
			'https://fonts.googleapis.com/css2?family=Noto+Serif+Display:ital,wght@0,400;0,500;1,400&display=swap'
		);

		if ( $font_url ) {
			wp_enqueue_style( 'spg-fonts', $font_url, array(), null );
		}
	}
} );

function spg_frontend_js() {
	return <<<'JS'
(function () {
	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var layers       = Array.prototype.slice.call( document.querySelectorAll( '.spg-parallax-layer' ) );
	var isTouch      = window.matchMedia && window.matchMedia( '(hover: none)' ).matches;

	if ( ! layers.length || reduceMotion ) {
		return;
	}

	var visible    = [];
	var vw         = window.innerWidth;
	var vh         = window.innerHeight;
	var lastScroll = -1;
	var running    = false;
	var rafId      = 0;

	// Driven by a frame loop rather than scroll events: iOS throttles scroll
	// events during momentum scrolling, which makes the movement stutter and
	// trail behind the page.
	function render() {
		var scrollY = window.pageYOffset;

		if ( scrollY !== lastScroll ) {
			lastScroll = scrollY;

			for ( var i = 0; i < visible.length; i++ ) {
				var layer    = visible[ i ];
				var rect     = layer.parentElement.getBoundingClientRect();
				var progress = ( rect.top + rect.height / 2 - vh / 2 ) / ( vh / 2 + rect.height / 2 );
				var dir      = parseFloat( layer.getAttribute( 'data-spg-dir' ) ) || 1;
				var amount   = Math.min( rect.height * 0.2, 80 ) * ( vw < 768 ? 0.6 : 1 );

				progress = Math.max( -1, Math.min( 1, progress ) );
				layer.style.transform = 'translate3d(0,' + ( progress * amount * dir ).toFixed( 2 ) + 'px,0)';
			}
		}

		rafId = running ? window.requestAnimationFrame( render ) : 0;
	}

	function start() {
		if ( ! running ) {
			running = true;
			rafId   = window.requestAnimationFrame( render );
		}
	}

	function stop() {
		running = false;

		if ( rafId ) {
			window.cancelAnimationFrame( rafId );
			rafId = 0;
		}
	}

	function onResize() {
		// iOS fires resize every time the address bar slides away mid-scroll.
		// Taking the new height there makes every image jump, so on a touch
		// device only react once the width actually changes.
		if ( isTouch && window.innerWidth === vw ) {
			return;
		}

		vw         = window.innerWidth;
		vh         = window.innerHeight;
		lastScroll = -1;
	}

	// Only the images on screen are measured and moved each frame, and only
	// those carry will-change: iOS Safari drops or flickers images when too
	// many large composited layers are alive at once.
	var io = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			entry.target.classList.toggle( 'is-visible', entry.isIntersecting );
		} );

		visible = layers.filter( function ( layer ) {
			return layer.classList.contains( 'is-visible' );
		} );

		lastScroll = -1;

		if ( visible.length ) {
			start();
		} else {
			stop();
		}
	}, { rootMargin: '15% 0px' } );

	layers.forEach( function ( layer ) {
		io.observe( layer );
	} );

	window.addEventListener( 'resize', onResize, { passive: true } );
	window.addEventListener( 'orientationchange', onResize, { passive: true } );
})();

(function () {
	var gallery = document.querySelector( '.spg-gallery[data-spg-images]' );

	if ( ! gallery ) {
		return;
	}

	var images = JSON.parse( gallery.getAttribute( 'data-spg-images' ) );

	if ( ! images || ! images.length ) {
		return;
	}

	var stageImg     = gallery.querySelector( '.spg-stage-img' );
	var stageCount   = gallery.querySelector( '.spg-stage-count' );
	var stageCaption = gallery.querySelector( '.spg-stage-caption' );
	var shots        = Array.prototype.slice.call( gallery.querySelectorAll( '[data-spg-index]' ) );
	var current      = 0;

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function setStage( i ) {
		current = ( i + images.length ) % images.length;

		var image = images[ current ];

		stageImg.src = image.src;
		stageImg.alt = image.alt || image.caption || '';

		stageCount.textContent   = pad( current + 1 ) + ' / ' + pad( images.length );
		stageCaption.textContent = image.caption || '';

		shots.forEach( function ( shot ) {
			shot.classList.toggle( 'is-current', parseInt( shot.getAttribute( 'data-spg-index' ), 10 ) === current );
		} );
	}

	var box = document.createElement( 'div' );

	box.className = 'spg-lightbox';
	box.setAttribute( 'role', 'dialog' );
	box.setAttribute( 'aria-modal', 'true' );
	box.setAttribute( 'aria-label', 'Project image' );
	box.hidden = true;
	box.innerHTML =
		'<button type="button" class="spg-lb-btn spg-lb-close" aria-label="Close">&times;</button>' +
		'<button type="button" class="spg-lb-btn spg-lb-prev" aria-label="Previous image">&#8249;</button>' +
		'<img class="spg-lb-img" src="" alt="">' +
		'<span class="spg-lb-spin" aria-hidden="true"></span>' +
		'<button type="button" class="spg-lb-btn spg-lb-next" aria-label="Next image">&#8250;</button>' +
		'<p class="spg-lb-count"></p>';

	document.body.appendChild( box );

	var picture   = box.querySelector( '.spg-lb-img' );
	var counter   = box.querySelector( '.spg-lb-count' );
	var closeBtn  = box.querySelector( '.spg-lb-close' );
	var prevBtn   = box.querySelector( '.spg-lb-prev' );
	var nextBtn   = box.querySelector( '.spg-lb-next' );
	var index     = 0;
	var lastFocus = null;
	var hideTimer = 0;
	var shown     = 0;
	var locked    = 0;
	var cache     = {};

	if ( images.length < 2 ) {
		prevBtn.hidden = true;
		nextBtn.hidden = true;
		counter.hidden = true;
	}

	function preload( i ) {
		var n = ( i + images.length ) % images.length;

		if ( ! cache[ n ] ) {
			cache[ n ]     = new Image();
			cache[ n ].src = images[ n ].full;
		}

		return cache[ n ];
	}

	function show( i ) {
		index = ( i + images.length ) % images.length;

		var image = images[ index ];
		var token = ++shown;

		picture.alt         = image.alt || image.caption || '';
		counter.textContent = pad( index + 1 ) + ' / ' + pad( images.length ) + ( image.caption ? '  ' + image.caption : '' );

		var big = preload( index );

		if ( big.complete ) {
			picture.src = image.full;
			box.classList.remove( 'is-loading' );
		} else {
			// The page already downloaded this image at grid size, so show that
			// straight away and swap in the large one when it arrives.
			picture.src = image.src;
			box.classList.add( 'is-loading' );

			big.addEventListener( 'load', function () {
				if ( token === shown ) {
					picture.src = image.full;
					box.classList.remove( 'is-loading' );
				}
			} );
		}

		preload( index + 1 );
		preload( index - 1 );
	}

	// overflow:hidden on the body does not hold on iOS Safari — the page keeps
	// scrolling behind the popup — so the body is pinned at its current offset.
	function lockScroll() {
		locked = window.pageYOffset;
		document.body.style.position = 'fixed';
		document.body.style.top      = '-' + locked + 'px';
		document.body.style.left     = '0';
		document.body.style.right    = '0';
	}

	function unlockScroll() {
		document.body.style.position = '';
		document.body.style.top      = '';
		document.body.style.left     = '';
		document.body.style.right    = '';
		window.scrollTo( 0, locked );
	}

	function open( i ) {
		window.clearTimeout( hideTimer );
		lastFocus = document.activeElement;
		show( i );
		box.hidden = false;
		lockScroll();
		window.requestAnimationFrame( function () {
			box.classList.add( 'is-open' );
		} );
		closeBtn.focus();
	}

	function close() {
		box.classList.remove( 'is-open' );
		unlockScroll();
		hideTimer = window.setTimeout( function () {
			box.hidden = true;
		}, 250 );

		if ( lastFocus ) {
			lastFocus.focus();
		}
	}

	shots.forEach( function ( shot ) {
		shot.addEventListener( 'click', function () {
			setStage( parseInt( shot.getAttribute( 'data-spg-index' ), 10 ) );
		} );
	} );

	gallery.querySelector( '.spg-stage-prev' ).addEventListener( 'click', function () {
		setStage( current - 1 );
	} );

	gallery.querySelector( '.spg-stage-next' ).addEventListener( 'click', function () {
		setStage( current + 1 );
	} );

	gallery.querySelector( '.spg-stage-open' ).addEventListener( 'click', function () {
		open( current );
	} );

	setStage( 0 );

	closeBtn.addEventListener( 'click', close );

	prevBtn.addEventListener( 'click', function () {
		show( index - 1 );
	} );

	nextBtn.addEventListener( 'click', function () {
		show( index + 1 );
	} );

	box.addEventListener( 'click', function ( e ) {
		if ( e.target === box ) {
			close();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( box.hidden ) {
			return;
		}

		if ( 'Escape' === e.key ) {
			close();
		} else if ( 'ArrowLeft' === e.key ) {
			show( index - 1 );
		} else if ( 'ArrowRight' === e.key ) {
			show( index + 1 );
		}
	} );

	var touchX = 0;

	box.addEventListener( 'touchstart', function ( e ) {
		touchX = e.changedTouches[ 0 ].clientX;
	}, { passive: true } );

	box.addEventListener( 'touchend', function ( e ) {
		var delta = e.changedTouches[ 0 ].clientX - touchX;

		if ( Math.abs( delta ) > 50 ) {
			show( delta < 0 ? index + 1 : index - 1 );
		}
	}, { passive: true } );
})();
JS;
}

function spg_css() {
	return '
:root{--spg-ink:#1d2522;--spg-muted:#8d837b;--spg-soft:#77736e;--spg-body:#555652;
--spg-quote:#615b55;--spg-rule:#a9998d;--spg-rule-quote:#9e8d81;--spg-rule-back:#bdb4ac;
--spg-sage:#f0f2eb;--spg-line:#e1e1d8;--spg-accent:#8f776b;--spg-accent-ink:#513a32;--spg-shade:#e8e6e0;
--spg-serif:"Noto Serif Display",Georgia,"Times New Roman",serif;
--spg-sans:"Montserrat",Arial,Helvetica,sans-serif}

/* grid */
.spg-grid{display:grid;grid-template-columns:repeat(var(--spg-cols,3),1fr);gap:24px}
.spg-portfolio{width:min(1100px,calc(100% - 48px));margin:34px auto 80px}
.spg-portfolio .spg-section-title{margin:0 0 18px!important;padding:0!important;
font-family:var(--spg-serif)!important;font-size:clamp(36px,4.2vw,50px)!important;
line-height:1!important;font-weight:400!important;letter-spacing:1px!important;
text-transform:none!important;color:#aaa!important}
.spg-portfolio .spg-section-title span{display:inline-block;margin:0 15px;color:#999;
font-family:var(--spg-sans)!important;font-weight:300!important}
.spg-grid{display:grid;grid-template-columns:repeat(var(--spg-cols,3),1fr);column-gap:31px;row-gap:62px}
.spg-card{position:relative;display:block;height:87px;overflow:hidden;text-decoration:none;background:#ddd}
.spg-parallax-layer{position:absolute;top:-25%;left:0;width:100%;height:150%;overflow:hidden;
transform:translate3d(0,0,0);backface-visibility:hidden}
.spg-parallax-layer.is-visible{will-change:transform}
.spg-parallax-layer img{width:100%;height:100%;object-fit:cover;display:block}
.spg-card img{filter:brightness(.72);transition:transform .45s ease,filter .45s ease}
.spg-card-overlay{position:absolute;inset:0;background:rgba(0,0,0,.16);transition:background .3s ease}
.spg-portfolio .spg-card-title{position:absolute;z-index:2;inset:0;display:flex;
align-items:center;justify-content:center;padding:10px!important;margin:0!important;
color:#fff!important;text-align:center;font-family:var(--spg-sans)!important;
font-size:16px!important;font-weight:700!important;line-height:1.35!important;
letter-spacing:1.4px!important;text-transform:uppercase!important;
text-shadow:0 1px 4px rgba(0,0,0,.45)}
/* hover only where there is a real pointer, otherwise a tap sticks these on until
   the next tap somewhere else */
@media(hover:hover){.spg-card:hover img{transform:scale(1.045);filter:brightness(.82)}
.spg-card:hover .spg-card-overlay{background:rgba(0,0,0,.08)}}
.spg-card,.spg-shot,.spg-lb-btn,.spg-stage-open,.spg-stage-nav button{
touch-action:manipulation;-webkit-tap-highlight-color:transparent}

/* single project page.
   Scoped to .spg-project, and the properties themes most often reset (type,
   colour, button padding) are forced: this drops into an unknown theme whose
   stylesheet loads after ours and would otherwise win. */
.spg-project{display:block;width:100%;max-width:100%;flex:1 1 100%;grid-column:1/-1;
color:var(--spg-ink);font-family:var(--spg-sans)}
.spg-project *{box-sizing:border-box}
.spg-project button{margin:0;min-width:0!important;box-shadow:none!important;text-shadow:none;
font-family:inherit;text-transform:none;letter-spacing:normal;line-height:1}
.spg-page{max-width:1440px;margin:0 auto;padding:var(--spg-top,118px) 54px 90px}

.spg-project .spg-eyebrow{display:flex!important;align-items:center;gap:16px;margin:0 0 22px!important;
color:var(--spg-muted)!important;font-family:var(--spg-sans)!important;
font-size:11px!important;font-weight:400!important;line-height:1.4!important;
letter-spacing:4px!important;text-transform:uppercase!important}
.spg-project .spg-eyebrow:before{content:"";flex:0 0 38px;width:38px;height:1px;background:var(--spg-rule)}

.spg-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:40px;padding-bottom:55px}
.spg-hero-head{flex:1 1 auto;min-width:0}
.spg-project .spg-title{max-width:950px;margin:0!important;padding:0!important;
font-family:var(--spg-serif)!important;font-weight:400!important;
font-size:clamp(46px,5.4vw,78px)!important;line-height:.98!important;letter-spacing:-2.5px!important;
text-transform:none!important;color:var(--spg-ink)!important}
.spg-project .spg-subtitle{margin:22px 0 0!important;color:var(--spg-soft)!important;
font-family:var(--spg-sans)!important;font-size:20px!important;font-weight:400!important;
line-height:1.5!important;letter-spacing:normal!important}
.spg-project .spg-quote{flex:0 0 180px;width:180px;margin:0!important;padding:0 0 8px!important;
color:var(--spg-quote)!important;font-family:var(--spg-serif)!important;font-style:italic!important;
font-size:19px!important;font-weight:400!important;line-height:1.35!important;letter-spacing:normal!important}
.spg-project .spg-quote:after{content:"";display:block;width:42px;height:1px;margin-top:18px;
background:var(--spg-rule-quote)}

.spg-content{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(380px,.75fr);
gap:62px;align-items:start}
.spg-content--solo{grid-template-columns:1fr}
.spg-gallery-col{min-width:0}

.spg-gallery{--spg-stage-h:clamp(360px,34vw,490px);display:grid;grid-template-columns:2fr 1fr;gap:12px}
.spg-stage{position:relative;grid-row:span 2;height:var(--spg-stage-h);overflow:hidden;
border-radius:12px;background:var(--spg-shade)}
.spg-project .spg-stage-img{display:block;width:100%;height:100%;max-width:none;margin:0;
border-radius:0;object-fit:cover}
.spg-stage:after{content:"";position:absolute;inset:0;pointer-events:none;
background:linear-gradient(180deg,transparent 65%,rgba(0,0,0,.28))}
.spg-project .spg-stage-open{position:absolute;inset:0;z-index:2;width:100%;height:100%;
padding:0!important;border:0!important;background:none!important;cursor:zoom-in}
.spg-project .spg-stage-meta{position:absolute;z-index:3;left:18px;bottom:16px;display:flex;gap:12px;
margin:0!important;max-width:calc(100% - 120px);color:#fff!important;
font-family:var(--spg-sans)!important;font-size:12px!important;line-height:1.4!important;
letter-spacing:.5px!important;pointer-events:none}
.spg-stage-caption{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.spg-stage-nav{position:absolute;z-index:4;right:16px;bottom:13px;display:flex;gap:7px}
.spg-project .spg-stage-nav button{display:grid;place-items:center;
flex:0 0 35px!important;width:35px!important;height:35px!important;padding:0!important;
border:1px solid rgba(255,255,255,.55)!important;border-radius:50%!important;
background:rgba(40,35,30,.25)!important;backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);
color:#fff!important;font-size:15px!important;line-height:1!important;cursor:pointer;
transition:background .25s ease}

.spg-project .spg-shot{position:relative;display:block;width:100%;padding:0!important;
border:0!important;border-radius:12px!important;overflow:hidden;background:var(--spg-shade);
cursor:pointer;transition:transform .2s ease,opacity .2s ease}
.spg-project .spg-shot img{max-width:none;margin:0;border-radius:0}
.spg-side{height:calc((var(--spg-stage-h) - 12px) / 2)}
.spg-thumbs{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:2px}
.spg-thumb{height:clamp(96px,9vw,128px)}
.spg-shot.is-current{opacity:.72}


.spg-copy{padding-top:12px;min-width:0}
.spg-project .spg-heading{max-width:510px;margin:0 0 26px!important;padding:0!important;
font-family:var(--spg-serif)!important;font-weight:400!important;
font-size:clamp(40px,4vw,62px)!important;line-height:1.03!important;letter-spacing:-1.8px!important;
text-transform:none!important;color:var(--spg-ink)!important}
.spg-project .spg-body{color:var(--spg-body)!important;font-family:var(--spg-sans)!important;
font-size:17px!important;line-height:1.75!important;letter-spacing:normal!important}
/* Text pasted from a word processor arrives as <div>s, not <p>s, so this targets
   every direct child rather than paragraphs alone. */
.spg-project .spg-body > *{margin:0 0 20px!important;color:var(--spg-body)!important;
font-family:var(--spg-sans)!important;font-size:17px!important;
line-height:1.75!important;letter-spacing:normal!important}
.spg-project .spg-body > *:last-child{margin-bottom:0!important}
.spg-project .spg-body strong,.spg-project .spg-body b{color:var(--spg-ink)!important;font-weight:600}
.spg-project .spg-body h1,.spg-project .spg-body h2,.spg-project .spg-body h3{
margin:0 0 20px!important;font-family:var(--spg-serif)!important;font-weight:400!important;
line-height:1.1!important;letter-spacing:-1px!important;color:var(--spg-ink)!important}
.spg-project .spg-body h2{font-size:38px!important}
.spg-project .spg-body h3{font-size:27px!important}
.spg-project .spg-body img{max-width:100%;height:auto;border-radius:10px}

.spg-project .spg-statement{display:flex;align-items:center;gap:18px;margin-top:30px;
padding:24px 26px!important;border:1px solid var(--spg-line)!important;border-radius:10px;
background:var(--spg-sage)!important;font-family:var(--spg-serif)!important;font-size:19px!important;
line-height:1.5!important;color:var(--spg-ink)!important}
.spg-project .spg-statement p{margin:0!important;font-family:var(--spg-serif)!important;
font-size:19px!important;line-height:1.5!important;color:var(--spg-ink)!important}
.spg-leaf{position:relative;flex:0 0 30px;width:30px;height:30px;background:#68785b;
border-radius:50% 0 50% 0;transform:rotate(-45deg)}
.spg-leaf:after{content:"";position:absolute;left:15px;top:4px;width:1px;height:23px;
background:#e8eee3;transform:rotate(45deg)}

.spg-project .spg-back{display:inline-flex;align-items:center;gap:10px;
margin-top:54px!important;padding:18px 0 0!important;border-top:1px solid var(--spg-rule-back)!important;
color:var(--spg-soft)!important;font-family:var(--spg-sans)!important;
font-size:13px!important;line-height:1.4!important;letter-spacing:1.5px!important;
text-transform:uppercase!important;text-decoration:none!important;background:none!important}
.spg-project .spg-back span{font-size:20px;line-height:1}

@media(hover:hover){.spg-thumb:hover{transform:translateY(-3px)}
.spg-project .spg-stage-nav button:hover{background:rgba(40,35,30,.6)!important}
.spg-project .spg-back:hover{color:var(--spg-ink)!important}}

/* full-image popup */
.spg-lightbox{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;
padding:48px;background:rgba(0,0,0,.92);opacity:0;transition:opacity .25s ease}
.spg-lightbox.is-open{opacity:1}
.spg-lightbox[hidden]{display:none}
.spg-lb-img{max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;display:block}
.spg-lb-btn{position:absolute;background:none;border:none;color:#fff;cursor:pointer;
line-height:1;padding:10px;opacity:.7;transition:opacity .2s ease}
.spg-lb-btn:hover{opacity:1}
.spg-lb-btn[hidden]{display:none}
.spg-lb-close{top:16px;right:22px;font-size:38px}
.spg-lb-prev,.spg-lb-next{top:50%;margin-top:-30px;font-size:56px}
.spg-lb-prev{left:14px}
.spg-lb-next{right:14px}
.spg-lb-count{position:absolute;bottom:18px;left:0;right:0;margin:0;text-align:center;
color:#fff;opacity:.6;font-size:12px;letter-spacing:.12em}
.spg-lb-spin{position:absolute;width:34px;height:34px;border:2px solid rgba(255,255,255,.25);
border-top-color:#fff;border-radius:50%;opacity:0;pointer-events:none;
transition:opacity .2s ease;animation:spg-spin .8s linear infinite}
.spg-lightbox.is-loading .spg-lb-spin{opacity:1}
@keyframes spg-spin{to{transform:rotate(360deg)}}

@media(max-width:800px){.spg-portfolio{width:min(700px,calc(100% - 32px));margin-top:28px}
.spg-grid{grid-template-columns:repeat(2,1fr);column-gap:20px;row-gap:28px}
.spg-card{height:95px}
.spg-portfolio .spg-section-title{font-size:38px!important}}

@media(max-width:520px){.spg-portfolio{width:calc(100% - 28px)}
.spg-grid{grid-template-columns:1fr;row-gap:18px}
.spg-card{height:105px}
.spg-portfolio .spg-section-title{font-size:31px!important;letter-spacing:.5px!important}
.spg-portfolio .spg-section-title span{margin:0 8px}}

@media(max-width:1100px){.spg-content{grid-template-columns:1fr;gap:48px}
.spg-copy{padding-top:0;max-width:760px}
.spg-project .spg-heading,.spg-project .spg-title{max-width:none}}

@media(max-width:900px){.spg-page{padding:var(--spg-top,96px) 24px 60px}
.spg-hero{display:block;padding-bottom:40px}
.spg-project .spg-quote{flex:none;width:auto;max-width:320px;margin-top:30px!important}
.spg-project .spg-title{letter-spacing:-1.5px!important}
.spg-project .spg-subtitle{font-size:18px!important}}

@media(max-width:640px){.spg-lightbox{padding:14px}
.spg-lb-close{font-size:30px;top:6px;right:10px}
.spg-lb-prev,.spg-lb-next{font-size:38px;margin-top:-22px}}

@media(max-width:600px){.spg-page{padding:var(--spg-top,88px) 18px 50px}
.spg-gallery{grid-template-columns:1fr 1fr;--spg-stage-h:330px}
.spg-stage{grid-column:1/-1;grid-row:auto;height:330px}
.spg-side{height:180px}
.spg-thumbs{grid-template-columns:repeat(2,1fr)}
.spg-thumb{height:120px}
.spg-project .spg-stage-nav button{flex:0 0 42px!important;width:42px!important;height:42px!important}
.spg-stage-nav{right:12px;bottom:12px}
.spg-project .spg-stage-meta{left:14px;max-width:calc(100% - 122px)}
.spg-project .spg-title{font-size:45px!important;letter-spacing:-1px!important}
.spg-project .spg-subtitle{font-size:17px!important;margin-top:16px!important}
.spg-project .spg-heading{font-size:34px!important;letter-spacing:-1px!important;margin-bottom:20px!important}
.spg-project .spg-body,.spg-project .spg-body > *{font-size:16px!important}
.spg-project .spg-statement,.spg-project .spg-statement p{font-size:17px!important}
.spg-project .spg-statement{padding:20px!important;gap:14px}
.spg-project .spg-back{margin-top:40px!important}}

@media(max-width:380px){.spg-project .spg-title{font-size:38px!important}
.spg-gallery{--spg-stage-h:260px}
.spg-stage{height:260px}
.spg-side{height:130px}}
';
}
