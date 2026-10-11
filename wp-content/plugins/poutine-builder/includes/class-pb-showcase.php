<?php
defined( 'ABSPATH' ) || exit;

/**
 * Blocs d'affichage pour le thème : [poutine_menu], [poutine_options], [poutine_stores].
 * Tout vient du back-office (ingrédients, boutiques) : une seule source de vérité.
 */
class PB_Showcase {

	public static function init(): void {
		add_shortcode( 'poutine_menu', array( __CLASS__, 'menu' ) );
		add_shortcode( 'poutine_options', array( __CLASS__, 'options' ) );
		add_shortcode( 'poutine_stores', array( __CLASS__, 'stores' ) );
	}

	private static function order_url( int $poutine_id = 0 ): string {
		$url = (string) get_permalink( (int) get_option( 'pb_builder_page_id' ) );
		return $poutine_id ? add_query_arg( 'poutine', $poutine_id, $url ) : $url;
	}

	/** 13 € plutôt que 13,00 €. */
	private static function short_price( float $v ): string {
		return rtrim( rtrim( number_format( $v, 2, ',', '' ), '0' ), ',' ) . ' €';
	}

	private static function money( float $v ): string {
		return wp_strip_all_tags( wc_price( $v ) );
	}

	/** La carte : une carte par poutine, bouton vers le configurateur. */
	public static function menu(): string {
		$list = PB_Catalog::by_step()['poutine'] ?? array();
		if ( ! $list ) {
			return '';
		}
		$out = '<div class="pbx-menu">';
		foreach ( $list as $p ) {
			$out .= sprintf(
				'<article class="pbx-card"><div class="pbx-media"><img class="pbx-img" src="%1$s" alt="" loading="lazy" width="800" height="600"></div><strong class="pbx-sticker">%6$s</strong>%2$s<p class="pbx-tagline">%3$s</p><h3>%4$s</h3>%5$s<div class="pbx-foot"><a class="pbx-btn" href="%7$s">Commander</a></div></article>',
				esc_url( $p['image'] ),
				$p['badge'] ? '<span class="pbx-badge">' . esc_html( $p['badge'] ) . '</span>' : '',
				esc_html( $p['desc'] ),
				esc_html( $p['name'] ),
				$p['contents'] ? '<p class="pbx-contents">' . esc_html( $p['contents'] ) . '</p>' : '',
				esc_html( self::short_price( $p['price'] ) ),
				esc_url( self::order_url( $p['id'] ) )
			);
		}
		return $out . '</div>';
	}

	/** Fromages + toppings (avec le topping du mois mis en avant). */
	public static function options(): string {
		$by  = PB_Catalog::by_step();
		$out = '<div class="pbx-options">';
		foreach ( array( 'cheese' => 'Choisis ton fromage', 'topping' => 'Topping au choix', 'extras' => 'Ingrédients en plus' ) as $key => $title ) {
			if ( empty( $by[ $key ] ) ) {
				continue;
			}
			$step  = current( array_filter( PB_Catalog::steps(), static fn( $s ) => $s['key'] === $key ) );
			$out  .= '<section class="pbx-opt"><h3>' . esc_html( $title ) . ( ! empty( $step['badge'] ) ? ' <span class="pbx-badge">' . esc_html( $step['badge'] ) . '</span>' : '' ) . '</h3><ul>';
			foreach ( $by[ $key ] as $i ) {
				if ( 'Sans topping' === $i['name'] ) {
					continue;
				}
				$out .= '<li' . ( $i['badge'] ? ' class="is-featured"' : '' ) . '>' . esc_html( $i['name'] )
					. ( $i['badge'] ? ' <span class="pbx-badge">' . esc_html( $i['badge'] ) . '</span>' : '' )
					. ( $i['price'] > 0 ? ' <small>+' . esc_html( self::money( $i['price'] ) ) . '</small>' : '' ) . '</li>';
			}
			$out .= '</ul></section>';
		}
		return $out . '</div>';
	}

	/** Boutiques de retrait. */
	public static function stores(): string {
		$out = '<div class="pbx-stores">';
		foreach ( PB_Stores::all( true ) as $s ) {
			$out .= sprintf(
				'<article class="pbx-store"><h3>%1$s</h3><p>%2$s</p>%3$s<p class="pbx-hours">%4$s – %5$s</p></article>',
				esc_html( $s['name'] ),
				esc_html( $s['address'] ),
				$s['phone'] ? '<p><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $s['phone'] ) ) . '">' . esc_html( $s['phone'] ) . '</a></p>' : '',
				esc_html( $s['open'] ),
				esc_html( $s['close'] )
			);
		}
		return $out . '</div>';
	}
}
