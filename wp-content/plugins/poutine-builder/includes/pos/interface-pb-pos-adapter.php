<?php
defined( 'ABSPATH' ) || exit;

/**
 * Un adaptateur transforme le payload générique en appel vers un modèle de caisse précis.
 * Enregistrer le sien via le filtre `pb_pos_adapters`.
 */
interface PB_POS_Adapter {

	public function id(): string;

	public function label(): string;

	/**
	 * @param array $payload Commande normalisée (voir PB_POS_Dispatcher::build_payload).
	 * @param array $store   Réglages de la boutique (pos_url, pos_secret, pos_token...).
	 * @return array|WP_Error Infos de réponse (ex. ['pos_id' => '...']) ou erreur => nouvel essai.
	 */
	public function send( array $payload, array $store );
}
