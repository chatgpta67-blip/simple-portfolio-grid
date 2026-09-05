<?php
/**
 * Plugin Name:       Simple Portfolio Grid
 * Description:       Add projects with a title, a thumbnail, content and images. Shows a responsive grid via the [portfolio] shortcode, and an editorial page for each project (banner, gallery left, story right).
 * Version:           1.7.0
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

define( 'SPG_VERSION', '1.7.0' );

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

	$tabs = array(
		'commercial'  => __( 'Commercial', 'simple-portfolio-grid' ),
		'residential' => __( 'Residential', 'simple-portfolio-grid' ),
	);

	$panels = array();

	foreach ( $tabs as $slug => $label ) {

		$q = new WP_Query( array(
			'post_type'      => 'spg_project',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'spg_project_type',
					'field'    => 'slug',
					'terms'    => $slug,
				),
			),
		) );

		ob_start();

		if ( $q->have_posts() ) {
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

			echo '</div>';
		} else {
			echo '<p class="spg-empty">' . esc_html__( 'No projects in this category yet.', 'simple-portfolio-grid' ) . '</p>';
		}

		wp_reset_postdata();
		$panels[ $slug ] = ob_get_clean();
	}

	// Radio + label rather than buttons: switching is done entirely in CSS, so the
	// tabs keep working if this theme never runs the script, and a click can never
	// navigate the way a stray button in a themes form can.
	$uid = wp_unique_id( 'spg-tabs-' );

	ob_start();
	echo '<div class="spg-tabs">';

	$i = 0;
	foreach ( $tabs as $slug => $label ) {
		printf(
			'<input type="radio" class="spg-tab-input" name="%1$s" id="%1$s-%2$s" data-spg-tab="%2$s"%3$s />',
			esc_attr( $uid ),
			esc_attr( $slug ),
			( 0 === $i ) ? ' checked="checked"' : ''
		);
		$i++;
	}

	echo '<div class="spg-tab-nav">';
	foreach ( $tabs as $slug => $label ) {
		printf(
			'<label class="spg-tab-btn" for="%1$s-%2$s">%3$s</label>',
			esc_attr( $uid ),
			esc_attr( $slug ),
			esc_html( $label )
		);
	}
	echo '</div>';

	foreach ( $panels as $slug => $html ) {
		echo '<div class="spg-tab-panel" data-spg-panel="' . esc_attr( $slug ) . '">' . $html . '</div>';
	}

	echo '</div>';

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

	gallery.querySelector( '.spg-gallery-btn' ).addEventListener( 'click', function () {
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
:root{--spg-cream:#f6f3ee;--spg-ink:#21201d;--spg-muted:#6d6862;--spg-accent:#b07d5c;--spg-sage:#e9ede6;
--spg-serif:"Cormorant Garamond","Playfair Display",Georgia,"Times New Roman",serif}

/* grid */
.spg-grid{display:grid;grid-template-columns:repeat(var(--spg-cols,3),1fr);gap:24px}
.spg-card{position:relative;display:block;aspect-ratio:16/7;overflow:hidden;text-decoration:none}
.spg-parallax-layer{position:absolute;top:-25%;left:0;width:100%;height:150%;overflow:hidden;
transform:translate3d(0,0,0);backface-visibility:hidden}
.spg-parallax-layer.is-visible{will-change:transform}
.spg-parallax-layer img{width:100%;height:100%;object-fit:cover;display:block}
.spg-card-overlay{position:absolute;inset:0;background:rgba(0,0,0,.3);transition:background .4s ease}
/* hover only where there is a real pointer, otherwise a tap sticks these on until
   the next tap somewhere else */
@media(hover:hover){.spg-card .spg-parallax-layer img{transition:scale .6s ease}
.spg-card:hover .spg-parallax-layer img{scale:1.06}
.spg-card:hover .spg-card-overlay{background:rgba(0,0,0,.45)}}
.spg-card,.spg-shot,.spg-tab-btn,.spg-gallery-btn,.spg-lb-btn,.spg-stage-open,.spg-stage-nav button{
touch-action:manipulation;-webkit-tap-highlight-color:transparent}
.spg-card-title{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
text-align:center;padding:0 18px;color:#fff;font-size:17px;font-weight:600;letter-spacing:.14em;
text-transform:uppercase;line-height:1.35}

