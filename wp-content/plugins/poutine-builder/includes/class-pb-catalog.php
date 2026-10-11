<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ingrédients (CPT) + définition des étapes + validation/prix côté serveur.
 */
class PB_Catalog {

	const CPT = 'pb_ingredient';

	/** @var array<int,array>|null */
	private static $cache = null;

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_meta' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register_cpt(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'       => array(
					'name'          => 'Ingrédients',
					'singular_name' => 'Ingrédient',
					'add_new_item'  => 'Ajouter un ingrédient',
					'edit_item'     => 'Modifier l’ingrédient',
					'menu_name'     => 'Poutine · Ingrédients',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-carrot',
				'supports'     => array( 'title', 'excerpt', 'thumbnail', 'page-attributes' ),
			)
		);
	}

	/**
	 * Étapes du configurateur, dans l’ordre.
	 * Filtre `pb_steps` pour en ajouter/modifier.
	 */
	public static function steps(): array {
		return apply_filters(
			'pb_steps',
			array(
				array( 'key' => 'poutine', 'label' => 'Ta poutine', 'type' => 'single', 'required' => true, 'absolute' => true, 'hint' => 'Choisis ta recette' ),
				array( 'key' => 'cheese', 'label' => 'Ton fromage', 'type' => 'single', 'required' => true, 'badge' => 'Nouveau', 'hint' => 'Choisis ton fromage' ),
				array( 'key' => 'topping', 'label' => 'Ton topping', 'type' => 'single', 'required' => true, 'hint' => 'Un topping au choix' ),
				array( 'key' => 'extras', 'label' => 'Ingrédients en plus', 'type' => 'multi', 'required' => false, 'hint' => 'Pour les gourmands' ),
			)
		);
	}

	/**
	 * Ingrédients disponibles, indexés par ID.
	 *
	 * @return array<int,array{id:int,step:string,name:string,price:float,desc:string,image:string,badge:string,contents:string}>
	 */
	public static function ingredients(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			)
		);
		self::$cache = array();
		foreach ( $posts as $p ) {
			if ( 'no' === get_post_meta( $p->ID, '_pb_available', true ) ) {
				continue;
			}
			self::$cache[ $p->ID ] = array(
				'id'    => $p->ID,
				'step'  => (string) get_post_meta( $p->ID, '_pb_step', true ),
				'name'  => $p->post_title,
				'price' => (float) get_post_meta( $p->ID, '_pb_price', true ),
				'desc'  => wp_strip_all_tags( $p->post_excerpt ),
				'image'    => (string) ( get_the_post_thumbnail_url( $p->ID, 'medium_large' ) ?: PB_URL . 'assets/placeholder.svg' ),
				'badge'    => (string) get_post_meta( $p->ID, '_pb_badge', true ),
				'contents' => (string) get_post_meta( $p->ID, '_pb_contents', true ),
			);
		}
		return self::$cache;
	}

	/** Ingrédients groupés par étape (pour le front). */
	public static function by_step(): array {
		$out = array();
		foreach ( self::ingredients() as $ing ) {
			$out[ $ing['step'] ][] = $ing;
		}
		return $out;
	}

	/**
	 * Valide une sélection et calcule le prix. Seule source de vérité pour le prix.
	 *
	 * @param mixed $selection ['size'=>12, 'toppings'=>[3,4], ...]
	 * @return array{selection:array,lines:array,total:float}|WP_Error
	 */
	public static function resolve( $selection ) {
		if ( ! is_array( $selection ) ) {
			return new WP_Error( 'pb_invalid', 'Composition invalide.' );
		}
		$ings  = self::ingredients();
		$clean = array();
		$lines = array();
		$total = 0.0;

		foreach ( self::steps() as $step ) {
			$key = $step['key'];
			$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $selection[ $key ] ?? array() ) ) ) ) );

			if ( ! $ids ) {
				if ( ! empty( $step['required'] ) ) {
					return new WP_Error( 'pb_missing', sprintf( 'Il manque une option : %s.', $step['label'] ) );
				}
				continue;
			}
			if ( 'single' === $step['type'] && count( $ids ) > 1 ) {
				return new WP_Error( 'pb_invalid', sprintf( 'Un seul choix possible pour : %s.', $step['label'] ) );
			}
			if ( ! empty( $step['max'] ) && count( $ids ) > $step['max'] ) {
				return new WP_Error( 'pb_too_many', sprintf( '%s : %d maximum.', $step['label'], $step['max'] ) );
			}
			foreach ( $ids as $id ) {
				if ( ! isset( $ings[ $id ] ) || $ings[ $id ]['step'] !== $key ) {
					return new WP_Error( 'pb_unavailable', 'Un ingrédient n’est plus disponible, merci de recomposer.' );
				}
				$total  += $ings[ $id ]['price'];
				$lines[] = array(
					'step'       => $key,
					'step_label' => $step['label'],
					'id'         => $id,
					'name'       => $ings[ $id ]['name'],
					'price'      => $ings[ $id ]['price'],
				);
			}
			$clean[ $key ] = $ids;
		}

		return array(
			'selection' => $clean,
			'lines'     => $lines,
			'total'     => round( $total, 2 ),
		);
	}

	/* ---------- Admin ---------- */

	public static function add_meta_box(): void {
		add_meta_box( 'pb_ingredient', 'Réglages de l’ingrédient', array( __CLASS__, 'render_meta_box' ), self::CPT, 'normal', 'high' );
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'pb_ingredient_save', 'pb_ingredient_nonce' );
		$step  = get_post_meta( $post->ID, '_pb_step', true );
		$price = get_post_meta( $post->ID, '_pb_price', true );
		$badge = get_post_meta( $post->ID, '_pb_badge', true );
		$cont  = get_post_meta( $post->ID, '_pb_contents', true );
		$avail = get_post_meta( $post->ID, '_pb_available', true ) ?: 'yes';
		?>
		<table class="form-table">
			<tr><th><label for="pb_step">Étape</label></th><td>
				<select name="pb_step" id="pb_step">
					<?php foreach ( self::steps() as $s ) : ?>
						<option value="<?php echo esc_attr( $s['key'] ); ?>" <?php selected( $step, $s['key'] ); ?>><?php echo esc_html( $s['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th><label for="pb_price">Prix (€)</label></th><td>
				<input type="number" step="0.01" min="0" name="pb_price" id="pb_price" value="<?php echo esc_attr( $price ); ?>">
				<p class="description">Pour une poutine : son prix. Pour le fromage, le topping et les extras : supplément (0 = inclus).</p>
			</td></tr>
			<tr><th><label for="pb_contents">Ingrédients de la recette</label></th><td>
				<textarea name="pb_contents" id="pb_contents" rows="3" class="large-text"><?php echo esc_textarea( $cont ); ?></textarea>
				<p class="description">Pour les poutines : ce qu’il y a dedans (affiché sur la carte). La phrase d’accroche va dans « Extrait ».</p>
			</td></tr>
			<tr><th><label for="pb_badge">Badge</label></th><td>
				<input type="text" name="pb_badge" id="pb_badge" value="<?php echo esc_attr( $badge ); ?>" placeholder="Topping du mois, Vegan, Nouveau…">
			</td></tr>
			<tr><th>Disponibilité</th><td>
				<label><input type="radio" name="pb_available" value="yes" <?php checked( $avail, 'yes' ); ?>> Disponible</label>
				<label><input type="radio" name="pb_available" value="no" <?php checked( $avail, 'no' ); ?>> Rupture</label>
			</td></tr>
		</table>
		<?php
	}

	public static function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['pb_ingredient_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pb_ingredient_nonce'] ) ), 'pb_ingredient_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$valid_steps = wp_list_pluck( self::steps(), 'key' );
		$step        = sanitize_key( wp_unslash( $_POST['pb_step'] ?? '' ) );
		update_post_meta( $post_id, '_pb_step', in_array( $step, $valid_steps, true ) ? $step : $valid_steps[0] );
		update_post_meta( $post_id, '_pb_price', max( 0, (float) wp_unslash( $_POST['pb_price'] ?? 0 ) ) );
		update_post_meta( $post_id, '_pb_contents', sanitize_textarea_field( wp_unslash( $_POST['pb_contents'] ?? '' ) ) );
		update_post_meta( $post_id, '_pb_badge', sanitize_text_field( wp_unslash( $_POST['pb_badge'] ?? '' ) ) );
		update_post_meta( $post_id, '_pb_available', 'no' === ( $_POST['pb_available'] ?? '' ) ? 'no' : 'yes' );
	}

	public static function columns( array $cols ): array {
		return array_slice( $cols, 0, 2, true ) + array( 'pb_step' => 'Étape', 'pb_price' => 'Prix', 'pb_avail' => 'Dispo' ) + array_slice( $cols, 2, null, true );
	}

	public static function column_content( string $col, int $post_id ): void {
		if ( 'pb_step' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_pb_step', true ) );
		} elseif ( 'pb_price' === $col ) {
			echo wp_kses_post( wc_price( (float) get_post_meta( $post_id, '_pb_price', true ) ) );
		} elseif ( 'pb_avail' === $col ) {
			echo 'no' === get_post_meta( $post_id, '_pb_available', true ) ? 'Rupture' : 'Oui';
		}
	}
}
