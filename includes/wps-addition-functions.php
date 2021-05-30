<?php
/**
 * hook admin menu
 */
add_action('admin_menu', 'wps_addition_config_menu');

/**
 * add new menu element
 */
function wps_addition_config_menu()
{
    add_menu_page(
        'Additional theme customization from wps-d',
        'Config wps',
        'manage_options',
        'wps-addition-config/includes/wps-addition-config-page.php'
    );
}

/**
 * Registering the settings.
 * Settings will be stored in an array, not one setting = one option.
 */
add_action('admin_init', 'plugin_wps_config_settings');
function plugin_wps_config_settings()
{
    register_setting('option_group_addition_config_wps', 'option_addition_config_wps', 'sanitize_callback');
    add_settings_section('section_id', '', '', 'wps_config_page');
    add_settings_field('addition_css_wps', 'Addition css', 'addition_css', 'wps_config_page', 'section_id');
    add_settings_field('addition_on_off', 'Enabling functions', 'addition_on_off', 'wps_config_page', 'section_id');
    add_settings_field('addition_on_off_coupon', 'Enabling coupon functions', 'addition_on_off_coupon', 'wps_config_page', 'section_id');
}

/**
 * add section custom css to setting page
 */
function addition_css()
{
    $val = get_option('option_addition_config_wps');
    $val = $val ? $val['css'] : null;
    ?>
    <textarea rows="20" cols="100" name="option_addition_config_wps[css]"
              value="<?php echo esc_attr($val) ?>"><?php echo esc_attr($val) ?></textarea>
    <?php
}

function addition_on_off()
{
    $val = get_option('option_addition_config_wps');
    $val = $val ? $val['onoff'] : null;
    ?>
    <label><input type="checkbox" name="option_addition_config_wps[onoff]" value="1" <?php checked(1, $val) ?> />
        On/Off (add text to button "Add to cart", limits custom field 500, add vendore name to
        shipstation )
    </label>
    <?php
}

/**
 * jumper auto coupon application
 */
function addition_on_off_coupon()
{
    $val = get_option('option_addition_config_wps');
    $val = $val ? $val['onoffcoupon'] : null;
    ?>
    <label><input type="checkbox" name="option_addition_config_wps[onoffcoupon]" value="1" <?php checked(1, $val) ?> />
        On/Off (auto coupon application)
    </label>
    <?php
}

function sanitize_callback($options)
{

    foreach ($options as $name => & $val) {
        if ($name == 'input')
            $val = strip_tags($val);

        if ($name == 'checkbox')
            $val = intval($val);
    }

    return $options;
}

/**
 * add custom css to front page
 */
add_action('wp_head', 'insert_custom_css');

function insert_custom_css()
{
    $onOff = get_option('option_addition_config_wps');
    $onOff = $onOff ? $onOff['onoff'] : null;

    if (!is_null($onOff)) {
        $val = get_option('option_addition_config_wps');
        $val = $val ? $val['css'] : null;
        echo "\n" . '<style type="text/css">' . $val . '</style>';
    }
}

/**
 * ADDITION FUNCTIONAL FROM FUNCTION.PHP
 * Special safe here, because when changing or updating the theme
 */
$config = get_option('option_addition_config_wps');
$onOff = $config ? $config['onoff'] : null;
$onOffCoupon = $config ? $config['onoffcoupon'] : null;

if (!is_null($onOffCoupon)) {
    add_action('woocommerce_before_cart_table', 'add_coupon_automatically');
    add_action('woocommerce_before_checkout_form', 'add_coupon_automatically');
}

if (!is_null($onOff)) {
    add_filter('woocommerce_get_price_html', 'woocommerce_saved_sales_price', 10, 2);
    add_filter('woocommerce_product_add_to_cart_text', 'custom_add_to_cart_price', 20, 2);
    add_filter('woocommerce_shipstation_export_custom_field_2', 'shipstation_custom_field_2');
    add_filter('postmeta_form_limit', 'meta_limit_increase');
}

function woocommerce_saved_sales_price($price, $product)
{
    $output_array = array();

    preg_match_all('/>([0-9.]+)</', $price, $output_array);

    if (count($output_array)) {
        $output = $output_array[1];

        if (count($output) == 2) {
            list($regular_price, $sale_price) = $output;

            $percentage = round((($regular_price - $sale_price) / $regular_price) * 100);

            return $price . '<br/><span class="discount">' . sprintf(__('save %s', 'woocommerce'), $percentage . '%') . '</span>';
        } else {
            return $price;
        }
    } else {
        return $price;
    }
}

function custom_add_to_cart_price($button_text, $product)
{
    if ($product->is_type('variable')) {
        if (!is_product()) {
            return $product->get_price_html();
        } else {
            return $product->get_price_html();
        }
    } else {
        return $product->get_price_html();
    }
}

/**
 * Add vendore name to shipstation
 */
function shipstation_custom_field_2()
{
    return 'vendore_name';
}

/**
 * Add 500 extra fields to post
 */
function meta_limit_increase($limit)
{
    return 500;
}

/**
 * Auto add coupon for product in cart and checkout
 * Add coupon when user views cart before checkout (shipping calculation page).
 * Add coupon when user views checkout page (would not be added otherwise, unless user views cart first).
 */

if (!function_exists('add_coupon_automatically')) {

    function add_coupon_automatically()
    {

        global $woocommerce;

        $items = $woocommerce->cart->get_cart();

        foreach ($items as $item => $values) {
            $idProduct = $values['data']->get_id();
            $args = [
                'meta_key' => 'product_ids',
                'meta_value' => $idProduct,
                'post_type' => 'shop_coupon',
                'post_status' => 'any',
                'posts_per_page' => 1
            ];
            $posts = get_posts($args);
            $coupon = (!empty($posts)) ? $posts[0]->post_title : null;

            if (!is_null($coupon)) {

                /**
                 * If coupon has been already been added remove it.
                 */
                if ($woocommerce->cart->has_discount(sanitize_text_field($coupon))) {
                    $woocommerce->cart->remove_coupons(sanitize_text_field($coupon));
                }

                /**
                 * Check date expires coupon and them add coupon
                 */
                $dateExpires = get_post_meta($posts[0]->ID, 'date_expires');

                if (strtotime("now") < $dateExpires[0]) {
                    if ($woocommerce->cart->add_discount(sanitize_text_field($coupon))) {
                        wc_print_notices('Coupon automatically applied!');
                    }
                }

                /**
                 * Manually recalculate totals.  If you do not do this, a refresh is required before user will see updated totals when discount is removed.
                 */
                $woocommerce->cart->calculate_totals();
            }
        }
    }
}
