<?php
defined( 'ABSPATH' ) || exit;

/**
 * Contenu éditable de la page d'accueil via ACF (version gratuite suffisante : champs texte uniquement).
 * Sans ACF, les valeurs par défaut ci-dessous s'affichent : le site fonctionne dans tous les cas.
 */

function pbt_defaults(): array {
	return array(
		'banner'       => '',
		'marquee_text'   => 'Poutine ● Fromage en grains ● Camembert ● Raclette fondue ● Click & collect ● Topping du mois ● ',
		'marquee_text_2' => 'Compose ta poutine ● Réserve ton heure ● Récupère-la chaude ● ',
		'ring_text'      => 'Ta poutine à ta façon ● Click & collect ● ',
		'hero_title'   => 'Ta poutine, à ta façon.',
		'hero_text'    => 'Choisis ta recette, ton fromage et ton topping. Commande en ligne, récupère-la chaude en boutique.',
		'hero_cta'     => 'Composer ma poutine',
		'how_title'    => 'Click & collect : comment ça marche ?',
		'step_1_title' => 'Compose',
		'step_1_text'  => 'Choisis ta poutine, ton fromage et ton topping sur le site.',
		'step_2_title' => 'Réserve',
		'step_2_text'  => 'Sélectionne ta boutique et ton heure de retrait, puis valide ta commande.',
		'step_3_title' => 'Récupère',
		'step_3_text'  => 'Passe en boutique à l’heure choisie : ta poutine est prête, bien chaude.',
		'faq_title'    => 'Questions fréquentes',
		'faq_1_q'      => 'Dois-je payer en ligne ?',
		'faq_1_a'      => 'À renseigner : paiement en ligne et/ou au retrait.',
		'faq_2_q'      => 'Combien de temps à l’avance dois-je commander ?',
		'faq_2_a'      => 'Compte une vingtaine de minutes de préparation minimum ; les créneaux disponibles s’affichent à la commande.',
		'faq_3_q'      => 'Puis-je modifier ou annuler ma commande ?',
		'faq_3_a'      => 'À renseigner : appelle directement la boutique choisie.',
		'faq_4_q'      => 'Où récupérer ma commande ?',
		'faq_4_a'      => 'Dans la boutique que tu as choisie au moment de commander (adresses plus haut sur la page).',
	);
}

/** Valeur ACF de la page d'accueil, avec repli sur le texte par défaut. */
function pbt_field( string $name ): string {
	$default = pbt_defaults()[ $name ] ?? '';
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}
	$value = get_field( $name, (int) get_option( 'page_on_front' ) );
	return is_string( $value ) && '' !== trim( $value ) ? $value : $default;
}

add_action(
	'acf/init',
	static function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}
		$d      = pbt_defaults();
		$fields = array(
			array( 'key' => 'pbt_tab_hero', 'label' => 'En-tête', 'type' => 'tab' ),
			array( 'key' => 'pbt_banner', 'label' => 'Bandeau d’annonce', 'name' => 'banner', 'type' => 'text', 'instructions' => 'Ex. « Fermé exceptionnellement lundi ». Laisser vide pour masquer.' ),
			array( 'key' => 'pbt_hero_title', 'label' => 'Titre', 'name' => 'hero_title', 'type' => 'text', 'default_value' => $d['hero_title'] ),
			array( 'key' => 'pbt_hero_text', 'label' => 'Texte', 'name' => 'hero_text', 'type' => 'textarea', 'rows' => 3, 'default_value' => $d['hero_text'] ),
			array( 'key' => 'pbt_hero_cta', 'label' => 'Bouton', 'name' => 'hero_cta', 'type' => 'text', 'default_value' => $d['hero_cta'] ),
			array( 'key' => 'pbt_ring_text', 'label' => 'Texte rond autour du logo', 'name' => 'ring_text', 'type' => 'text', 'default_value' => $d['ring_text'] ),
			array( 'key' => 'pbt_tab_bands', 'label' => 'Bandeaux défilants', 'type' => 'tab' ),
			array( 'key' => 'pbt_marquee_text', 'label' => 'Bandeau 1 (jaune/noir)', 'name' => 'marquee_text', 'type' => 'text', 'default_value' => $d['marquee_text'], 'instructions' => 'Sépare les mots avec ●' ),
			array( 'key' => 'pbt_marquee_text_2', 'label' => 'Bandeau 2 (orange)', 'name' => 'marquee_text_2', 'type' => 'text', 'default_value' => $d['marquee_text_2'] ),
			array( 'key' => 'pbt_tab_how', 'label' => 'Click & collect', 'type' => 'tab' ),
			array( 'key' => 'pbt_how_title', 'label' => 'Titre de la section', 'name' => 'how_title', 'type' => 'text', 'default_value' => $d['how_title'] ),
		);
		foreach ( array( 1, 2, 3 ) as $i ) {
			$fields[] = array( 'key' => "pbt_step_{$i}_title", 'label' => "Étape $i — titre", 'name' => "step_{$i}_title", 'type' => 'text', 'default_value' => $d[ "step_{$i}_title" ], 'wrapper' => array( 'width' => '30' ) );
			$fields[] = array( 'key' => "pbt_step_{$i}_text", 'label' => "Étape $i — texte", 'name' => "step_{$i}_text", 'type' => 'textarea', 'rows' => 2, 'default_value' => $d[ "step_{$i}_text" ], 'wrapper' => array( 'width' => '70' ) );
		}
		$fields[] = array( 'key' => 'pbt_tab_faq', 'label' => 'FAQ', 'type' => 'tab' );
		$fields[] = array( 'key' => 'pbt_faq_title', 'label' => 'Titre de la section', 'name' => 'faq_title', 'type' => 'text', 'default_value' => $d['faq_title'] );
		foreach ( array( 1, 2, 3, 4 ) as $i ) {
			$fields[] = array( 'key' => "pbt_faq_{$i}_q", 'label' => "Question $i", 'name' => "faq_{$i}_q", 'type' => 'text', 'default_value' => $d[ "faq_{$i}_q" ] );
			$fields[] = array( 'key' => "pbt_faq_{$i}_a", 'label' => "Réponse $i", 'name' => "faq_{$i}_a", 'type' => 'textarea', 'rows' => 2, 'default_value' => $d[ "faq_{$i}_a" ] );
		}
		acf_add_local_field_group(
			array(
				'key'      => 'group_pbt_home',
				'title'    => 'Page d’accueil',
				'fields'   => $fields,
				'location' => array( array( array( 'param' => 'page_type', 'operator' => '==', 'value' => 'front_page' ) ) ),
			)
		);
	}
);

