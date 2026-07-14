<?php
/**
 * Plugin Name:       Folio – 3D Flipbook Catalog
 * Plugin URI:        https://bitakpaint.com/
 * Description:        نمایش کاتالوگ و دفترچه‌ی محصولات به‌صورت مجله‌ی سه‌بعدی واقعی؛ ورق‌زدن با کشیدنِ موس/انگشت روی کاغذ، پیچ‌خوردن گوشه و سایه‌ی نرم. سریع، بدون هنگ، چندزبانه (WPML). با شورت‌کد داخل هر برگه یا نوشته.
 * Version:           2.5.0
 * Author:            Yalda Jahanshahi
 * Text Domain:       folio-flipbook
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FC_VERSION', '2.5.0' );
define( 'FC_FILE', __FILE__ );
define( 'FC_DIR', plugin_dir_path( __FILE__ ) );
define( 'FC_URL', plugin_dir_url( __FILE__ ) );
define( 'FC_CPT', 'flip_catalog' );

require_once FC_DIR . 'includes/class-fc-cpt.php';
require_once FC_DIR . 'includes/class-fc-admin.php';
require_once FC_DIR . 'includes/class-fc-shortcode.php';

/**
 * راه‌اندازی افزونه.
 */
function fc_bootstrap() {
	FC_CPT_Registrar::init();
	FC_Admin::init();
	FC_Shortcode::init();
}
add_action( 'plugins_loaded', 'fc_bootstrap' );

/**
 * فعال‌سازی: ثبت CPT و flush کردن rewrite rules.
 */
function fc_activate() {
	FC_CPT_Registrar::register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'fc_activate' );

/**
 * غیرفعال‌سازی.
 */
function fc_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'fc_deactivate' );
