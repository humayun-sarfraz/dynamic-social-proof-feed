<?php
declare(strict_types=1);
/**
 * Plugin Name: Dynamic Social Proof Feed
 * Plugin URI: https://github.com/humayun-sarfraz/dynamic-social-proof-feed
 * Description: Display real-time social proof popups with product images, purchases (real or fake), page views, and live/fake product viewers. Integrates with WooCommerce, EDD, and more.
 * Version: 1.4.0
 * Author: Humayun Sarfraz
 * Author URI: https://github.com/humayun-sarfraz
 * Text Domain: dynamic-social-proof-feed
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

if ( ! class_exists( 'DSPF_Main' ) ) :

final class DSPF_Main {

    const VERSION = '1.4.0';

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        if ( is_admin() ) {
            require_once plugin_dir_path(__FILE__) . 'admin/class-dspf-admin.php';
            new DSPF_Admin();
        }
        add_action( 'template_redirect', [ $this, 'log_page_view' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'log_woo_purchase' ], 10, 1 );
        add_action( 'edd_complete_purchase', [ $this, 'log_edd_purchase' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_nopriv_dspf_fetch_feed', [ $this, 'ajax_fetch_feed' ] );
        add_action( 'wp_ajax_dspf_fetch_feed', [ $this, 'ajax_fetch_feed' ] );
        add_action( 'wp_ajax_nopriv_dspf_product_viewers', [ $this, 'ajax_product_viewers' ] );
        add_action( 'wp_ajax_dspf_product_viewers', [ $this, 'ajax_product_viewers' ] );
        add_action( 'wp_footer', [ $this, 'output_product_viewers_div' ] );
        add_action( 'woocommerce_single_product_summary', [ $this, 'dspf_wc_show_product_viewers_bar' ], 25 );
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'dynamic-social-proof-feed',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    public function enqueue_scripts() {
        $opts = get_option('dspf_settings', []);
        if ( !empty($opts['hide_logged_in']) && is_user_logged_in() ) return;
        if ( !empty($opts['hide_on_ids']) ) {
            $ids = array_map('trim', explode(',', $opts['hide_on_ids']));
            $cur = get_queried_object_id();
            if ( in_array($cur, $ids) ) return;
        }
        wp_register_style( 'dspf-style', plugins_url( 'assets/dspf-style.css', __FILE__ ), [], self::VERSION );
        wp_enqueue_style( 'dspf-style' );
        wp_register_script( 'dspf-script', plugins_url( 'assets/dspf-script.js', __FILE__ ), [ 'jquery' ], self::VERSION, true );
        $opts_js = $opts;
        $opts_js['ajax_url'] = esc_url( admin_url( 'admin-ajax.php' ) );
        $opts_js['nonce'] = wp_create_nonce( 'dspf_feed_nonce' );
        if (function_exists('is_product') && is_product()) {
            $opts_js['product_id'] = get_queried_object_id();
        }
        wp_localize_script( 'dspf-script', 'DSPF_Ajax', $opts_js );
        wp_enqueue_script( 'dspf-script' );
    }

    public function log_page_view() {
        $opts = get_option('dspf_settings', []);
        if ( is_admin() ) return;
        if ( !empty($opts['hide_logged_in']) && is_user_logged_in() ) return;
        if ( empty($opts['enable_views']) ) return;
        $page_id = get_queried_object_id();
        if ( ! $page_id ) return;
        if (
            function_exists('is_product') &&
            is_product() &&
            !empty($opts['show_product_viewers']) &&
            !empty($opts['show_live_viewers']) &&
            empty($opts['fake_viewers_enable'])
        ) {
            $this->track_product_viewer($page_id);
        }
        $activity = [
            'type'      => 'page_view',
            'title'     => get_the_title( $page_id ),
            'post_id'   => absint( $page_id ),
            'time'      => time(),
            'ip'        => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ];
        $this->save_activity( $activity );
    }

    private function track_product_viewer($product_id) {
        if ( empty($product_id) ) return;
        $key = 'dspf_product_viewers_' . $product_id;
        $session_id = $this->get_session_id();
        $arr = get_transient($key);
        if ( !is_array($arr) ) $arr = [];
        $now = time();
        foreach($arr as $sid => $ts) {
            if ($now - $ts > 60) unset($arr[$sid]);
        }
        $arr[$session_id] = $now;
        set_transient($key, $arr, 120);
    }

    public function ajax_product_viewers() {
        check_ajax_referer( 'dspf_feed_nonce', 'nonce' );
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $count = 0;
        if($product_id) {
            $key = 'dspf_product_viewers_' . $product_id;
            $arr = get_transient($key);
            if ( is_array($arr) ) {
                $now = time();
                foreach($arr as $sid => $ts) {
                    if($now - $ts <= 60) $count++;
                }
            }
        }
        wp_send_json_success(['count' => $count]);
    }

    public function output_product_viewers_div() {
        $opts = get_option('dspf_settings', []);
        if(
            !empty($opts['show_product_viewers']) &&
            function_exists('is_product') &&
            is_product() &&
            ( !empty($opts['show_live_viewers']) || !empty($opts['fake_viewers_enable']) )
        ) {
            $product_id = get_queried_object_id();
            echo '<div id="dspf-product-viewers-bar" style="display:none"></div>';
            echo '<script>window.DSPF_ProductID = '.intval($product_id).';</script>';
        }
    }

    public function dspf_wc_show_product_viewers_bar() {
        if ( ! function_exists('is_product') || ! is_product() ) return;
        $opts = get_option('dspf_settings', []);
        if ( empty($opts['show_product_viewers']) ) return;
        if ( empty($opts['show_live_viewers']) && empty($opts['fake_viewers_enable']) ) return;
        $product_id = get_queried_object_id();

        if ( !empty($opts['fake_viewers_enable']) ) {
            $count = 0;
            if (($opts['fake_viewers_mode'] ?? 'random') === 'random') {
                $min = intval($opts['fake_viewers_min'] ?? 4);
                $max = intval($opts['fake_viewers_max'] ?? 18);
                if($min > $max) $max = $min;
                $count = rand($min, $max);
            } else {
                $count = intval($opts['fake_viewers_fixed'] ?? 9);
            }
            $msg = $count === 1
                ? esc_html__('1 person is viewing this product right now', 'dynamic-social-proof-feed')
                : sprintf(esc_html__('%d people are viewing this product right now', 'dynamic-social-proof-feed'), $count);
            echo '<div id="dspf-product-viewers-bar" class="dspf-wc-product-viewers-bar" style="margin:14px 0 8px 0;">'.esc_html($msg).'</div>';
            echo '<script>window.DSPF_ProductViewersFake = true;</script>';
            return;
        }

        if ( !empty($opts['show_live_viewers']) ) {
            echo '<div id="dspf-product-viewers-bar" class="dspf-wc-product-viewers-bar" style="margin:14px 0 8px 0; display:none"></div>';
            echo '<script>window.DSPF_ProductViewersFake = false;</script>';
        }
    }

    public function log_woo_purchase( $order_id ) {
        $opts = get_option('dspf_settings', []);
        if ( empty($opts['enable_woo']) || empty($opts['show_purchase_popups']) ) return;
        if ( ! function_exists( 'wc_get_order' ) ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        $first_name = $order->get_billing_first_name();
        $city = $order->get_billing_city();
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $img_url = '';
            if ( $product_id && has_post_thumbnail( $product_id ) ) {
                $img = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'thumbnail' );
                if ($img && !empty($img[0])) $img_url = esc_url($img[0]);
            }
            $activity = [
                'type'      => 'purchase',
                'name'      => $first_name,
                'city'      => $city,
                'product'   => $item->get_name(),
                'product_img' => $img_url,
                'time'      => time(),
            ];
            $this->save_activity( $activity );
        }
    }

    public function log_edd_purchase( $payment_id ) {
        $opts = get_option('dspf_settings', []);
        if ( empty($opts['enable_edd']) || empty($opts['show_purchase_popups']) ) return;
        if ( ! function_exists( 'edd_get_payment' ) ) return;
        $payment = edd_get_payment( $payment_id );
        if ( ! $payment ) return;
        $meta = $payment->get_meta();
        $name = $meta['user_info']['first_name'] ?? '';
        $city = $meta['user_info']['address']['city'] ?? '';
        foreach ( $payment->cart_details as $item ) {
            $product_id = $item['id'] ?? 0;
            $img_url = '';
            if ( $product_id ) {
                $img_id = get_post_thumbnail_id( $product_id );
                if ($img_id) {
                    $img = wp_get_attachment_image_src( $img_id, 'thumbnail' );
                    if ($img && !empty($img[0])) $img_url = esc_url($img[0]);
                }
            }
            $activity = [
                'type'      => 'purchase',
                'name'      => $name,
                'city'      => $city,
                'product'   => $item['name'],
                'product_img' => $img_url,
                'time'      => time(),
            ];
            $this->save_activity( $activity );
        }
    }

    private function save_activity( array $activity ) {
        $cache = get_transient( 'dspf_activity_feed' );
        if ( ! is_array( $cache ) ) {
            $cache = [];
        }
        array_unshift( $cache, $activity );
        $cache = array_slice( $cache, 0, 30 );
        set_transient( 'dspf_activity_feed', $cache, HOUR_IN_SECONDS * 12 );
    }

    public function ajax_fetch_feed() {
        check_ajax_referer( 'dspf_feed_nonce', 'nonce' );
        $opts = get_option('dspf_settings', []);
        $output = [];
        $count = !empty($opts['popup_count']) ? (int)$opts['popup_count'] : 10;

        // Handle fake purchase popups (overrides real if enabled)
        if (!empty($opts['show_fake_purchase_popups'])) {
            $fake_entries = explode('|', $opts['fake_purchase_entries'] ?? '');
            foreach ($fake_entries as $entry) {
                $row = array_map('trim', explode(',', $entry));
                if (count($row) === 3) {
                    $output[] = $this->get_message([
                        'type' => 'purchase',
                        'name' => $row[0],
                        'city' => $row[1],
                        'product' => $row[2],
                        'product_img' => '',
                        'time' => time(),
                        'fake' => true,
                    ]);
                    if (count($output) >= $count) break;
                }
            }
            // If not enough fakes, allow repeat
            while(count($output) < $count && count($output) > 0) {
                $output[] = $output[array_rand($output)];
            }
            wp_send_json_success($output);
        }

        // If not fake, build from live feed
        $feed = get_transient('dspf_activity_feed');
        if (!is_array($feed)) $feed = [];
        foreach ($feed as $activity) {
            // Only add real purchases if allowed
            if ($activity['type'] === 'purchase' && empty($opts['show_purchase_popups'])) continue;
            $output[] = $this->get_message($activity);
            if (count($output) >= $count) break;
        }
        wp_send_json_success($output);
    }

    private function get_message($a) {
        $opts = get_option('dspf_settings', []);
        if (!is_array($a) || empty($a['type'])) return '';
        if ($a['type'] === 'manual' && !empty($a['custom'])) {
            return esc_html($a['custom']);
        }
        if ($a['type'] === 'purchase') {
            // Only show if either real or fake purchase mode enabled
            if (empty($opts['show_purchase_popups']) && empty($opts['show_fake_purchase_popups'])) return '';
            $tpl = $opts['template_purchase'] ?? '{name} from {city} purchased {product}';
            $img = !empty($a['product_img']) ? '<img class="dspf-avatar" src="'.esc_url($a['product_img']).'" alt="'.esc_attr($a['product'] ?? '').'">' : '';
            $msg = strtr( $tpl, [
                '{name}'    => esc_html( $a['name'] ?? __( 'Someone', 'dynamic-social-proof-feed' ) ),
                '{city}'    => esc_html( $a['city'] ?? __( 'somewhere', 'dynamic-social-proof-feed' ) ),
                '{product}' => esc_html( $a['product'] ?? '' )
            ]);
            return '<span class="dspf-row">'.$img.'<span class="dspf-msg">'.esc_html($msg).'</span></span>';
        }
        if ($a['type'] === 'page_view') {
            $tpl = $opts['template_page_view'] ?? '{count} people viewed "{page}" recently';
            $count = $this->get_page_view_count( $a['post_id'], 10 );
            $msg = strtr( $tpl, [
                '{count}' => esc_html($count),
                '{page}'  => esc_html($a['title'] ?? '')
            ]);
            return '<span class="dspf-msg">'.esc_html($msg).'</span>';
        }
        return '';
    }

    private function get_page_view_count($post_id, $max = 10) {
        $feed = get_transient('dspf_activity_feed');
        $count = 0;
        if (is_array($feed)) {
            foreach ($feed as $a) {
                if ($a['type'] === 'page_view' && isset($a['post_id']) && intval($a['post_id']) === intval($post_id)) {
                    $count++;
                }
            }
        }
        return min($count, $max);
    }

    private function get_session_id() {
        if (!session_id()) {
            if (headers_sent() === false) {
                session_start();
            }
        }
        if (isset($_COOKIE['dspf_sid'])) {
            return sanitize_text_field($_COOKIE['dspf_sid']);
        }
        $sid = md5(uniqid((string)mt_rand(), true) . microtime());
        setcookie('dspf_sid', $sid, time()+3600, '/');
        return $sid;
    }
}

new DSPF_Main();

endif;