/* Commercial / Residential tabs, switched with :checked so no script is needed */
.spg-tab-input{position:absolute;width:1px;height:1px;opacity:0}
.spg-tab-nav{display:flex;gap:8px;margin-bottom:28px;border-bottom:1px solid rgba(0,0,0,.12)}
.spg-tab-btn{display:inline-block;cursor:pointer;padding:12px 22px;
font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:inherit;opacity:.5;
border-bottom:2px solid transparent;margin-bottom:-1px;transition:opacity .3s ease,border-color .3s ease}
.spg-tab-btn:hover{opacity:.8}
.spg-tab-panel{display:none}
.spg-tab-input[data-spg-tab="commercial"]:checked ~ .spg-tab-panel[data-spg-panel="commercial"],
.spg-tab-input[data-spg-tab="residential"]:checked ~ .spg-tab-panel[data-spg-panel="residential"]{display:block}
.spg-tab-input[data-spg-tab="commercial"]:checked ~ .spg-tab-nav label[for$="-commercial"],
.spg-tab-input[data-spg-tab="residential"]:checked ~ .spg-tab-nav label[for$="-residential"]{opacity:1;border-color:currentColor}
.spg-tab-input:focus-visible ~ .spg-tab-nav{outline:2px solid var(--spg-accent);outline-offset:4px}
.spg-empty{opacity:.55;padding:30px 0}

