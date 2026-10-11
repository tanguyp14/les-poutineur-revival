<?php
defined( 'ABSPATH' ) || exit;

/**
 * Envoie chaque commande à la caisse de la boutique choisie, en tâche de fond avec reprises.
 */
class PB_POS_Dispatcher {

	const HOOK         = 'pb_pos_send';
	const MAX_ATTEMPTS = 5;

	public static function init(): void {
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'queue' ) );
		add_action( self::HOOK, array( __CLASS__, 'handle' ), 10, 2 );
		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'order_actions' ) );
		add_action( 'woocommerce_order_action_pb_resend_pos', array( __CLASS__, 'resend' ) );
	}

	/** @return array<string,PB_POS_Adapter> */
	public static function adapters(): array {
		$list = apply_filters( 'pb_pos_adapters', array( new PB_POS_Webhook() ) );
		$out  = array();
		foreach ( $list as $a ) {
			$out[ $a->id() ] = $a;
		}
		return $out;
	}

	public static function queue( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_pb_store_id' ) || 'sent' === $order->get_meta( '_pb_pos_status' ) ) {
			return;
		}
		$order->update_meta_data( '_pb_pos_status', 'queued' );
		$order->save();
		as_enqueue_async_action( self::HOOK, array( $order_id, 1 ), 'poutine-builder' );
	}

	public static function handle( int $order_id, int $attempt = 1 ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'sent' === $order->get_meta( '_pb_pos_status' ) ) {
			return;
		}
		$store = PB_Stores::get( (string) $order->get_meta( '_pb_store_id' ) );
		if ( ! $store || empty( $store['pos_url'] ) ) {
			$order->update_meta_data( '_pb_pos_status', 'skipped' );
			$order->add_order_note( 'Caisse : aucune URL configurée pour cette boutique, commande non transmise.' );
			$order->save();
			return;
		}
		$adapter = self::adapters()[ $store['pos_adapter'] ] ?? null;
		$result  = $adapter ? $adapter->send( self::build_payload( $order, $store ), $store ) : new WP_Error( 'pb_pos_adapter', 'Adaptateur de caisse inconnu.' );

		if ( ! is_wp_error( $result ) ) {
			$order->update_meta_data( '_pb_pos_status', 'sent' );
			$order->update_meta_data( '_pb_pos_id', $result['pos_id'] ?? '' );
			$order->add_order_note( 'Caisse : commande transmise à ' . $store['name'] . '.' );
			$order->save();
			return;
		}

		$order->add_order_note( sprintf( 'Caisse : échec envoi (essai %d/%d) — %s', $attempt, self::MAX_ATTEMPTS, $result->get_error_message() ) );
		if ( $attempt < self::MAX_ATTEMPTS ) {
			$delay = array( 1 => 60, 2 => 300, 3 => 900, 4 => 3600 )[ $attempt ];
			as_schedule_single_action( time() + $delay, self::HOOK, array( $order_id, $attempt + 1 ), 'poutine-builder' );
		} else {
			$order->update_meta_data( '_pb_pos_status', 'failed' );
			do_action( 'pb_pos_failed', $order, $result );
		}
		$order->save();
	}

	public static function build_payload( WC_Order $order, array $store ): array {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$qty     = max( 1, (int) $item->get_quantity() );
			$items[] = array(
				'name'        => $item->get_name(),
				'quantity'    => $qty,
				'unit_price'  => round( (float) $item->get_total() / $qty, 2 ),
				'total'       => (float) $item->get_total(),
				'composition' => json_decode( (string) $item->get_meta( '_pb_composition' ), true ) ?: array(),
			);
		}
		$ts = (int) $order->get_meta( '_pb_pickup_ts' );
		return array(
			'version' => 1,
			'order'   => array(
				'id'             => $order->get_id(),
				'number'         => $order->get_order_number(),
				'created_at'     => $order->get_date_created() ? $order->get_date_created()->format( DATE_ATOM ) : '',
				'total'          => (float) $order->get_total(),
				'currency'       => $order->get_currency(),
				'payment_method' => $order->get_payment_method(),
				'paid'           => $order->is_paid(),
				'note'           => $order->get_customer_note(),
			),
			'customer' => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'phone'      => $order->get_billing_phone(),
				'email'      => $order->get_billing_email(),
			),
			'pickup'  => array(
				'store_id'   => $store['id'],
				'store_name' => $store['name'],
				'at'         => wp_date( DATE_ATOM, $ts ),
				'timestamp'  => $ts,
			),
			'items'   => $items,
		);
	}

	public static function order_actions( array $actions ): array {
		$actions['pb_resend_pos'] = 'Renvoyer la commande à la caisse';
		return $actions;
	}

	public static function resend( WC_Order $order ): void {
		$order->update_meta_data( '_pb_pos_status', 'queued' );
		$order->save();
		as_enqueue_async_action( self::HOOK, array( $order->get_id(), 1 ), 'poutine-builder' );
	}
}
