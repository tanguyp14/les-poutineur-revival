<?php
defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'poutineurs', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	}
);

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'editor-styles' );
	}
);
