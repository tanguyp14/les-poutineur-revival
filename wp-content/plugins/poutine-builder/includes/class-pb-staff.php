<?php
defined( 'ABSPATH' ) || exit;

/**
 * Page de suivi des commandes pour le personnel : shortcode [poutine_staff].
 * Réservé aux utilisateurs ayant la capacité `pb_staff` (rôle « Équipe boutique », gérants, admins).
 */
class PB_Staff {

	const CAP = 'pb_staff';

	/** Statuts de préparation (méta _pb_prep_status). */
	const STATUSES = array( 'new', 'preparing', 'ready', 'collected' );

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'ensure_role' ) );
		add_shortcode( 'poutine_staff', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_pb_staff_orders', array( __CLASS__, 'ajax_orders' ) );
		add_action( 'wp_ajax_pb_staff_update', array( __CLASS__, 'ajax_update' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'noindex' ) );
	}

	public static function ensure_role(): void {
		if ( get_option( 'pb_staff_role_v' ) === PB_VERSION ) {
			return;
		}
		add_role( 'pb_staff', 'Équipe boutique', array( 'read' => true, self::CAP => true ) );
		foreach ( array( 'administrator', 'shop_manager' ) as $r ) {
			if ( $role = get_role( $r ) ) {
				$role->add_cap( self::CAP );
			}
		}
		update_option( 'pb_staff_role_v', PB_VERSION );
	}

	public static function noindex( array $robots ): array {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'poutine_staff' ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	public static function render(): string {
		nocache_headers();
		if ( ! is_user_logged_in() ) {
			return '<div class="pbs-login"><h2>Suivi des commandes</h2>' . wp_login_form( array( 'echo' => false, 'redirect' => get_permalink() ) ) . '</div>';
		}
		if ( ! current_user_can( self::CAP ) ) {
			return '<p>Ce compte n’a pas accès au suivi des commandes.</p>';
		}
		wp_enqueue_style( 'pb-staff', PB_URL . 'assets/staff.css', array(), PB_VERSION );
		wp_enqueue_script( 'pb-staff', PB_URL . 'assets/staff.js', array(), PB_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pb_staff' ),
			'poll'    => 15000,
			'stores'  => array_values( array_map( static fn( $s ) => array( 'id' => $s['id'], 'name' => $s['name'] ), PB_Stores::all( true ) ) ),
		);
		return sprintf( '<div id="pb-staff" class="pbs" data-config="%s"></div>', esc_attr( wp_json_encode( $config ) ) );
	}

	private static function guard(): void {
		check_ajax_referer( 'pb_staff', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
		}
		nocache_headers();
	}

	public static function ajax_orders(): void {
		self::guard();
		$store = sanitize_key( wp_unslash( $_GET['store'] ?? '' ) );
		if ( ! PB_Stores::get( $store ) ) {
			wp_send_json_error( array( 'message' => 'Boutique inconnue.' ), 400 );
		}
		$now      = time();
		$active   = wc_get_orders(
			array(
				'limit'      => 100,
				'status'     => array( 'wc-processing' ),
				'meta_query' => array( array( 'key' => '_pb_store_id', 'value' => $store ) ),
			)
		);
		$done     = ! empty( $_GET['done'] ) ? wc_get_orders(
			array(
				'limit'      => 50,
				'status'     => array( 'wc-completed' ),
				'meta_query' => array(
					array( 'key' => '_pb_store_id', 'value' => $store ),
					array( 'key' => '_pb_pickup_ts', 'value' => $now - 12 * HOUR_IN_SECONDS, 'compare' => '>=', 'type' => 'NUMERIC' ),
				),
			)
		) : array();

		$rows = array_map( array( __CLASS__, 'row' ), array_merge( $active, $done ) );
		usort( $rows, static fn( $a, $b ) => $a['pickup_ts'] <=> $b['pickup_ts'] );
		wp_send_json_success( array( 'now' => $now, 'orders' => $rows ) );
	}

	public static function row( WC_Order $order ): array {
		$ts    = (int) $order->get_meta( '_pb_pickup_ts' );
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$lines = json_decode( (string) $item->get_meta( '_pb_composition' ), true ) ?: array();
			$items[] = array(
				'name'  => $item->get_name(),
				'qty'   => (int) $item->get_quantity(),
				'parts' => PB_Cart::group_lines( $lines ),
			);
		}
		$status = 'completed' === $order->get_status() ? 'collected' : (string) $order->get_meta( '_pb_prep_status' );
		return array(
			'id'        => $order->get_id(),
			'number'    => $order->get_order_number(),
			'pickup_ts' => $ts,
			'pickup'    => wp_date( 'H:i', $ts ),
			'today'     => wp_date( 'Y-m-d', $ts ) === wp_date( 'Y-m-d' ),
			'day'       => wp_date( 'D j/m', $ts ),
			'customer'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'phone'     => $order->get_billing_phone(),
			'paid'      => $order->is_paid(),
			'total'     => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ),
			'note'      => $order->get_customer_note(),
			'items'     => $items,
			'status'    => in_array( $status, self::STATUSES, true ) ? $status : 'new',
			'pos'       => (string) $order->get_meta( '_pb_pos_status' ),
		);
	}

	public static function ajax_update(): void {
		self::guard();
		$order  = wc_get_order( absint( $_POST['order_id'] ?? 0 ) );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		if ( ! $order || ! $order->get_meta( '_pb_store_id' ) || ! in_array( $status, self::STATUSES, true ) ) {
			wp_send_json_error( array( 'message' => 'Commande ou statut invalide.' ), 400 );
		}
		$user = wp_get_current_user()->display_name;
		$order->update_meta_data( '_pb_prep_status', $status );

		if ( 'collected' === $status ) {
			$order->update_status( 'completed', sprintf( 'Retirée en magasin (%s).', $user ) );
		} else {
			if ( 'completed' === $order->get_status() ) { // annulation d'un « retirée ».
				$order->update_status( 'processing', sprintf( 'Retrait annulé par %s.', $user ) );
			}
			$labels = array( 'new' => 'remise en nouvelles', 'preparing' => 'en préparation', 'ready' => 'prête' );
			$order->add_order_note( sprintf( 'Suivi : %s (%s).', $labels[ $status ], $user ) );
			$order->save();
		}
		wp_send_json_success( self::row( wc_get_order( $order->get_id() ) ) );
	}
}
