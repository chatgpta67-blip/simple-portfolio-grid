<?php
/**
 * Single project page: banner, gallery on the left, story on the right.
 * Copy this file into your theme folder as single-spg_project.php if you want to edit it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$post_id = get_the_ID();
	$ids     = array_values( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, 'spg_images', true ) ) ) ) );

	if ( ! $ids && has_post_thumbnail() ) {
		$ids = array( get_post_thumbnail_id( $post_id ) );
	}

	$images = array();

	foreach ( $ids as $id ) {
		$src = wp_get_attachment_image_url( $id, 'large' );

		if ( ! $src ) {
			continue;
		}

		// 2048px keeps the popup sharp without making every arrow click pull down a
		// multi-megabyte original. WordPress serves the full file when it is smaller.
		$full    = wp_get_attachment_image_url( $id, '2048x2048' );
		$caption = wp_get_attachment_caption( $id );

		$images[] = array(
			'src'     => $src,
			'full'    => $full ? $full : $src,
			'alt'     => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
			'caption' => $caption ? $caption : get_the_title( $id ),
		);
	}

	$subtitle = get_post_meta( $post_id, 'spg_subtitle', true );
	$quote    = get_post_meta( $post_id, 'spg_quote', true );
	$heading  = get_post_meta( $post_id, 'spg_heading', true );
	$callout  = get_post_meta( $post_id, 'spg_callout', true );

	$stack  = array_slice( $images, 1, 2, true );
	$thumbs = array_slice( $images, 3, 12, true );
	?>

	<section class="spg-banner">
		<div class="spg-banner-in">

			<div class="spg-banner-head">
				<p class="spg-eyebrow"><?php esc_html_e( 'Featured Works', 'simple-portfolio-grid' ); ?></p>
				<h1 class="spg-banner-title"><?php the_title(); ?></h1>
				<?php if ( $subtitle ) : ?>
					<p class="spg-banner-sub"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>

			<a class="spg-scroll" href="#spg-story">
				<span class="spg-scroll-ring" aria-hidden="true">&darr;</span>
				<?php esc_html_e( 'Scroll', 'simple-portfolio-grid' ); ?>
			</a>

			<?php if ( $quote ) : ?>
				<p class="spg-banner-quote"><?php echo esc_html( $quote ); ?></p>
			<?php endif; ?>

		</div>
	</section>

	<div class="spg-single" id="spg-story">

		<?php if ( $images ) : ?>
			<div class="spg-gallery" data-spg-images="<?php echo esc_attr( wp_json_encode( $images ) ); ?>">

				<div class="spg-feature">

					<div class="spg-stage">
						<img class="spg-stage-img" src="<?php echo esc_url( $images[0]['src'] ); ?>" alt="<?php echo esc_attr( $images[0]['alt'] ); ?>" />
						<button type="button" class="spg-stage-open" aria-label="<?php esc_attr_e( 'View full size', 'simple-portfolio-grid' ); ?>"></button>
						<div class="spg-stage-info">
							<span class="spg-stage-count"></span>
							<span class="spg-stage-caption"></span>
						</div>
						<div class="spg-stage-nav">
							<button type="button" class="spg-stage-prev" aria-label="<?php esc_attr_e( 'Previous image', 'simple-portfolio-grid' ); ?>">&#8249;</button>
							<button type="button" class="spg-stage-next" aria-label="<?php esc_attr_e( 'Next image', 'simple-portfolio-grid' ); ?>">&#8250;</button>
						</div>
					</div>

					<?php if ( $stack ) : ?>
						<div class="spg-stack">
							<?php foreach ( $stack as $i => $image ) : ?>
								<button type="button" class="spg-shot" data-spg-index="<?php echo (int) $i; ?>">
									<span class="spg-parallax-layer" data-spg-dir="<?php echo ( 0 === $i % 2 ) ? 1 : -1; ?>">
										<img src="<?php echo esc_url( $image['src'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy" />
									</span>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

				</div>

				<?php if ( $thumbs ) : ?>
					<div class="spg-thumbs">
						<?php foreach ( $thumbs as $i => $image ) : ?>
							<button type="button" class="spg-shot" data-spg-index="<?php echo (int) $i; ?>">
								<span class="spg-parallax-layer" data-spg-dir="<?php echo ( 0 === $i % 2 ) ? 1 : -1; ?>">
									<img src="<?php echo esc_url( $image['src'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy" />
								</span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<button type="button" class="spg-gallery-btn">
					<?php esc_html_e( 'View Full Gallery', 'simple-portfolio-grid' ); ?>
					<span aria-hidden="true">&rarr;</span>
				</button>

			</div>
		<?php endif; ?>

		<div class="spg-text">

			<p class="spg-eyebrow"><?php esc_html_e( 'About the Space', 'simple-portfolio-grid' ); ?></p>

			<?php if ( $heading ) : ?>
				<h2 class="spg-heading"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>

			<div class="spg-body">
				<?php the_content(); ?>
			</div>

			<?php if ( $callout ) : ?>
				<div class="spg-callout">
					<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
						<path d="M4 20c0-9 6-15 16-15 0 10-6 15-16 15z" fill="#6b8b62" />
						<path d="M4 20C7 14 11 10 17 7" stroke="#ffffff" stroke-width="1.2" fill="none" opacity=".7" />
					</svg>
					<p><?php echo nl2br( esc_html( $callout ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php
			// Falls back to the homepage on sites that don't have a "featuredworks" page,
			// and is filterable so a site owner can point it anywhere without editing the plugin.
			$back_page = get_page_by_path( 'featuredworks' );
			$back_url  = $back_page ? get_permalink( $back_page ) : home_url( '/' );
			$back_url  = apply_filters( 'spg_back_link_url', $back_url );
			?>
			<div class="spg-back-wrap">
				<a class="spg-back" href="<?php echo esc_url( $back_url ); ?>">
					<span aria-hidden="true">&larr;</span>
					<?php esc_html_e( 'Back to Featured Works', 'simple-portfolio-grid' ); ?>
				</a>
			</div>

		</div>

	</div>

	<?php
endwhile;

get_footer();
