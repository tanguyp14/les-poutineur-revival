<?php
defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		$ver = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'poutineurs', get_stylesheet_uri(), array(), $ver );
		if ( is_front_page() ) {
			$v = get_theme_file_uri( 'assets/vendor/' );
			wp_enqueue_script( 'gsap', $v . 'gsap.min.js', array(), '3.15.0', array( 'in_footer' => true, 'strategy' => 'defer' ) );
			wp_enqueue_script( 'gsap-scrolltrigger', $v . 'ScrollTrigger.min.js', array( 'gsap' ), '3.15.0', array( 'in_footer' => true, 'strategy' => 'defer' ) );
			wp_enqueue_script( 'poutineurs-funky', get_theme_file_uri( 'assets/js/funky.js' ), array( 'gsap-scrolltrigger' ), $ver, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}
);

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'editor-styles' );
	}
);

require_once get_theme_file_path( 'inc/acf-fields.php' );
