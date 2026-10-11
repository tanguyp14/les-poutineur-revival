<?php
/**
 * Title: Hero Les Poutineurs
 * Slug: poutineurs/hero
 * Categories: featured
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"clamp(2rem,6vw,5rem)","bottom":"clamp(2rem,6vw,5rem)","left":"1.25rem","right":"1.25rem"}}},"backgroundColor":"yellow","layout":{"type":"constrained","wideSize":"1280px"}} -->
<div class="wp-block-group alignfull has-yellow-background-color has-background" style="padding-top:clamp(2rem,6vw,5rem);padding-right:1.25rem;padding-bottom:clamp(2rem,6vw,5rem);padding-left:1.25rem">
<!-- wp:columns {"align":"wide","verticalAlignment":"center"} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center">
<!-- wp:column {"verticalAlignment":"center","width":"60%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:60%">
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Ta poutine, à ta façon.</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Choisis ta recette, ton fromage et ton topping. Commande en ligne, récupère-la chaude en boutique.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/commander/">Composer ma poutine</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline","style":{"color":{"background":"#1a1410"}}} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-background wp-element-button" href="#carte" style="background-color:#1a1410">Voir la carte</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
</div>
<!-- /wp:column -->
<!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%">
<!-- wp:image {"align":"center","sizeSlug":"large","style":{"border":{"radius":"32px","width":"4px","color":"#1a1410"}}} -->
<figure class="wp-block-image aligncenter size-large has-custom-border"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/logo.png' ) ); ?>" alt="Les Poutineurs" style="border-color:#1a1410;border-width:4px;border-radius:32px;max-width:320px;width:100%;height:auto"/></figure>
<!-- /wp:image -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->
