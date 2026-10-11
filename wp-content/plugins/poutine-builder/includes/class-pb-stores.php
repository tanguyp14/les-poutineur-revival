<?php
defined( 'ABSPATH' ) || exit;

/**
 * Boutiques de retrait + créneaux horaires.
 */
class PB_Stores {

	const OPTION = 'pb_stores';

	public static function defaults(): array {
		$base = array(
			'enabled'      => true,
			'address'      => '',
			'phone'        => '',
			'open'         => '11:00',
			'close'        => '21:00',
			'closed_days'  => array( 0 ), // 0 = dimanche.
			'slot_minutes' => 15,
			'prep_minutes' => 20,
			'max_days'     => 2,
			'capacity'     => 6, // commandes max par créneau.
			'pos_adapter'  => 'webhook',
			'pos_url'      => '',
			'pos_secret'   => '',
			'pos_token'    => '',
		);
		return array(
			'store_1' => array_merge( $base, array( 'id' => 'store_1', 'name' => 'Caen — Libération', 'address' => '12 avenue de la Libération, Caen' ) ),
			'store_2' => array_merge( $base, array( 'id' => 'store_2', 'name' => 'Caen — Écuyère', 'address' => '21 rue Écuyère, Caen' ) ),
		);
	}

	/** @return array<string,array> */
	public static function all( bool $only_enabled = false ): array {
		$saved  = get_option( self::OPTION, array() );
		$stores = array();
		foreach ( self::defaults() as $id => $def ) {
			$stores[ $id ] = array_merge( $def, is_array( $saved[ $id ] ?? null ) ? $saved[ $id ] : array() );
		}
		if ( $only_enabled ) {
			$stores = array_filter( $stores, static fn( $s ) => ! empty( $s['enabled'] ) );
		}
		return $stores;
	}

	public static function get( string $id ): ?array {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Créneaux de retrait disponibles.
	 *
	 * @return array<int,array{value:int,label:string,day:string}>
	 */
	public static function slots( string $store_id ): array {
		$s = self::get( $store_id );
		if ( ! $s || empty( $s['enabled'] ) ) {
			return array();
		}
		$tz       = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $tz );
		$earliest = $now->modify( '+' . (int) $s['prep_minutes'] . ' minutes' )->getTimestamp();
		$step     = max( 5, (int) $s['slot_minutes'] ) * 60;
		$max_days = max( 0, (int) $s['max_days'] );
		$booked   = self::booked( $store_id, $now->getTimestamp(), $now->modify( '+' . ( $max_days + 1 ) . ' days' )->getTimestamp() );

		$out = array();
		for ( $d = 0; $d <= $max_days; $d++ ) {
			$day = $now->modify( "+$d days" );
			if ( in_array( (int) $day->format( 'w' ), array_map( 'intval', (array) $s['closed_days'] ), true ) ) {
				continue;
			}
			[ $oh, $om ] = array_map( 'intval', explode( ':', $s['open'] ) );
			[ $ch, $cm ] = array_map( 'intval', explode( ':', $s['close'] ) );
			$start       = $day->setTime( $oh, $om )->getTimestamp();
			$end         = $day->setTime( $ch, $cm )->getTimestamp();
			$day_label   = 0 === $d ? 'Aujourd’hui' : ( 1 === $d ? 'Demain' : wp_date( 'l j F', $start, $tz ) );

			for ( $t = $start; $t + $step <= $end; $t += $step ) {
				if ( $t < $earliest || ( $booked[ $t ] ?? 0 ) >= (int) $s['capacity'] ) {
					continue;
				}
				$out[] = array(
					'value' => $t,
					'label' => wp_date( 'H:i', $t, $tz ),
					'day'   => $day_label,
				);
			}
		}
		return $out;
	}

	public static function slot_is_valid( string $store_id, int $ts ): bool {
		foreach ( self::slots( $store_id ) as $slot ) {
			if ( $slot['value'] === $ts ) {
				return true;
			}
		}
		return false;
	}

	/** Nombre de commandes déjà prises par créneau. @return array<int,int> */
	private static function booked( string $store_id, int $from, int $to ): array {
		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => array( 'wc-on-hold', 'wc-processing', 'wc-completed' ),
				'meta_query' => array(
					array( 'key' => '_pb_store_id', 'value' => $store_id ),
					array( 'key' => '_pb_pickup_ts', 'value' => array( $from, $to ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
				),
			)
		);
		$count = array();
		foreach ( $orders as $o ) {
			$ts           = (int) $o->get_meta( '_pb_pickup_ts' );
			$count[ $ts ] = ( $count[ $ts ] ?? 0 ) + 1;
		}
		return $count;
	}
}
