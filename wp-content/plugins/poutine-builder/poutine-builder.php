<?php
/**
 * Plugin Name:       Poutine Builder
 * Description:       Configurateur de poutine pour WooCommerce, retrait en magasin (click & collect) et envoi des commandes vers la caisse.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Text Domain:       poutine-builder
 */

defined( 'ABSPATH' ) || exit;

define( 'PB_VERSION', '0.1.0' );
define( 'PB_FILE', __FILE__ );
define( 'PB_DIR', plugin_dir_path( __FILE__ ) );
define( 'PB_URL', plugin_dir_url( __FILE__ ) );

foreach ( array(
	'class-pb-catalog.php',
	'class-pb-stores.php',
	'class-pb-cart.php',
	'class-pb-pickup.php',
	'class-pb-frontend.php',
	'class-pb-settings.php',
	'pos/interface-pb-pos-adapter.php',
	'pos/class-pb-pos-webhook.php',
	'pos/class-pb-pos-dispatcher.php',
	'class-pb-install.php',
) as $pb_file ) {
	require_once PB_DIR . 'includes/' . $pb_file;
}

register_activation_hook( __FILE__, array( 'PB_Install', 'activate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PB_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>Poutine Builder nécessite WooCommerce.</p></div>';
				}
			);
			return;
		}
		PB_Catalog::init();
		PB_Cart::init();
		PB_Pickup::init();
		PB_Frontend::init();
		PB_Settings::init();
		PB_POS_Dispatcher::init();
	}
);
