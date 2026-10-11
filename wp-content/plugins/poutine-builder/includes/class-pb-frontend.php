<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shortcode [poutine_builder] : le configurateur.
 */
class PB_Frontend {

	public static function init(): void {
		add_shortcode( 'poutine_builder', array( __CLASS__, 'render' ) );
	}

	public static function render(): string {
		wp_enqueue_style( 'pb-builder', PB_URL . 'assets/builder.css', array(), PB_VERSION );
		wp_enqueue_script( 'pb-builder', PB_URL . 'assets/builder.js', array(), PB_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		$config = array(
			'steps'       => array_values( PB_Catalog::steps() ),
			'ingredients' => PB_Catalog::by_step(),
			'currency'    => get_woocommerce_currency(),
			'locale'      => str_replace( '_', '-', get_locale() ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'pb_builder' ),
		);

		return sprintf(
			'<div id="pb-builder" class="pb" data-config="%s"><noscript>Active JavaScript pour composer ta poutine.</noscript></div>',
			esc_attr( wp_json_encode( $config ) )
		);
	}
}