/* ---------- Blocs rendus dans front-page.html ---------- */

add_shortcode(
	'pbt_banner',
	static function () {
		$t = pbt_field( 'banner' );
		return $t ? '<div class="pbt-banner" role="status">' . esc_html( $t ) . '</div>' : '';
	}
);

add_shortcode(
	'pbt_hero',
	static function () {
		$ring = trim( pbt_field( 'ring_text' ) );
		return sprintf(
			'<section class="pbt-hero"><div class="pbt-hero__text"><h1>%1$s</h1><p class="pbt-hero__lead">%2$s</p><p class="pbt-hero__cta"><a class="pbx-btn pbx-btn--lg" href="%3$s">%4$s</a> <a class="pbx-btn pbx-btn--ghost pbx-btn--lg" href="#carte">Voir la carte</a></p></div><div class="pbt-hero__logo"><div class="pbt-ring-wrap"><svg class="pbt-ring" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><defs><path id="pbt-ring-path" d="M200,200 m-150,0 a150,150 0 1,1 300,0 a150,150 0 1,1 -300,0"/></defs><text><textPath href="#pbt-ring-path" textLength="930" lengthAdjust="spacing">%6$s</textPath></text></svg></div><img class="pbt-hero__img" src="%5$s" alt="Les Poutineurs" width="320" height="341"></div></section>',
			esc_html( pbt_field( 'hero_title' ) ),
			esc_html( pbt_field( 'hero_text' ) ),
			esc_url( home_url( '/commander/' ) ),
			esc_html( pbt_field( 'hero_cta' ) ),
			esc_url( get_theme_file_uri( 'assets/images/logo.png' ) ),
			esc_html( $ring )
		);
	}
);

add_shortcode(
	'pbt_marquee',
	static function ( $atts ) {
		$variant = 'b' === ( $atts['variant'] ?? 'a' ) ? 'b' : 'a';
		$text    = pbt_field( 'b' === $variant ? 'marquee_text_2' : 'marquee_text' );
		return '<div class="pbt-marquee pbt-marquee--' . $variant . '" aria-hidden="true"><div class="pbt-marquee__track"><span class="pbt-marquee__item">' . esc_html( $text ) . '</span></div></div>';
	}
);

add_shortcode(
	'pbt_how',
	static function () {
		$words = array();
		foreach ( array( 1, 2, 3 ) as $i ) {
			$words[] = mb_strtoupper( pbt_field( "step_{$i}_title" ) );
		}
		$line = implode( ' ● ', $words ) . ' ● ';
		$out  = '<h2>' . esc_html( pbt_field( 'how_title' ) ) . '</h2>';
		$out .= '<svg class="pbt-curve" viewBox="0 0 1200 240" aria-hidden="true" focusable="false"><path id="pbt-wave" d="M-60,120 C140,20 340,220 540,120 S940,20 1260,120" fill="none"/><text><textPath id="pbt-wave-text" href="#pbt-wave" startOffset="0">' . esc_html( str_repeat( $line, 5 ) ) . '</textPath></text></svg>';
		$out .= '<ol class="pbt-steps">';
		foreach ( array( 1, 2, 3 ) as $i ) {
			$out .= '<li><span class="pbt-steps__n" aria-hidden="true">' . $i . '</span><h3>' . esc_html( pbt_field( "step_{$i}_title" ) ) . '</h3><p>' . esc_html( pbt_field( "step_{$i}_text" ) ) . '</p></li>';
		}
		return $out . '</ol>';
	}
);

add_shortcode(
	'pbt_faq',
	static function () {
		$out = '<h2>' . esc_html( pbt_field( 'faq_title' ) ) . '</h2><div class="pbt-faq">';
		foreach ( array( 1, 2, 3, 4 ) as $i ) {
			$q = pbt_field( "faq_{$i}_q" );
			if ( '' === $q ) {
				continue;
			}
			$out .= '<details><summary>' . esc_html( $q ) . '</summary><p>' . esc_html( pbt_field( "faq_{$i}_a" ) ) . '</p></details>';
		}
		return $out . '</div>';
	}
);

/** À l'activation du thème : une page d'accueil statique « Accueil » (nécessaire pour ACF). */
add_action(
	'after_switch_theme',
	static function () {
		if ( 'page' === get_option( 'show_on_front' ) && get_post_status( (int) get_option( 'page_on_front' ) ) ) {
			return;
		}
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Accueil', 'post_name' => 'accueil' ) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $id );
		}
	}
);
