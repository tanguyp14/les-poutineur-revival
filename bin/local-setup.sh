#!/bin/bash
# Configure le WordPress local (wp-env) : français, euro, thème, pages, checkout classique. Rejouable.
set -e
wp() { npx wp-env run cli wp "$@"; }

wp language core install fr_FR --activate >/dev/null || true
wp option update timezone_string "Europe/Paris"
wp rewrite structure "/%postname%/" --hard
wp theme activate poutineurs
wp plugin activate woocommerce advanced-custom-fields poutine-builder
wp option update woocommerce_default_country FR
wp option update woocommerce_currency EUR
wp option update woocommerce_cod_settings '{"enabled":"yes","title":"Payer au retrait","description":"Règle ta commande en boutique."}' --format=json
# Le plugin crée ses pages à l'activation ; on s'assure du checkout classique.
wp post update "$(wp option get woocommerce_checkout_page_id)" --post_content='[woocommerce_checkout]'
wp post update "$(wp option get woocommerce_cart_page_id)" --post_content='[woocommerce_cart]'
echo
echo "Site : http://localhost:8888   Admin : http://localhost:8888/wp-admin  (admin / password)"
