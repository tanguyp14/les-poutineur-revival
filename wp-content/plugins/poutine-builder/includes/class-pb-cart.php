<?php
defined( 'ABSPATH' ) || exit;

/**
 * Panier : ajout d’une poutine composée, prix recalculé côté serveur, méta de commande.
 */
class PB_Cart {

	public static function init(): void {
		add_action( 'wp_ajax_pb_add_to_cart', array( __CLASS__, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_pb_add_to_cart', array( __CLASS__, 'ajax_add_to_cart' ) );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );
	}

	public static function product_id(): int {
		return (int) get_option( 'pb_product_id' );
	}

	public static function ajax_add_to_cart(): void {
		check_ajax_referer( 'pb_builder', 'nonce' );

		$selection = json_decode( wp_unslash( $_POST['selection'] ?? '' ), true );
		$qty       = max( 1, min( 20, absint( $_POST['qty'] ?? 1 ) ) );
		$resolved  = PB_Catalog::resolve( $selection );
		if ( is_wp_error( $resolved ) ) {
			wp_send_json_error( array( 'message' => $resolved->get_error_message() ), 422 );
		}
		if ( null === WC()->cart ) {
			wc_load_cart();
		}
		$added = WC()->cart->add_to_cart(
			self::product_id(),
			$qty,
			0,
			array(),
			array( 'pb_composition' => $resolved )
		);
		if ( ! $added ) {
			wp_send_json_error( array( 'message' => 'Impossible d’ajouter au panier.' ), 400 );
		}
		wp_send_json_success(
			array(
				'cartUrl'     => wc_get_cart_url(),
				'checkoutUrl' => wc_get_checkout_url(),
				'count'       => WC()->cart->get_cart_contents_count(),
			)
		);
	}

	public static function apply_prices( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['pb_composition']['total'] ) ) {
				$item['data']->set_price( (float) $item['pb_composition']['total'] );
			}
		}
	}

	/** Regroupe les lignes par étape : [ 'Taille' => ['Moyenne'], ... ]. */
	public static function group_lines( array $lines ): array {
		$grouped = array();
		foreach ( $lines as $l ) {
			$grouped[ $l['step_label'] ][] = $l['name'];
		}
		return $grouped;
	}

	public static function item_data( array $data, array $cart_item ): array {
		if ( empty( $cart_item['pb_composition']['lines'] ) ) {
			return $data;
		}
		foreach ( self::group_lines( $cart_item['pb_composition']['lines'] ) as $label => $names ) {
			$data[] = array( 'key' => $label, 'value' => implode( ', ', $names ) );
		}
		return $data;
	}

	/** Re-vérifie la composition (ingrédient passé en rupture entre-temps, prix modifié...). */
	public static function check_cart(): void {
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item['pb_composition']['selection'] ) ) {
				continue;
			}
			$again = PB_Catalog::resolve( $item['pb_composition']['selection'] );
			if ( is_wp_error( $again ) ) {
				WC()->cart->remove_cart_item( $key );
				wc_add_notice( 'Une poutine a été retirée de ton panier : ' . $again->get_error_message(), 'error' );
				continue;
			}
			WC()->cart->cart_contents[ $key ]['pb_composition'] = $again; // prix à jour.
		}
	}

	public static function order_item_meta( WC_Order_Item_Product $item, string $cart_key, array $values ): void {
		if ( empty( $values['pb_composition']['lines'] ) ) {
			return;
		}
		foreach ( self::group_lines( $values['pb_composition']['lines'] ) as $label => $names ) {
			$item->add_meta_data( $label, implode( ', ', $names ) );
		}
		$item->add_meta_data( '_pb_composition', wp_json_encode( $values['pb_composition']['lines'] ), true );
	}
}
