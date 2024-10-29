<?php
/**
 * Plugin Name: Astro WooCommerce Free Gift
 * Plugin URI: http://astrotemplates.com/
 * Description: This plugin allows you to create a list of free Gift for any product item
 * Version: 1.0.1
 * Author: AstroTemplates
 * Author URI: http://themeforest.net/user/astrotemplates
 *
 * License: GPLv2 or later
 * Text Domain: astro-woocommerce-free-gift
 * Domain Path: /languages/
 *
 */

if (!defined('ABSPATH')) exit;

if(!class_exists('Astro_WooCommerce_Free_Gift')) :

    final class Astro_WooCommerce_Free_Gift {
        private static $_instance;

        private $vertion = '1.0.1';

        private $_data_key = '_productgift_ids';

        public static function get_instance() {
            if( is_null(self::$_instance)) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        public function init(){
            add_action( 'plugins_loaded', array($this, 'plugins_loaded') );
            $this->define_constants();

            add_action('plugins_loaded', array($this, 'init_hooks'), 30);
        }

        protected function define_constants(){
            $this->define('ASTWFG_PLUGIN_FILE', __FILE__);
        }

        private function define($name, $value){
            if (!defined($name)) {
                define($name, $value);
            }
        }

        private function is_request($type)
        {
            switch ($type) {
                case 'admin' :
                    return is_admin();
                case 'ajax' :
                    return defined('DOING_AJAX');
                case 'cron' :
                    return defined('DOING_CRON');
                case 'frontend' :
                    return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON');
            }
        }

        public function plugins_loaded(){
            if ( ! function_exists( 'WC' ) ) {
                add_action( 'admin_notices', array($this, 'install_woocommerce_admin_notice') );
            }
        }

        public function install_woocommerce_admin_notice(){
            ?>
            <div class="error">
                <p><?php _e( 'Astro WooCommerce Free Gift is enabled but not effective. It requires WooCommerce in order to work.', 'astro-woocommerce-free-gift' ); ?></p>
            </div>
            <?php
        }

        public function init_hooks(){
            register_activation_hook(__FILE__, array($this, 'installed_callback'));
            add_action('init', array($this, 'load_domain'));

            if($this->is_request('admin')) {
                add_action('woocommerce_product_options_related', array($this, 'backend_options'));
                add_action('woocommerce_process_product_meta', array($this, 'save_data'), 10, 2);
            } else {
                add_action('woocommerce_add_to_cart', array($this, 'add_gift_to_cart'), 50, 6);
                add_action('woocommerce_cart_item_removed', array($this, 'remove_cart_item'), 10, 2);
                add_action('woocommerce_single_product_summary', array($this, 'single_product_gift'), 20);
                add_filter('woocommerce_cart_item_quantity', array($this, 'item_quantity'), 10, 3);
                add_filter('woocommerce_cart_item_class', array($this, 'cart_class'), 10, 3);

                add_action('wp_enqueue_scripts', array($this, 'load_scripts'));
            }
        }

        public function installed_callback(){

        }

        public function load_domain(){
            $locale = get_locale();

            load_textdomain('astro-woocommerce-free-gift', WP_LANG_DIR . '/astro-woocommerce-free-gift/astro-woocommerce-free-gift-' . $locale . '.mo');
            load_plugin_textdomain('astro-woocommerce-free-gift', false, plugin_basename(dirname(__FILE__)) . "/languages");
        }

        public function load_scripts(){
            wp_enqueue_script('jquery');
            wp_enqueue_style('astro-woofg-frontend', plugin_dir_url(__FILE__) . 'assets/css/frontend.css', false, $this->vertion);
            wp_enqueue_script('astro-woofg-js', plugin_dir_url(__FILE__) . 'assets/js/frontend.js', array('jquery'), $this->vertion, true);
        }

        public function backend_options(){
            global $post;
            ?>
            <div class="options_group">

                <p class="form-field">
                    <label for="grouped_products"><?php _e( 'Gift product items', 'astro-woocommerce-free-gift' ); ?></label>
                    <select class="wc-product-search" multiple="multiple" style="width: 50%;" id="productgift_ids" name="productgift_ids[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'astro-woocommerce-free-gift' ); ?>"
                            data-action="woocommerce_json_search_products" data-exclude="<?php echo intval( $post->ID ); ?>">
                        <?php
                        $product_ids = array_filter(array_map('absint', (array)get_post_meta($post->ID, '_productgift_ids', true)));

                        foreach ( $product_ids as $product_id ) {
                            $product = wc_get_product( $product_id );
                            if ( is_object( $product ) ) {
                                echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
                            }
                        }
                        ?>
                    </select> <?php echo wc_help_tip( __( 'This lets you choose which products are part of this group.', 'astro-woocommerce-free-gift' ) ); ?>
                </p>

            </div>
            <?php
        }

        public function save_data($post_id, $post){
            $productgift_ids = isset($_POST['productgift_ids']) ? (array) $_POST['productgift_ids'] : array();
            update_post_meta($post_id, $this->_data_key, $productgift_ids);
        }

        public function add_gift_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data){
            $gift_ids = array_filter(array_map('absint', (array)get_post_meta($product_id, $this->_data_key, true)));

            if (!empty($gift_ids)) {
                foreach ($gift_ids as $gift_id) {
                    $product_status = get_post_status($gift_id);
                    $gift_variation_id = $this->create_gift_variation($gift_id);

                    if (false !== WC()->cart->add_to_cart($gift_id, $quantity, $gift_variation_id, array(__('Type', 'astro-woocommerce-free-gift') => __('Free gift', 'astro-woocommerce-free-gift')), array('gift_for' => $product_id, 'gift_for_item' => $cart_item_key)) && 'publish' === $product_status) {
                        do_action('woocommerce_ajax_added_to_cart', $gift_id);

                        if (get_option('woocommerce_cart_redirect_after_add') == 'yes') {
                            wc_add_to_cart_message(array($gift_id => $quantity), true);
                        }
                    }
                }
            }
        }

        public function remove_cart_item($cart_item_key_removed, $cart){
            foreach ($cart->cart_contents as $cart_item_key => $cart_item) {
                if (isset($cart_item['gift_for_item']) && $cart_item_key_removed === $cart_item['gift_for_item']) {
                    $cart->remove_cart_item($cart_item_key);
                }
            }
        }

        private function create_gift_variation($product_id)
        {
            //check variation product exist
            $product_variation = get_posts(array(
                'post_parent' => $product_id,
                's' => 'astro_product_gift_item',
                'post_type' => 'product_variation',
                'posts_per_page' => 1
            ));

            if (!empty($product_variation)) {
                $this->update_gift_metadata($product_variation[0]->ID, $product_id);
                return $product_variation[0]->ID;
            }

            $author = get_users(array(
                'orderby' => 'nicename',
                'role' => 'administrator',
                'number' => 1
            ));

            $variation = array(
                'post_author' => $author[0]->ID,
                'post_status' => 'publish',
                'post_name' => 'product-gift-variation-' . absint($product_id),
                'post_parent' => $product_id,
                'post_title' => 'astro_product_gift_item',
                'post_type' => 'product_variation',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            );

            $_gift_id = wp_insert_post($variation);

            $this->update_gift_metadata($_gift_id, $product_id);

            return $_gift_id;
        }

        private function update_gift_metadata($gift_id, $product_id) {
            update_post_meta($gift_id, '_price', 0);
            update_post_meta($gift_id, '_sale_price', 0);
            update_post_meta($gift_id, '_regular_price', get_post_meta($product_id, '_regular_price', true));
            update_post_meta($gift_id, '_virtual', get_post_meta($product_id, '_virtual', true));
            //update_post_meta( $gift_id, '_sold_individually', 'yes');
        }

        public function item_quantity($product_quantity, $cart_item_key, $cart_item){
            $_product = $cart_item['data'];
            if(!empty($cart_item['gift_for'])) {
                ob_start();
                ?>
                <div class="quantity-girf" data-product_id="<?php echo absint($_product->id) ?>" data-gift_for="<?php echo (!empty($cart_item['gift_for'])) ? absint($cart_item['gift_for']) : 0; ?>">
                    <span><?php echo absint($cart_item['quantity'])?></span>
                    <input type="hidden" name="cart[<?php echo esc_attr($cart_item_key)?>][qty]" value="<?php echo absint($cart_item['quantity'])?>" />
                </div>
                <?php
                $product_quantity = ob_get_clean();
            }
            echo $product_quantity;
        }

        public function single_product_gift() {
            global $post;
            $product_ids = array_filter(array_map('absint', (array)get_post_meta($post->ID, $this->_data_key, true)));

            if (empty($product_ids)) return;
            $_count = count($product_ids);

            $_total_price = 0;
            ob_start();
            foreach ($product_ids as $product_id) :
                $product = new WC_Product($product_id);
                ?>
                <li>
                    <a class="product-image" href="<?php echo esc_url(get_permalink($product->get_id())); ?>"
                       title="<?php echo esc_attr($product->get_title()); ?>">
                        <?php echo $product->get_image(); ?>
                    </a>

                    <div class="product-detail">
                        <?php echo $product->get_price_html();?>
                    </div>
                </li>
                <?php

                $_total_price += absint($product->get_price());
            endforeach;
            $_li_html = ob_get_clean();

            echo '<div class="single-product-gifts-wrapper">';
            echo '<h4><a href="javascript:viod(0)">'.sprintf(_n('%d Promotion gift', '%d Promotion gifts', $_count, 'astro-woocommerce-free-gift'), $_count).' ('.wc_price( $_total_price ).')</a></h4>';
            echo '<ul class="single-product-gifts">';
            echo wp_kses_post($_li_html);
            echo '</ul></div>';
        }

        public function cart_class($class, $cart_item, $cart_item_key){
            if(!empty($cart_item['gift_for'])) {
                $class .= ' cart_item_gift';
            }

            return $class;
        }
    }

endif;

if (!function_exists('Astro_WooCommerce_Free_Gift')) {
    function Astro_WooCommerce_Free_Gift()
    {
        return Astro_WooCommerce_Free_Gift::get_instance();
    }
}

Astro_WooCommerce_Free_Gift()->init();