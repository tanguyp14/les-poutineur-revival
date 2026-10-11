<?php
defined( 'ABSPATH' ) || exit;

/**
 * Activation : produit caché « Poutine composée » + ingrédients d’exemple.
 */
class PB_Install {

	public static function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		PB_Catalog::register_cpt();
		self::ensure_product();
		self::seed_ingredients();
	}

	private static function ensure_product(): void {
		$id = (int) get_option( 'pb_product_id' );
		if ( $id && 'product' === get_post_type( $id ) ) {
			return;
		}
		$product = new WC_Product_Simple();
		$product->set_name( 'Poutine composée' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_regular_price( '0' );
		$product->set_sold_individually( false );
		update_option( 'pb_product_id', $product->save() );
	}

	private static function seed_ingredients(): void {
		if ( get_posts( array( 'post_type' => PB_Catalog::CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
			return;
		}
		// Prix et noms d’exemple : à remplacer par la vraie carte.
		$seed = array(
			'size'     => array( array( 'Petite', 6.5, '🥡' ), array( 'Moyenne', 8.5, '🍟' ), array( 'Grosse', 10.5, '🍽️' ) ),
			'fries'    => array( array( 'Frites classiques', 0, '🍟' ), array( 'Frites maison', 0.5, '🥔' ), array( 'Patate douce', 1.5, '🍠' ) ),
			'cheese'   => array( array( 'Fromage en grains', 0, '🧀' ), array( 'Cheddar fondu', 0.5, '🧀' ), array( 'Mozzarella', 0.5, '🧀' ) ),
			'sauce'    => array( array( 'Sauce brune', 0, '🥣' ), array( 'Sauce poivre', 0.5, '🌶️' ), array( 'Sauce BBQ', 0.5, '🍖' ) ),
			'toppings' => array( array( 'Bacon', 2, '🥓' ), array( 'Poulet effiloché', 2.5, '🍗' ), array( 'Bœuf effiloché', 3, '🥩' ), array( 'Oignons frits', 1, '🧅' ), array( 'Champignons', 1, '🍄' ), array( 'Jalapeños', 0.5, '🌶️' ) ),
			'extras'   => array( array( 'Supplément fromage', 1, '🧀' ), array( 'Supplément sauce', 0.5, '🥣' ), array( 'Oeuf', 1, '🍳' ) ),
		);
		$order = 0;
		foreach ( $seed as $step => $rows ) {
			foreach ( $rows as [ $name, $price, $emoji ] ) {
				$id = wp_insert_post(
					array(
						'post_type'   => PB_Catalog::CPT,
						'post_status' => 'publish',
						'post_title'  => $name,
						'menu_order'  => $order++,
					)
				);
				update_post_meta( $id, '_pb_step', $step );
				update_post_meta( $id, '_pb_price', $price );
				update_post_meta( $id, '_pb_emoji', $emoji );
				update_post_meta( $id, '_pb_available', 'yes' );
			}
		}
	}
}
