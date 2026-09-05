<?php
/**
 * Single project page: hero, gallery on the left, story on the right.
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
		$alt     = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );

		$images[] = array(
			'src'  => $src,
			'full' => $full ? $full : $src,
			'alt'  => $alt,
			// Deliberately no fall back to the attachment title: that is the raw
			// upload filename, which reads as junk over the image.
			'caption' => $caption ? $caption : $alt,
		);
	}

	$subtitle = get_post_meta( $post_id, 'spg_subtitle', true );
	$quote    = get_post_meta( $post_id, 'spg_quote', true );
	$heading  = get_post_meta( $post_id, 'spg_heading', true );
	$callout  = get_post_meta( $post_id, 'spg_callout', true );

	$sides  = array_slice( $images, 1, 2, true );
	$thumbs = array_slice( $images, 3, 8, true );
	?>

	<div class="spg-project">
		<div class="spg-page">

			<section class="spg-hero">
				<div class="spg-hero-head">
					<p class="spg-eyebrow"><?php esc_html_e( 'Featured Works', 'simple-portfolio-grid' ); ?></p>
					<h1 class="spg-title"><?php the_title(); ?></h1>
					<?php if ( $subtitle ) : ?>
						<p class="spg-subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( $quote ) : ?>
					<p class="spg-quote"><?php echo esc_html( $quote ); ?></p>
				<?php endif; ?>
			</section>

			<section class="spg-content<?php echo $images ? '' : ' spg-content--solo'; ?>">

				<?php if ( $images ) : ?>
					<div class="spg-gallery-col">
						<div class="spg-gallery" data-spg-images="<?php echo esc_attr( wp_json_encode( $images ) ); ?>">

							<div class="spg-stage">
								<img class="spg-stage-img" src="<?php echo esc_url( $images[0]['src'] ); ?>" alt="<?php echo esc_attr( $images[0]['alt'] ); ?>" />
								<button type="button" class="spg-stage-open" aria-label="<?php esc_attr_e( 'View full size', 'simple-portfolio-grid' ); ?>"></button>
								<p class="spg-stage-meta">
									<span class="spg-stage-count"></span>
									<span class="spg-stage-caption"></span>
								</p>
								<div class="spg-stage-nav">
									<button type="button" class="spg-stage-prev" aria-label="<?php esc_attr_e( 'Previous image', 'simple-portfolio-grid' ); ?>">&#8249;</button>
									<button type="button" class="spg-stage-next" aria-label="<?php esc_attr_e( 'Next image', 'simple-portfolio-grid' ); ?>">&#8250;</button>
								</div>
							</div>

							<?php foreach ( $sides as $i => $image ) : ?>
								<button type="button" class="spg-shot spg-side" data-spg-index="<?php echo (int) $i; ?>">
									<span class="spg-parallax-layer" data-spg-dir="<?php echo ( 0 === $i % 2 ) ? 1 : -1; ?>">
										<img src="<?php echo esc_url( $image['src'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy" />
									</span>
								</button>
							<?php endforeach; ?>

							<?php if ( $thumbs ) : ?>
								<div class="spg-thumbs">
									<?php foreach ( $thumbs as $i => $image ) : ?>
										<button type="button" class="spg-shot spg-thumb" data-spg-index="<?php echo (int) $i; ?>">
											<span class="spg-parallax-layer" data-spg-dir="<?php echo ( 0 === $i % 2 ) ? 1 : -1; ?>">
												<img src="<?php echo esc_url( $image['src'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy" />
											</span>
										</button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

						</div>

						<button type="button" class="spg-gallery-btn">
							<?php esc_html_e( 'View Full Gallery', 'simple-portfolio-grid' ); ?>
							<span aria-hidden="true">&rarr;</span>
						</button>
					</div>
				<?php endif; ?>

				<article class="spg-copy">

					<p class="spg-eyebrow"><?php esc_html_e( 'About the Space', 'simple-portfolio-grid' ); ?></p>

					<?php if ( $heading ) : ?>
						<h2 class="spg-heading"><?php echo esc_html( $heading ); ?></h2>
					<?php endif; ?>

					<div class="spg-body">
						<?php the_content(); ?>
					</div>

					<?php if ( $callout ) : ?>
						<div class="spg-statement">
							<span class="spg-leaf" aria-hidden="true"></span>
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
					<a class="spg-back" href="<?php echo esc_url( $back_url ); ?>">
						<span aria-hidden="true">&larr;</span>
						<?php esc_html_e( 'Back to Featured Works', 'simple-portfolio-grid' ); ?>
					</a>

				</article>

			</section>

		</div>
	</div>

	<?php
endwhile;

get_footer();
