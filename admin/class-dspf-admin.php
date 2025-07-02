<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

if (!class_exists('DSPF_Admin')) :

final class DSPF_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu() {
        add_options_page(
            esc_html__('Social Proof Feed', 'dynamic-social-proof-feed'),
            esc_html__('Social Proof Feed', 'dynamic-social-proof-feed'),
            'manage_options',
            'dspf-settings',
            [$this, 'settings_page']
        );
    }

    public function register_settings() {
        register_setting('dspf_settings_group', 'dspf_settings', [$this, 'sanitize']);

        add_settings_section('dspf_section_main', __('General Settings', 'dynamic-social-proof-feed'), '__return_false', 'dspf-settings');

        // Product Viewers
        add_settings_field('show_product_viewers', __('Show Product Viewers Bar', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[show_product_viewers]" value="1" %s>',
                checked(!empty($opts['show_product_viewers']), 1, false)
            );
        }, 'dspf-settings', 'dspf_section_main');

        // Live viewers
        add_settings_field('show_live_viewers', __('Show Live Product Viewers', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[show_live_viewers]" value="1" %s>',
                checked(!empty($opts['show_live_viewers']), 1, false)
            );
            echo '<span style="color:#888;margin-left:8px;">'.__('Shows real number of people viewing product (not available if "Fake" enabled)', 'dynamic-social-proof-feed').'</span>';
        }, 'dspf-settings', 'dspf_section_main');

        // Fake viewers
        add_settings_field('fake_viewers_enable', __('Show Fake Product Viewers', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[fake_viewers_enable]" value="1" %s>',
                checked(!empty($opts['fake_viewers_enable']), 1, false)
            );
            echo '<span style="color:#888;margin-left:8px;">'.__('Overrides "Live" if both are enabled', 'dynamic-social-proof-feed').'</span>';
        }, 'dspf-settings', 'dspf_section_main');

        add_settings_field('fake_viewers_mode', __('Fake Viewers Mode', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            $mode = $opts['fake_viewers_mode'] ?? 'random';
            ?>
            <select name="dspf_settings[fake_viewers_mode]">
                <option value="random" <?php selected($mode, 'random'); ?>><?php _e('Random in Range', 'dynamic-social-proof-feed'); ?></option>
                <option value="fixed" <?php selected($mode, 'fixed'); ?>><?php _e('Fixed Number', 'dynamic-social-proof-feed'); ?></option>
            </select>
            <?php
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('fake_viewers_min', __('Fake Viewers Min', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            echo '<input type="number" min="1" max="100" name="dspf_settings[fake_viewers_min]" value="'.esc_attr($opts['fake_viewers_min'] ?? 4).'" class="small-text">';
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('fake_viewers_max', __('Fake Viewers Max', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            echo '<input type="number" min="1" max="500" name="dspf_settings[fake_viewers_max]" value="'.esc_attr($opts['fake_viewers_max'] ?? 18).'" class="small-text">';
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('fake_viewers_fixed', __('Fake Viewers Fixed Value', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            echo '<input type="number" min="1" max="1000" name="dspf_settings[fake_viewers_fixed]" value="'.esc_attr($opts['fake_viewers_fixed'] ?? 9).'" class="small-text">';
        }, 'dspf-settings', 'dspf_section_main');

        // Purchase popups
        add_settings_field('show_purchase_popups', __('Show Recent Purchase Popups (Real)', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[show_purchase_popups]" value="1" %s>',
                checked(!empty($opts['show_purchase_popups']), 1, false)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('show_fake_purchase_popups', __('Show Fake Purchase Popups', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[show_fake_purchase_popups]" value="1" %s>',
                checked(!empty($opts['show_fake_purchase_popups']), 1, false)
            );
            echo '<span style="color:#888;margin-left:8px;">' . __('Overrides "Real" if both enabled', 'dynamic-social-proof-feed') . '</span>';
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('fake_purchase_entries', __('Fake Purchase Data', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            $val = $opts['fake_purchase_entries'] ?? '';
            echo '<textarea name="dspf_settings[fake_purchase_entries]" rows="5" cols="45" placeholder="Format: Name,City,Product|Name,City,Product ...">'.esc_textarea($val).'</textarea>';
            echo '<br><span style="color:#888;">'.__('Separate multiple entries with |, e.g. John,London,Blue Shirt | Maria,Berlin,Red Dress', 'dynamic-social-proof-feed').'</span>';
        }, 'dspf-settings', 'dspf_section_main');

        // Woo, EDD, Views
        add_settings_field('enable_woo', __('Enable WooCommerce', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[enable_woo]" value="1" %s>',
                checked(!empty($opts['enable_woo']), 1, false)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('enable_edd', __('Enable Easy Digital Downloads', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[enable_edd]" value="1" %s>',
                checked(!empty($opts['enable_edd']), 1, false)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('enable_views', __('Enable Page Views', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[enable_views]" value="1" %s>',
                checked(!empty($opts['enable_views']), 1, false)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('hide_logged_in', __('Hide for logged-in users', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[hide_logged_in]" value="1" %s>',
                checked(!empty($opts['hide_logged_in']), 1, false)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('popup_position', __('Popup Position', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            $pos = $opts['popup_position'] ?? 'bottom_left';
            ?>
            <select name="dspf_settings[popup_position]">
                <option value="bottom_left" <?php selected($pos, 'bottom_left'); ?>><?php _e('Bottom Left', 'dynamic-social-proof-feed'); ?></option>
                <option value="bottom_right" <?php selected($pos, 'bottom_right'); ?>><?php _e('Bottom Right', 'dynamic-social-proof-feed'); ?></option>
                <option value="top_left" <?php selected($pos, 'top_left'); ?>><?php _e('Top Left', 'dynamic-social-proof-feed'); ?></option>
                <option value="top_right" <?php selected($pos, 'top_right'); ?>><?php _e('Top Right', 'dynamic-social-proof-feed'); ?></option>
            </select>
            <?php
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('popup_animation', __('Popup Animation', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            $ani = $opts['popup_animation'] ?? 'slide';
            ?>
            <select name="dspf_settings[popup_animation]">
                <option value="slide" <?php selected($ani, 'slide'); ?>><?php _e('Slide', 'dynamic-social-proof-feed'); ?></option>
                <option value="fade" <?php selected($ani, 'fade'); ?>><?php _e('Fade', 'dynamic-social-proof-feed'); ?></option>
                <option value="bounce" <?php selected($ani, 'bounce'); ?>><?php _e('Bounce', 'dynamic-social-proof-feed'); ?></option>
            </select>
            <?php
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('popup_count', __('Max Popups in Rotation', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="number" min="1" max="30" name="dspf_settings[popup_count]" value="%s" class="small-text">',
                esc_attr($opts['popup_count'] ?? 10)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('popup_interval', __('Popup Display Interval (ms)', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="number" min="2000" max="60000" step="100" name="dspf_settings[popup_interval]" value="%s" class="small-text">',
                esc_attr($opts['popup_interval'] ?? 6000)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('popup_hide_delay', __('Popup Hide Delay (ms)', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="number" min="1000" max="30000" step="100" name="dspf_settings[popup_hide_delay]" value="%s" class="small-text">',
                esc_attr($opts['popup_hide_delay'] ?? 4000)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('show_on_mobile', __('Show on Mobile', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[show_on_mobile]" value="1" %s>',
                checked(!empty($opts['show_on_mobile']), 1, false)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('show_on_desktop', __('Show on Desktop', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="checkbox" name="dspf_settings[show_on_desktop]" value="1" %s>',
                checked(!empty($opts['show_on_desktop']), 1, false)
            );
        }, 'dspf-settings', 'dspf_section_main');
        add_settings_field('hide_on_ids', __('Hide on Page/Post IDs (comma-separated)', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            printf('<input type="text" name="dspf_settings[hide_on_ids]" value="%s" class="regular-text">',
                esc_attr($opts['hide_on_ids'] ?? '')
            );
        }, 'dspf-settings', 'dspf_section_main');

        // Templates
        add_settings_section('dspf_section_templates', __('Message Templates', 'dynamic-social-proof-feed'), function(){
            echo '<p>'.__('Use tags: {name}, {city}, {product}, {page}, {count}', 'dynamic-social-proof-feed').'</p>';
        }, 'dspf-settings');
        add_settings_field('template_purchase', __('Purchase Message', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            $tpl = $opts['template_purchase'] ?? '{name} from {city} purchased {product}';
            echo '<input type="text" name="dspf_settings[template_purchase]" value="'.esc_attr($tpl).'" class="large-text">';
        }, 'dspf-settings', 'dspf_section_templates');
        add_settings_field('template_page_view', __('Page View Message', 'dynamic-social-proof-feed'), function(){
            $opts = get_option('dspf_settings', []);
            $tpl = $opts['template_page_view'] ?? '{count} people viewed "{page}" recently';
            echo '<input type="text" name="dspf_settings[template_page_view]" value="'.esc_attr($tpl).'" class="large-text">';
        }, 'dspf-settings', 'dspf_section_templates');

        // Manual (Simulate) Event
        add_settings_section('dspf_section_simulate', __('Simulate Events (for Testing)', 'dynamic-social-proof-feed'), '__return_false', 'dspf-settings');
        add_settings_field('manual_event', __('Add Event', 'dynamic-social-proof-feed'), function(){
            echo '<input type="text" name="dspf_manual_event" value="" placeholder="{name} from {city} purchased {product}" class="large-text">';
        }, 'dspf-settings', 'dspf_section_simulate');
    }

    public function sanitize($input) {
        $out = [];
        $out['show_product_viewers'] = !empty($input['show_product_viewers']) ? 1 : 0;
        $out['show_live_viewers'] = !empty($input['show_live_viewers']) ? 1 : 0;
        $out['fake_viewers_enable'] = !empty($input['fake_viewers_enable']) ? 1 : 0;
        $out['fake_viewers_mode'] = in_array($input['fake_viewers_mode'] ?? '', ['random','fixed']) ? $input['fake_viewers_mode'] : 'random';
        $out['fake_viewers_min'] = min(max(1, absint($input['fake_viewers_min'] ?? 4)), 500);
        $out['fake_viewers_max'] = min(max($out['fake_viewers_min'], absint($input['fake_viewers_max'] ?? 18)), 1000);
        $out['fake_viewers_fixed'] = min(max(1, absint($input['fake_viewers_fixed'] ?? 9)), 1000);
        $out['show_purchase_popups'] = !empty($input['show_purchase_popups']) ? 1 : 0;
        $out['show_fake_purchase_popups'] = !empty($input['show_fake_purchase_popups']) ? 1 : 0;
        $out['fake_purchase_entries'] = isset($input['fake_purchase_entries']) ? sanitize_textarea_field($input['fake_purchase_entries']) : '';
        // Existing
        $out['enable_woo'] = !empty($input['enable_woo']) ? 1 : 0;
        $out['enable_edd'] = !empty($input['enable_edd']) ? 1 : 0;
        $out['enable_views'] = !empty($input['enable_views']) ? 1 : 0;
        $out['hide_logged_in'] = !empty($input['hide_logged_in']) ? 1 : 0;
        $out['popup_position'] = in_array($input['popup_position'] ?? '', ['bottom_left','bottom_right','top_left','top_right']) ? $input['popup_position'] : 'bottom_left';
        $out['popup_animation'] = in_array($input['popup_animation'] ?? '', ['slide','fade','bounce']) ? $input['popup_animation'] : 'slide';
        $out['popup_count'] = min(max(1, absint($input['popup_count'] ?? 10)), 30);
        $out['popup_interval'] = min(max(2000, absint($input['popup_interval'] ?? 6000)), 60000);
        $out['popup_hide_delay'] = min(max(1000, absint($input['popup_hide_delay'] ?? 4000)), 30000);
        $out['show_on_mobile'] = !empty($input['show_on_mobile']) ? 1 : 0;
        $out['show_on_desktop'] = !empty($input['show_on_desktop']) ? 1 : 0;
        $out['hide_on_ids'] = sanitize_text_field($input['hide_on_ids'] ?? '');
        $out['template_purchase'] = sanitize_text_field($input['template_purchase'] ?? '{name} from {city} purchased {product}');
        $out['template_page_view'] = sanitize_text_field($input['template_page_view'] ?? '{count} people viewed "{page}" recently');
        if (!empty($_POST['dspf_manual_event'])) {
            $event = sanitize_text_field($_POST['dspf_manual_event']);
            if ($event) {
                $cache = get_transient('dspf_activity_feed');
                if (!is_array($cache)) $cache = [];
                array_unshift($cache, ['type'=>'manual','custom'=>$event,'time'=>time()]);
                $cache = array_slice($cache, 0, 30);
                set_transient('dspf_activity_feed', $cache, HOUR_IN_SECONDS * 12);
            }
        }
        return $out;
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Dynamic Social Proof Feed', 'dynamic-social-proof-feed'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('dspf_settings_group');
                do_settings_sections('dspf-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

endif;
