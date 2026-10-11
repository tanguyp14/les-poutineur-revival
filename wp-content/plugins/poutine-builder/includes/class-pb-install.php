<?php
defined( 'ABSPATH' ) || exit;

/**
 * Activation : produit caché « Poutine composée », carte de départ, pages Commander et Suivi.
 */
class PB_Install {

	public static function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		PB_Catalog::register_cpt();
		self::ensure_product();
		self::seed_ingredients();
		self::ensure_page( 'pb_builder_page_id', 'Commander', 'commander', '[poutine_builder]' );
		self::ensure_page( 'pb_staff_page_id', 'Suivi des commandes', 'suivi-commandes', '[poutine_staff]' );
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

	/** Crée une page avec un shortcode si elle n'existe pas encore. */
	private static function ensure_page( string $option, string $title, string $slug, string $shortcode ): void {
		if ( get_post_status( (int) get_option( $option ) ) ) {
			return;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( $option, $id );
		}
	}

	private static function seed_ingredients(): void {
		if ( get_posts( array( 'post_type' => PB_Catalog::CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
			return;
		}
		// [ nom, prix, accroche (extrait), badge ]. Carte du site ; fromages/toppings à 0 € = inclus (à ajuster).
		$seed = array(
			'poutine' => array(
				array( 'La Classique', 10, 'Basique Simple', '' ),
				array( 'La Classique Bacon', 12, 'On revoit les bases', '' ),
				array( 'Chili !', 13, 'Aie Caramba !', '' ),
				array( 'La Cochonne', 13, 'C’est le nom de la recette', '' ),
				array( 'La Saucisse', 12, 'Mais t’avais dit qu’on ferait des knakis :(', '' ),
				array( 'La Stephen Curry', 13, 'Not 1, not 2, but 3 🏀', '' ),
				array( 'La Végétarienne', 12, 'Viens pas woingner on a pensé à toi', 'Vegan' ),
				array( 'La Blanquette des Vikings', 13, 'Comment est votre Blanquette ?', '' ),
			),
			'cheese'  => array(
				array( 'Fromage en grains', 0, '', '' ),
				array( 'Camembert', 0, '', '' ),
				array( 'Raclette fondue', 0, '', '' ),
			),
			'topping' => array(
				array( 'Persil', 0, '', '' ),
				array( 'Coriandre', 0, '', '' ),
				array( 'Oignons frits', 0, '', '' ),
				array( 'Oignons rouges', 0, '', '' ),
				array( 'Cacahuètes', 0, '', '' ),
				array( 'Jalapeños', 0, '', '' ),
				array( 'Chips de carottes maison', 0, '', 'Topping du mois' ),
				array( 'Sans topping', 0, '', '' ),
			),
		);
		$order = 0;
		foreach ( $seed as $step => $rows ) {
			foreach ( $rows as [ $name, $price, $tagline, $badge ] ) {
				$id = wp_insert_post(
					array(
						'post_type'    => PB_Catalog::CPT,
						'post_status'  => 'publish',
						'post_title'   => $name,
						'post_excerpt' => $tagline,
						'menu_order'   => $order++,
					)
				);
				update_post_meta( $id, '_pb_step', $step );
				update_post_meta( $id, '_pb_price', $price );
				update_post_meta( $id, '_pb_badge', $badge );
				update_post_meta( $id, '_pb_available', 'yes' );
			}
		}
	}
}
