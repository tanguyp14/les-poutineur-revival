<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adaptateur générique : POST JSON signé (HMAC-SHA256) vers l’URL de la boutique.
 * Convient à une caisse qui expose une API/webhook, ou à un middleware (Zapier, n8n, Make...).
 */
class PB_POS_Webhook implements PB_POS_Adapter {

	public function id(): string {
		return 'webhook';
	}

	public function label(): string {
		return 'Webhook JSON générique';
	}

	public function send( array $payload, array $store ) {
		if ( empty( $store['pos_url'] ) ) {
			return new WP_Error( 'pb_pos_no_url', 'URL de la caisse non configurée.' );
		}
		$body    = wp_json_encode( $payload );
		$headers = array(
			'Content-Type'          => 'application/json',
			'X-PB-Idempotency-Key'  => 'order-' . $payload['order']['id'],
		);
		if ( ! empty( $store['pos_secret'] ) ) {
			$headers['X-PB-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $store['pos_secret'] );
		}
		if ( ! empty( $store['pos_token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $store['pos_token'];
		}

		$res = wp_remote_post( $store['pos_url'], array( 'timeout' => 15, 'headers' => $headers, 'body' => $body ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'pb_pos_http', sprintf( 'La caisse a répondu HTTP %d.', $code ) );
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		return array( 'pos_id' => is_array( $json ) ? (string) ( $json['id'] ?? '' ) : '' );
	}
}
