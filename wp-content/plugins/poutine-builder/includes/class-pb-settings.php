<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce > Poutine Builder : boutiques, horaires, caisse.
 */
class PB_Settings {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_pb_save_stores', array( __CLASS__, 'save' ) );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'Poutine Builder', 'Poutine Builder', 'manage_woocommerce', 'poutine-builder', array( __CLASS__, 'render' ) );
	}

	public static function render(): void {
		$days     = array( 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi' );
		$adapters = PB_POS_Dispatcher::adapters();
		?>
		<div class="wrap">
			<h1>Poutine Builder — Boutiques de retrait</h1>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p>Réglages enregistrés.</p></div>
			<?php endif; ?>
			<p>Shortcode du configurateur : <code>[poutine_builder]</code> — utilise le checkout WooCommerce « classique » (<code>[woocommerce_checkout]</code>).</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pb_save_stores">
				<?php wp_nonce_field( 'pb_save_stores' ); ?>
				<?php foreach ( PB_Stores::all() as $id => $s ) : $n = "stores[$id]"; ?>
					<h2><?php echo esc_html( $s['name'] ); ?></h2>
					<table class="form-table">
						<tr><th>Active</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> Proposer le retrait ici</label></td></tr>
						<?php
						self::text( $n, 'name', 'Nom', $s );
						self::text( $n, 'address', 'Adresse', $s );
						self::text( $n, 'phone', 'Téléphone', $s );
						self::text( $n, 'open', 'Ouverture (HH:MM)', $s );
						self::text( $n, 'close', 'Fermeture (HH:MM)', $s );
						?>
						<tr><th>Jours fermés</th><td>
							<?php foreach ( $days as $i => $d ) : ?>
								<label style="margin-right:1em"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[closed_days][]" value="<?php echo (int) $i; ?>" <?php checked( in_array( $i, array_map( 'intval', $s['closed_days'] ), true ) ); ?>> <?php echo esc_html( $d ); ?></label>
							<?php endforeach; ?>
						</td></tr>
						<?php
						self::text( $n, 'slot_minutes', 'Durée d’un créneau (min)', $s, 'number' );
						self::text( $n, 'prep_minutes', 'Préparation minimum (min)', $s, 'number' );
						self::text( $n, 'max_days', 'Commander jusqu’à (jours à l’avance)', $s, 'number' );
						self::text( $n, 'capacity', 'Commandes max par créneau', $s, 'number' );
						?>
						<tr><th>Caisse — adaptateur</th><td>
							<select name="<?php echo esc_attr( $n ); ?>[pos_adapter]">
								<?php foreach ( $adapters as $a ) : ?>
									<option value="<?php echo esc_attr( $a->id() ); ?>" <?php selected( $s['pos_adapter'], $a->id() ); ?>><?php echo esc_html( $a->label() ); ?></option>
								<?php endforeach; ?>
							</select>
						</td></tr>
						<?php
						self::text( $n, 'pos_url', 'Caisse — URL de l’API', $s, 'url' );
						self::text( $n, 'pos_secret', 'Caisse — secret de signature (HMAC)', $s );
						self::text( $n, 'pos_token', 'Caisse — jeton Bearer', $s );
						?>
					</table>
				<?php endforeach; ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function text( string $n, string $key, string $label, array $s, string $type = 'text' ): void {
		printf(
			'<tr><th><label>%1$s</label></th><td><input class="regular-text" type="%2$s" name="%3$s[%4$s]" value="%5$s"></td></tr>',
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $n ),
			esc_attr( $key ),
			esc_attr( (string) $s[ $key ] )
		);
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.', 403 );
		}
		check_admin_referer( 'pb_save_stores' );

		$time  = static fn( $v, $def ) => preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $v ) ? (string) $v : $def;
		$input = isset( $_POST['stores'] ) && is_array( $_POST['stores'] ) ? wp_unslash( $_POST['stores'] ) : array();
		$out   = array();

		foreach ( PB_Stores::defaults() as $id => $def ) {
			$in          = is_array( $input[ $id ] ?? null ) ? $input[ $id ] : array();
			$out[ $id ]  = array(
				'id'           => $id,
				'enabled'      => ! empty( $in['enabled'] ),
				'name'         => sanitize_text_field( $in['name'] ?? $def['name'] ),
				'address'      => sanitize_text_field( $in['address'] ?? '' ),
				'phone'        => sanitize_text_field( $in['phone'] ?? '' ),
				'open'         => $time( $in['open'] ?? '', $def['open'] ),
				'close'        => $time( $in['close'] ?? '', $def['close'] ),
				'closed_days'  => array_values( array_filter( array_map( 'intval', (array) ( $in['closed_days'] ?? array() ) ), static fn( $d ) => $d >= 0 && $d <= 6 ) ),
				'slot_minutes' => max( 5, (int) ( $in['slot_minutes'] ?? 15 ) ),
				'prep_minutes' => max( 0, (int) ( $in['prep_minutes'] ?? 20 ) ),
				'max_days'     => max( 0, min( 14, (int) ( $in['max_days'] ?? 2 ) ) ),
				'capacity'     => max( 1, (int) ( $in['capacity'] ?? 6 ) ),
				'pos_adapter'  => sanitize_key( $in['pos_adapter'] ?? 'webhook' ),
				'pos_url'      => esc_url_raw( $in['pos_url'] ?? '' ),
				'pos_secret'   => sanitize_text_field( $in['pos_secret'] ?? '' ),
				'pos_token'    => sanitize_text_field( $in['pos_token'] ?? '' ),
			);
		}
		update_option( PB_Stores::OPTION, $out, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'poutine-builder', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
