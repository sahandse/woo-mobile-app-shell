<?php
/**
 * Plugin Name: پوسته اپ موبایل ووکامرس
 * Plugin URI: https://github.com/sahandse/woo-mobile-app-shell
 * Description: تبدیل ظاهر موبایل فروشگاه ووکامرس به تجربه‌ای شبیه اپلیکیشن با نوار پایین، Splash و ساختار PWA.
 * Version: 1.1.0
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: woo-mobile-app-shell
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

final class WMAS_Plugin {
    const VERSION = '1.1.0';
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
        add_action('wp_footer', [$this, 'splash']);
        add_action('wp_footer', [$this, 'ajax_search_ui']);
        add_action('wp_ajax_wmas_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_nopriv_wmas_search_products', [$this, 'ajax_search_products']);
        add_action('init', [$this, 'pwa_routes']);
        add_action('template_redirect', [$this, 'serve_pwa_files']);
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
        if (function_exists('s_store_register_submenu')) {
            s_store_register_submenu('woo-mobile-app-shell', 'اپ موبایل ووکامرس', [$this, 'settings_page'], 'manage_woocommerce', 'اپ موبایل ووکامرس');
            return;
        }
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
                        <h2>PWA و جستجوی زنده</h2>
                        <p>Manifest و Service Worker واقعی فعال است و جستجوی Ajax محصولات از نوار موبایل انجام می‌شود. OTP و Push به Providerهای خارجی وابسته‌اند و جداگانه پیکربندی می‌شوند.</p>
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
        if('yes'===$s['enable_pwa']){
            echo '<link rel="manifest" href="' . esc_url(home_url('/wmas-manifest.webmanifest')) . '">';
            echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",()=>navigator.serviceWorker.register("' . esc_url(home_url('/wmas-sw.js')) . '").catch(()=>{}));}</script>';
        }
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
    }    public function pwa_routes() {
        add_rewrite_rule('^wmas-manifest\.webmanifest$','index.php?wmas_pwa=manifest','top');
        add_rewrite_rule('^wmas-sw\.js$','index.php?wmas_pwa=sw','top');
        add_rewrite_tag('%wmas_pwa%','([^&]+)');
    }

    public function serve_pwa_files() {
        $type=get_query_var('wmas_pwa');
        if(!$type) return;
        $s=$this->settings();
        if('yes'!==$s['enable_pwa']) { status_header(404); exit; }

        if('manifest'===$type){
            header('Content-Type: application/manifest+json; charset=utf-8');
            echo wp_json_encode([
                'name'=>get_bloginfo('name'),
                'short_name'=>get_bloginfo('name'),
                'start_url'=>home_url('/'),
                'display'=>'standalone',
                'background_color'=>'#ffffff',
                'theme_color'=>$s['accent'],
                'icons'=>[]
            ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        if('sw'===$type){
            header('Content-Type: application/javascript; charset=utf-8');
            echo "const CACHE='wmas-v1';self.addEventListener('install',e=>{self.skipWaiting();});self.addEventListener('activate',e=>{e.waitUntil(self.clients.claim());});self.addEventListener('fetch',e=>{if(e.request.method!=='GET')return;e.respondWith(fetch(e.request).catch(()=>caches.match(e.request)));});";
            exit;
        }
    }

    public function splash() {
        $s=$this->settings();
        if(!wp_is_mobile()||'yes'!==$s['enabled']||'yes'!==$s['show_splash']) return;
        echo '<div id="wmas-splash" style="position:fixed;inset:0;z-index:999999;background:#fff;display:grid;place-items:center"><div style="text-align:center"><strong style="font-size:22px">'.esc_html(get_bloginfo('name')).'</strong><div style="margin-top:12px;color:'.esc_attr($s['accent']).'">● ● ●</div></div></div>';
        echo '<script>window.addEventListener("load",()=>{const s=document.getElementById("wmas-splash");if(s)setTimeout(()=>{s.style.opacity="0";s.style.transition="opacity .25s";setTimeout(()=>s.remove(),260)},350)});</script>';
    }

    public function ajax_search_ui() {
        $s=$this->settings();
        if(!wp_is_mobile()||'yes'!==$s['enabled']) return;
        echo '<div class="wmas-search-sheet" hidden><div class="wmas-search-box"><input type="search" placeholder="جستجوی محصول…"><button type="button" class="wmas-search-close">×</button><div class="wmas-search-results"></div></div></div>';
        echo '<button type="button" class="wmas-search-fab" aria-label="جستجو">⌕</button>';
        echo '<script>(function(){const sheet=document.querySelector(".wmas-search-sheet"),open=document.querySelector(".wmas-search-fab"),close=document.querySelector(".wmas-search-close"),input=sheet?.querySelector("input"),results=sheet?.querySelector(".wmas-search-results");if(!sheet||!open)return;open.onclick=()=>{sheet.hidden=false;input.focus()};close.onclick=()=>sheet.hidden=true;let t;input.addEventListener("input",()=>{clearTimeout(t);t=setTimeout(async()=>{const q=input.value.trim();if(q.length<2){results.innerHTML="";return;}results.innerHTML="در حال جستجو…";const b=new URLSearchParams({action:"wmas_search_products",q});const r=await fetch("'.esc_url(admin_url('admin-ajax.php')).'",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:b});const j=await r.json();results.innerHTML=j.success?j.data.html:"نتیجه‌ای پیدا نشد";},250)});})();</script>';
    }

    public function ajax_search_products() {
        $q=sanitize_text_field(wp_unslash($_POST['q']??''));
        if(mb_strlen($q)<2) wp_send_json_success(['html'=>'']);
        $query=new WP_Query(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>8,'s'=>$q,'fields'=>'ids']);
        ob_start();
        foreach($query->posts as $id){
            $p=wc_get_product($id); if(!$p) continue;
            echo '<a href="'.esc_url(get_permalink($id)).'" style="display:flex;gap:10px;padding:10px;text-decoration:none">';
            echo $p->get_image([48,48]);
            echo '<span><strong>'.esc_html($p->get_name()).'</strong><small style="display:block">'.wp_kses_post($p->get_price_html()).'</small></span></a>';
        }
        wp_send_json_success(['html'=>ob_get_clean()]);
    }


}

new WMAS_Plugin();
