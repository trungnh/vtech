<script type='text/javascript' src='/wp-content/themes/flash/js/isotope.pkgd.min.js?ver=4.9.4'></script>
<?php
/**
 * The template for displaying portfolio widget.
 *
 * This template can be overridden by copying it to yourtheme/flash-toolkit/content-widget-portfolio.php.
 *
 * HOWEVER, on occasion FlashToolkit will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     http://docs.themegrill.com/flash-toolkit/template-structure/
 * @author  ThemeGrill
 * @package FlashToolkit/Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(!function_exists('get_product_price')){
	function get_product_price($product_id)
	{
		$regular_price = get_post_meta($product_id, '_regular_price');
		$sale_price = get_post_meta($product_id, '_regular_price');
		if($sale_price)
			return number_format_i18n($sale_price[0], 0);
		return number_format_i18n($regular_price[0], 0);
	}
}

$terms = get_terms( 'product_cat' );
$tabItem = '<div class="productFilter clearfix">';
$tabContent = '<div class="productContainer">';
$defaultDisplay = reset($terms)->slug;
$count = 0;
foreach($terms as $term){
    $current = '';
    if($count == 0){$current = 'current';}
	$termLink = get_term_link($term);
    $tabItem .= '<a href="#" data-filter=".' . $term->slug . '" class="filter-item '.$current.'" data-link="'.$termLink.'">' . $term->name . '</a>';

    $project_query = new WP_Query(
        array (
            'post_type'      => 'product',
            'posts_per_page' => 4,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'id',
                    'terms'    => $term->term_id
                ),
            ),
        )
    );
    while ( $project_query->have_posts() ): $project_query->the_post();
        global $post;

        $id          = $post->ID;

        $tabContent .= '<div class="'.$term->slug.' objects tg-column-4">';
        $tabContent .= '<a href="' . get_the_permalink( $post->ID ) . '">'.get_the_post_thumbnail( $post->ID, array(400, 400) ) . '</a>';
        $tabContent .= '<div class="product-info"><h4 class="product-title"><a href="' . get_the_permalink( $post->ID ) . '">' . get_the_title( $post->ID ) . '</a></h4>';
        $tabContent .= '<p class="woocommerce-Price-amount amount">' . get_product_price($post->ID) . '₫</p></div>';
        $tabContent .= '</div>';

    endwhile;
    wp_reset_postdata();
    $count++;
}
$tabItem .= '</div>';
$tabContent .= '</div>';

echo '<div class="tg-column-wrapper">';
echo $tabItem;
echo $tabContent;
echo '</div>';
?>
<script>
    jQuery(document).ready(function(){
        var $container = jQuery('.productContainer');
        $container.isotope({
            filter: '.<?php echo $defaultDisplay;?>',
            animationOptions: {
                duration: 750,
                easing: 'linear',
                queue: false
            }
        });

        jQuery('.productFilter a.filter-item').click(function(){
            jQuery('.productFilter .current').removeClass('current');
            jQuery(this).addClass('current');

            var selector = jQuery(this).attr('data-filter');
            $container.isotope({
                filter: selector,
                animationOptions: {
                    duration: 750,
                    easing: 'linear',
                    queue: false
                }
            });
			jQuery('.product-readmore a').attr('href', jQuery(this).attr('data-link'));
            return false;
        });
    });
</script>
