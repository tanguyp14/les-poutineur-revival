<?php
defined( 'ABSPATH' ) || exit;

/**
 * Click & collect : choix de la boutique + créneau au checkout (checkout classique).
 */
class PB_Pickup {

	public static function init(): void {
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'render_fields' ) );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'simplify_fields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_pb_slots', array( __CLASS__, 'ajax_slots' ) );
		add_action( 'wp_ajax_nopriv_pb_slots', array( __CLASS__, 'ajax_slots' ) );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_info' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'render_info' ) );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'render_info' ) );
	}

	public static function assets(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		wp_enqueue_style( 'pb-pickup', PB_URL . 'assets/pickup.css', array(), PB_VERSION );
		wp_enqueue_script( 'pb-pickup', PB_URL . 'assets/pickup.js', array(), PB_VERSION, array( 'in_footer' => true ) );
		wp_localize_script(
			'pb-pickup',
			'PBPickup',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pb_slots' ),
			)
		);
	}

	public static function ajax_slots(): void {
		check_ajax_referer( 'pb_slots', 'nonce' );
		wp_send_json_success( PB_Stores::slots( sanitize_key( wp_unslash( $_GET['store'] ?? '' ) ) ) );
	}

	public static function render_fields(): void {
		$stores = PB_Stores::all( true );
		echo '<fieldset class="pb-pickup"><legend>Retrait en magasin</legend>';
		echo '<div class="pb-pickup__stores" role="radiogroup">';
		foreach ( $stores as $s ) {
			printf(
				'<label class="pb-pickup__store"><input type="radio" name="pb_store" value="%1$s" required><span class="pb-pickup__name">%2$s</span><span class="pb-pickup__addr">%3$s</span></label>',
				esc_attr( $s['id'] ),
				esc_html( $s['name'] ),
				esc_html( $s['address'] )
			);
		}
		echo '</div>';
		echo '<p class="form-row"><label for="pb_slot">Heure de retrait <abbr class="required">*</abbr></label>';
		echo '<select name="pb_slot" id="pb_slot" disabled><option value="">Choisis d’abord une boutique</option></select></p>';
		echo '</fieldset>';
	}

	/** Retrait uniquement : pas besoin d’adresse de facturation détaillée. */
	public static function simplify_fields( array $fields ): array {
		if ( ! apply_filters( 'pb_simplify_checkout', true ) ) {
			return $fields;
		}
		foreach ( array( 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_state' ) as $k ) {
			unset( $fields['billing'][ $k ] );
		}
		$fields['billing']['billing_phone']['required'] = true; // utile pour joindre le client au retrait.
		return $fields;
	}

	public static function validate( array $data, WP_Error $errors ): void {
		$store = sanitize_key( wp_unslash( $_POST['pb_store'] ?? '' ) );
		$slot  = absint( $_POST['pb_slot'] ?? 0 );
		if ( ! isset( PB_Stores::all( true )[ $store ] ) ) {
			$errors->add( 'pb_store', 'Choisis une boutique pour le retrait.' );
			return;
		}
		if ( ! PB_Stores::slot_is_valid( $store, $slot ) ) {
			$errors->add( 'pb_slot', 'Ce créneau n’est plus disponible, choisis-en un autre.' );
		}
	}

	public static function save( WC_Order $order, array $data ): void {
		$store = PB_Stores::get( sanitize_key( wp_unslash( $_POST['pb_store'] ?? '' ) ) );
		if ( ! $store ) {
			return;
		}
		$order->update_meta_data( '_pb_store_id', $store['id'] );
		$order->update_meta_data( '_pb_store_name', $store['name'] );
		$order->update_meta_data( '_pb_pickup_ts', absint( $_POST['pb_slot'] ?? 0 ) );
	}

	/** @param WC_Order|int $order */
	public static function render_info( $order ): void {
		$order = wc_get_order( $order );
		if ( ! $order || ! $order->get_meta( '_pb_store_id' ) ) {
			return;
		}
		$store = PB_Stores::get( (string) $order->get_meta( '_pb_store_id' ) );
		$when  = wp_date( 'l j F à H:i', (int) $order->get_meta( '_pb_pickup_ts' ) );
		printf(
			'<div class="pb-pickup-info" style="margin:1em 0"><h3>Retrait en magasin</h3><p><strong>%s</strong><br>%s<br>%s</p></div>',
			esc_html( $order->get_meta( '_pb_store_name' ) ),
			esc_html( $store['address'] ?? '' ),
			esc_html( ucfirst( $when ) )
		);
	}
}