/* single project: banner */
.spg-banner{background:var(--spg-cream);padding:48px 30px 40px}
.spg-banner-in{max-width:1500px;margin:0 auto;display:flex;align-items:flex-end;gap:40px;flex-wrap:wrap}
.spg-banner-head{flex:1 1 460px}
.spg-eyebrow{display:flex;align-items:center;gap:12px;margin:0 0 14px;font-size:11px;
letter-spacing:.28em;text-transform:uppercase;color:var(--spg-muted)}
.spg-eyebrow:before{content:"";width:34px;height:1px;background:var(--spg-accent)}
.spg-banner-title{font-family:var(--spg-serif);font-size:clamp(32px,4.4vw,54px);font-weight:400;
line-height:1.08;margin:0;color:var(--spg-ink)}
.spg-banner-sub{margin:14px 0 0;font-size:15px;color:var(--spg-muted)}
.spg-scroll{display:flex;align-items:center;gap:12px;padding-bottom:6px;text-decoration:none;
font-size:11px;letter-spacing:.24em;text-transform:uppercase;color:var(--spg-muted)}
.spg-scroll-ring{display:flex;align-items:center;justify-content:center;width:38px;height:38px;
border:1px solid var(--spg-accent);border-radius:50%;color:var(--spg-accent);font-size:15px;
transition:background .25s ease,color .25s ease}
.spg-scroll:hover .spg-scroll-ring{background:var(--spg-accent);color:#fff}
.spg-banner-quote{max-width:170px;margin:0;padding-bottom:6px;font-family:var(--spg-serif);
font-style:italic;font-size:17px;line-height:1.45;text-align:right;color:var(--spg-ink)}
.spg-banner-quote:after{content:"";display:block;width:44px;height:1px;margin:14px 0 0 auto;
background:var(--spg-accent)}

/* single project: gallery left, story right */
.spg-single{display:grid;grid-template-columns:1.8fr 1fr;gap:44px;
max-width:1500px;margin:0 auto;padding:36px 30px 70px}
.spg-feature{display:grid;grid-template-columns:2.1fr 1fr;gap:8px}
.spg-stage{position:relative;overflow:hidden;aspect-ratio:4/3;background:#eceae6}
.spg-stage-img{width:100%;height:100%;object-fit:cover;display:block}
.spg-stage:after{content:"";position:absolute;left:0;right:0;bottom:0;height:40%;pointer-events:none;
background:linear-gradient(to top,rgba(0,0,0,.55),rgba(0,0,0,0))}
.spg-stage-open{position:absolute;inset:0;padding:0;border:0;background:none;cursor:zoom-in}
.spg-stage-info{position:absolute;left:20px;bottom:16px;z-index:2;pointer-events:none;color:#fff}
.spg-stage-count{display:block;font-size:12px;letter-spacing:.14em;opacity:.85}
.spg-stage-caption{display:block;margin-top:3px;font-size:13px}
.spg-stage-nav{position:absolute;right:18px;bottom:16px;z-index:2;display:flex;gap:8px}
.spg-stage-nav button{display:flex;align-items:center;justify-content:center;width:30px;height:30px;
border:1px solid rgba(255,255,255,.75);border-radius:50%;background:rgba(0,0,0,.25);color:#fff;
font-size:15px;line-height:1;cursor:pointer;transition:background .25s ease}
.spg-stage-nav button:hover{background:rgba(0,0,0,.6)}
.spg-stack{display:grid;grid-template-rows:1fr 1fr;gap:8px}
.spg-shot{position:relative;display:block;width:100%;padding:0;border:0;overflow:hidden;
background:#eceae6;cursor:pointer}
.spg-stack .spg-shot{height:100%}
.spg-thumbs{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:8px}
.spg-thumbs .spg-shot{aspect-ratio:3/2}
.spg-shot.is-current{outline:2px solid var(--spg-accent);outline-offset:-2px}
.spg-gallery-btn{display:inline-flex;align-items:center;gap:12px;margin-top:18px;padding:11px 22px;
border:1px solid rgba(0,0,0,.22);border-radius:999px;background:none;cursor:pointer;
font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--spg-ink);
transition:border-color .25s ease,color .25s ease}
.spg-gallery-btn:hover{border-color:var(--spg-accent);color:var(--spg-accent)}

/* single project: story column */
.spg-text{position:sticky;top:40px;align-self:start}
.spg-heading{font-family:var(--spg-serif);font-size:clamp(26px,2.6vw,38px);font-weight:400;
line-height:1.16;margin:0 0 20px;color:var(--spg-ink)}
.spg-body{font-size:14.5px;line-height:1.85;color:var(--spg-muted)}
.spg-body p{margin:0 0 16px}
.spg-body strong{color:var(--spg-ink);font-weight:600}
.spg-callout{display:flex;gap:14px;margin-top:26px;padding:20px 22px;background:var(--spg-sage)}
.spg-callout svg{flex:none;margin-top:3px}
.spg-callout p{margin:0;font-family:var(--spg-serif);font-size:15px;line-height:1.55;color:var(--spg-ink)}
.spg-back-wrap{position:relative;margin-top:34px;padding-top:26px}
.spg-back-wrap:before{content:"";position:absolute;top:0;left:0;width:46px;height:1px;background:var(--spg-accent)}
.spg-back{display:inline-flex;align-items:center;gap:12px;font-size:14px;text-decoration:none;color:var(--spg-ink)}
.spg-back:hover{color:var(--spg-accent)}

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

@media(max-width:1024px){.spg-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:1100px){.spg-single{grid-template-columns:1fr;gap:36px}
.spg-text{position:static}}
@media(max-width:760px){.spg-banner{padding:32px 18px 28px}
.spg-banner-in{gap:24px}
.spg-banner-quote{max-width:none;text-align:left}
.spg-banner-quote:after{margin-left:0}
.spg-single{padding:26px 18px 50px}
.spg-feature{grid-template-columns:1fr}
.spg-stack{grid-template-rows:none;grid-template-columns:1fr 1fr}
.spg-stack .spg-shot{height:auto;aspect-ratio:3/2}
.spg-thumbs{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.spg-grid{grid-template-columns:1fr}.spg-card-title{font-size:15px}
.spg-lightbox{padding:14px}
.spg-lb-close{font-size:30px;top:6px;right:10px}
.spg-lb-prev,.spg-lb-next{font-size:38px;margin-top:-22px}}
@media(max-width:480px){.spg-thumbs{grid-template-columns:repeat(2,1fr)}}
';
}
