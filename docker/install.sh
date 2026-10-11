#!/bin/bash
# Configure WordPress au premier démarrage. Rejouable sans risque.
WP="wp --allow-root --path=/var/www/html"
echo "[install] attente de WordPress et de la base…"
for i in $(seq 1 90); do
  [ -f /var/www/html/wp-config.php ] && $WP db check >/dev/null 2>&1 && break
  sleep 3
done
$WP db check >/dev/null 2>&1 || { echo "[install] base injoignable, abandon"; exit 1; }

if ! $WP core is-installed 2>/dev/null; then
  echo "[install] installation de WordPress"
  $WP core install \
    --url="https://${WP_DOMAIN:-${RAILWAY_PUBLIC_DOMAIN:-localhost}}" \
    --title="Les Poutineurs" \
    --admin_user="${WP_ADMIN_USER:-admin}" \
    --admin_password="${WP_ADMIN_PASSWORD:?WP_ADMIN_PASSWORD manquant}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@example.com}" \
    --skip-email
  $WP language core install fr_FR --activate || true
  $WP option update timezone_string "Europe/Paris"
  $WP option update date_format "j F Y"
  $WP option update time_format "H:i"
  $WP rewrite structure "/%postname%/" --hard
  $WP plugin activate woocommerce
  $WP option update woocommerce_default_country "FR"
  $WP option update woocommerce_currency "EUR"
  $WP option update woocommerce_cod_settings '{"enabled":"yes","title":"Payer au retrait","description":"Règle ta commande directement en boutique."}' --format=json
  $WP plugin activate advanced-custom-fields
  $WP plugin activate poutine-builder
  $WP theme activate poutineurs
  # Checkout et panier classiques (le retrait en magasin n'est pas géré par les blocs).
  $WP post update "$($WP option get woocommerce_checkout_page_id)" --post_content='[woocommerce_checkout]'
  $WP post update "$($WP option get woocommerce_cart_page_id)" --post_content='[woocommerce_cart]'
  echo "[install] terminé"
fi
