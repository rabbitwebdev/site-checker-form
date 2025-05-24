<?php
/**
 * Site Checker Form template.
 *
 * @param array $block The block settings and attributes.
 */

// Load values and assign defaults.

$rabbit_image             = get_field( 'rabbit_image' );
$background_color  = get_field( 'background_color' ); // ACF's color picker.
$text_color        = get_field( 'text_color' ); // ACF's color picker.
$rabbit_text = get_field('rabbit_text');
$content_align = get_field('content_align');
$sticky_image = get_field('sticky_image');
$checkout_form_id = get_field('checkout_form_id');
$the_wp_image = get_field('the_wp_image');
$wp_object_image = get_field('wp_object_image');

$show_shortcode = get_field('show_shortcode');
$show_customers = get_field('show_customers');
if($content_align == 'center') {
    $content_align = 'is-vertical is-layout-flex is-content-justification-center';
} elseif ($content_align == 'top') {
    $content_align = 'is-not-vertical is-content-justification-top';
} else {
     $content_align = 'is-not-';
}

// Support custom "anchor" values.
$anchor = '';
if ( ! empty( $block['anchor'] ) ) {
    $anchor = 'id="' . esc_attr( $block['anchor'] ) . '" ';
}

// Create class attribute allowing for custom "className" and "align" values.
$class_name = 'sitechecker-form';
if ( ! empty( $block['className'] ) ) {
    $class_name .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
    $class_name .= ' align' . $block['align'];
}
if ( $background_color || $text_color ) {
    $class_name .= ' has-custom-acf-color';
}

// Build a valid style attribute for background and text colors.
$styles = array( 'background-color: ' . $background_color, 'color: ' . $text_color );
$style  = implode( '; ', $styles );
$is_preview = isset($is_preview) ? $is_preview : false;
?>

<?php if (!$is_preview) { ?>
    <div <?php echo wp_kses_data(get_block_wrapper_attributes()); ?>>
<?php } ?>

<div <?php echo esc_attr( $anchor ); ?>class="<?php echo esc_attr( $class_name ); ?>  wp-block-group alignfull " style="<?php echo esc_attr( $style ); ?>">



<div class="driveway-calc-wrp">

  <?php echo do_shortcode( ' [site_check_form] ' ); ?>
 </div>


</div>
<?php if (!$is_preview) { ?>
    </div>
<?php } ?>