<?php
/**
 * Single project page: images on the left, text on the right.
 * Copy this file into your theme folder as single-spg_project.php if you want to edit it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$ids = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( get_the_ID(), 'spg_images', true ) ) ) );
	?>

	<div class="spg-single">

		<div class="spg-images">
			<?php
			if ( $ids ) {
				foreach ( array_values( $ids ) as $i => $id ) {
					$dir  = ( 0 === $i % 2 ) ? 1 : -1;
					$full = wp_get_attachment_image_url( $id, 'full' );
					$open = $full ? ' data-spg-full="' . esc_url( $full ) . '" role="button" tabindex="0"' : '';
					echo '<div class="spg-image-wrap"' . $open . '><div class="spg-parallax-layer" data-spg-dir="' . esc_attr( $dir ) . '">'
						. wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) )
						. '</div></div>';
				}
			} elseif ( has_post_thumbnail() ) {
				$full = get_the_post_thumbnail_url( get_the_ID(), 'full' );
				$open = $full ? ' data-spg-full="' . esc_url( $full ) . '" role="button" tabindex="0"' : '';
				echo '<div class="spg-image-wrap"' . $open . '><div class="spg-parallax-layer" data-spg-dir="1">'
					. get_the_post_thumbnail( get_the_ID(), 'large' )
					. '</div></div>';
			}
			?>
		</div>

		<div class="spg-text">
			<h1><?php the_title(); ?></h1>

			<div class="spg-body">
				<?php the_content(); ?>
			</div>

			<?php
			// Falls back to the homepage on sites that don't have a "featuredworks" page,
			// and is filterable so a site owner can point it anywhere without editing the plugin.
			$back_page = get_page_by_path( 'featuredworks' );
			$back_url  = $back_page ? get_permalink( $back_page ) : home_url( '/' );
			$back_url  = apply_filters( 'spg_back_link_url', $back_url );
			?>
			<a class="spg-back" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to Featured Works', 'simple-portfolio-grid' ); ?></a>
		</div>

	</div>

	<?php
endwhile;

get_footer();
