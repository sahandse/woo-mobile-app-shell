<?php
/**
 * Plugin Name: پوسته اپ موبایل ووکامرس
 * Plugin URI: https://github.com/sahandse/woo-mobile-app-shell
 * Description: تبدیل ظاهر موبایل فروشگاه ووکامرس به تجربه‌ای شبیه اپلیکیشن با نوار پایین، Splash و ساختار PWA.
 * Version: 1.0.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: woo-mobile-app-shell
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

final class WMAS_Plugin {
    const VERSION = '1.0.0';
    const OPTION  = 'wmas_settings';

    public function __construct() {
        add_action('before_woocommerce_init', [$this, 'declare_hpos']);
        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function declare_hpos() {
        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }

    public function boot() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_notice']);
            return;
        }

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('wp_footer', [$this, 'bottom_nav']);
        add_action('wp_head', [$this, 'mobile_meta']);
    }

    public function woocommerce_notice() {
        echo '<div class="notice notice-error"><p>پوسته اپ موبایل برای اجرا به WooCommerce نیاز دارد.</p></div>';
    }

    public function defaults() {
        return [
            'enabled' => 'yes',
            'theme' => 'light',
            'accent' => '#111827',
            'show_bottom_nav' => 'yes',
            'show_splash' => 'yes',
            'enable_pwa' => 'yes',
            'home_label' => 'خانه',
            'shop_label' => 'فروشگاه',
            'cart_label' => 'سبد',
            'account_label' => 'حساب',
        ];
    }

    public function settings() {
        return wp_parse_args((array)get_option(self::OPTION, []), $this->defaults());
    }

    public function register_settings() {
        register_setting('wmas_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in) {
        $d = $this->defaults();

        return [
            'enabled' => !empty($in['enabled']) ? 'yes' : 'no',
            'theme' => in_array($in['theme'] ?? '', ['light','dark','glass'], true)
                ? $in['theme']
                : $d['theme'],
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
            'show_bottom_nav' => !empty($in['show_bottom_nav']) ? 'yes' : 'no',
            'show_splash' => !empty($in['show_splash']) ? 'yes' : 'no',
            'enable_pwa' => !empty($in['enable_pwa']) ? 'yes' : 'no',
            'home_label' => sanitize_text_field($in['home_label'] ?? $d['home_label']),
            'shop_label' => sanitize_text_field($in['shop_label'] ?? $d['shop_label']),
            'cart_label' => sanitize_text_field($in['cart_label'] ?? $d['cart_label']),
            'account_label' => sanitize_text_field($in['account_label'] ?? $d['account_label']),
        ];
    }

    public function admin_menu() {
        add_submenu_page(
            'woocommerce',
            'پوسته اپ موبایل',
            'اپ موبایل',
            'manage_woocommerce',
            'woo-mobile-app-shell',
            [$this, 'settings_page']
        );
    }

    public function admin_assets($hook) {
        if (false === strpos($hook, 'woo-mobile-app-shell')) return;
        wp_enqueue_style('wmas-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
    }

    public function frontend_assets() {
        if (wp_is_mobile() && 'yes' === $this->settings()['enabled']) {
            wp_enqueue_style('wmas-front', plugin_dir_url(__FILE__) . 'assets/frontend.css', [], self::VERSION);
        }
    }

    public function settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $s = $this->settings();
        ?>
        <div class="wrap wmas-admin">
            <div class="wmas-hero">
                <div>
                    <h1>پوسته اپ موبایل ووکامرس</h1>
                    <p>مدیریت تجربه موبایل فروشگاه با ظاهر شبیه اپلیکیشن.</p>
                </div>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('wmas_group'); ?>
                <div class="wmas-grid">
                    <section class="wmas-card">
                        <h2>تنظیمات عمومی</h2>
                        <label class="wmas-switch">
                            <span>فعال بودن</span>
                            <input type="checkbox" name="<?php echo self::OPTION; ?>[enabled]" value="1" <?php checked($s['enabled'],'yes'); ?>>
                        </label>
                        <label>تم
                            <select name="<?php echo self::OPTION; ?>[theme]">
                                <option value="light" <?php selected($s['theme'],'light'); ?>>روشن</option>
                                <option value="dark" <?php selected($s['theme'],'dark'); ?>>تیره</option>
                                <option value="glass" <?php selected($s['theme'],'glass'); ?>>شیشه‌ای</option>
                            </select>
                        </label>
                        <label>رنگ اصلی
                            <input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>">
                        </label>
                    </section>

                    <section class="wmas-card">
                        <h2>قابلیت‌ها</h2>
                        <label class="wmas-switch"><span>Bottom Navigation</span><input type="checkbox" name="<?php echo self::OPTION; ?>[show_bottom_nav]" value="1" <?php checked($s['show_bottom_nav'],'yes'); ?>></label>
                        <label class="wmas-switch"><span>Splash Screen</span><input type="checkbox" name="<?php echo self::OPTION; ?>[show_splash]" value="1" <?php checked($s['show_splash'],'yes'); ?>></label>
                        <label class="wmas-switch"><span>PWA</span><input type="checkbox" name="<?php echo self::OPTION; ?>[enable_pwa]" value="1" <?php checked($s['enable_pwa'],'yes'); ?>></label>
                    </section>

                    <section class="wmas-card">
                        <h2>برچسب‌های منوی پایین</h2>
                        <label>خانه
                            <input type="text" name="<?php echo self::OPTION; ?>[home_label]" value="<?php echo esc_attr($s['home_label']); ?>">
                        </label>
                        <label>فروشگاه
                            <input type="text" name="<?php echo self::OPTION; ?>[shop_label]" value="<?php echo esc_attr($s['shop_label']); ?>">
                        </label>
                        <label>سبد
                            <input type="text" name="<?php echo self::OPTION; ?>[cart_label]" value="<?php echo esc_attr($s['cart_label']); ?>">
                        </label>
                        <label>حساب
                            <input type="text" name="<?php echo self::OPTION; ?>[account_label]" value="<?php echo esc_attr($s['account_label']); ?>">
                        </label>
                    </section>

                    <section class="wmas-card">
                        <h2>وضعیت توسعه</h2>
                        <p>هسته پوسته موبایل و Bottom Navigation آماده است. Home Builder، جستجوی Ajax، Bottom Sheet فیلترها، OTP و Push Notification در نسخه‌های بعدی همین Repo تکمیل می‌شود.</p>
                    </section>
                </div>

                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    public function mobile_meta() {
        $s = $this->settings();
        if (!wp_is_mobile() || 'yes' !== $s['enabled']) return;

        echo '<meta name="theme-color" content="' . esc_attr($s['accent']) . '">';
        echo '<meta name="mobile-web-app-capable" content="yes">';
    }

    public function bottom_nav() {
        $s = $this->settings();

        if (!wp_is_mobile() || 'yes' !== $s['enabled'] || 'yes' !== $s['show_bottom_nav']) {
            return;
        }

        $home = home_url('/');
        $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
        $cart = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/');
        $account = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/');

        echo '<nav class="wmas-bottom-nav" style="--wmas-accent:' . esc_attr($s['accent']) . '">';
        echo '<a href="' . esc_url($home) . '"><span>⌂</span><small>' . esc_html($s['home_label']) . '</small></a>';
        echo '<a href="' . esc_url($shop) . '"><span>▦</span><small>' . esc_html($s['shop_label']) . '</small></a>';
        echo '<a href="' . esc_url($cart) . '"><span>🛒</span><small>' . esc_html($s['cart_label']) . '</small></a>';
        echo '<a href="' . esc_url($account) . '"><span>◉</span><small>' . esc_html($s['account_label']) . '</small></a>';
        echo '</nav>';
    }
}

new WMAS_Plugin();
