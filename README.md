# les-poutineur-revival

WordPress + WooCommerce : composition de poutine en ligne, retrait en magasin (click & collect) dans 2 boutiques, envoi des commandes vers la caisse.

Tout est dans le plugin `wp-content/plugins/poutine-builder`.

## Contenu du dépôt

- `wp-content/themes/poutineurs` : thème de blocs (couleurs du logo, accueil : hero, carte, fromages/toppings, boutiques). Remplacer `assets/images/logo.png` par une version haute définition (idéalement SVG).
- `wp-content/plugins/poutine-builder` : configurateur, retrait, caisse, suivi.

## Fonctionnement

1. **La carte** : 8 poutines à prix fixe (La Classique 10 €, Bacon 12 €, Chili ! 13 €, Cochonne 13 €, Saucisse 12 €, Stephen Curry 13 €, Végétarienne 12 € (badge Vegan), Blanquette des Vikings 13 €). Chaque ligne = un ingrédient de l'étape « Ta poutine » dans **Poutine · Ingrédients** : prix, phrase d'accroche (champ *Extrait*), liste des ingrédients de la recette, badge, photo.
2. **Configurateur** `[poutine_builder]` (page *Commander*, créée à l'activation) : Ta poutine → Ton fromage (badge *Nouveau* : grains, camembert, raclette) → Ton topping (persil, coriandre, oignons frits/rouges, cacahuètes, jalapeños + *topping du mois*) → Ingrédients en plus (étape masquée tant qu'aucun extra n'est créé). Le bouton « Commander » de la carte présélectionne la recette (`?poutine=ID`).
3. **Prix** : recalculé côté serveur (recette + suppléments). Le prix envoyé par le navigateur est ignoré. La composition est revérifiée au panier.
4. **Retrait** : au checkout, boutique puis créneau (horaires, jours fermés, préparation, capacité par créneau).
5. **Caisse** : à « En cours », envoi asynchrone vers la caisse de la boutique, 5 essais, action « Renvoyer à la caisse ».

Blocs d'affichage pour le thème : `[poutine_menu]`, `[poutine_options]`, `[poutine_stores]` (tout vient du back-office).

## Suivi des commandes (personnel)

Créer une page avec `[poutine_staff]` (idéalement sur une tablette en boutique). Accès réservé aux comptes du rôle **Équipe boutique** (créé automatiquement), aux gérants et aux admins ; la page n'est pas indexée.

- 3 colonnes : Nouvelles → En préparation → Prêtes, puis « Retirée » (la commande WooCommerce passe en *Terminée*).
- Rafraîchissement toutes les 15 s, bip + titre clignotant sur nouvelle commande (bouton « Son activé » à toucher une fois par session : exigence des navigateurs).
- Heure de retrait, compte à rebours, retard en rouge, téléphone cliquable, note client, « À encaisser » si paiement au retrait, alerte si l'envoi caisse a échoué.
- Choix de la boutique mémorisé sur l'appareil.

## Démarrage

```bash
npx @wordpress/env start          # nécessite Docker
```
Puis dans l'admin : activer WooCommerce + Poutine Builder, créer une page avec `[poutine_builder]`, vérifier que la page Commande utilise le shortcode `[woocommerce_checkout]` (checkout classique), et régler **WooCommerce → Poutine Builder** (boutiques, horaires, caisse). Les ingrédients (exemples) se gèrent dans **Poutine · Ingrédients**.

## Brancher la caisse

Par défaut, adaptateur « Webhook JSON » : `POST` vers l'URL de la boutique, en-têtes `X-PB-Signature: sha256=<HMAC du corps>`, `X-PB-Idempotency-Key`, et `Authorization: Bearer` si renseigné. Corps : voir `PB_POS_Dispatcher::build_payload()`.

Pour une caisse précise, ajouter un adaptateur :

```php
add_filter( 'pb_pos_adapters', fn( $a ) => array_merge( $a, array( new Ma_Caisse_Adapter() ) ) ); // implements PB_POS_Adapter
```

## À compléter / à vérifier

- Ingrédients de chaque recette et liste des extras avec leurs prix (à saisir dans le back-office).
- Suppléments éventuels des fromages/toppings (0 € = inclus pour l'instant), adresses et horaires des boutiques (adresses reprises d'un annuaire en ligne, non vérifiées).
- Accroches : associées à la recette qui les suit sur la carte (`Basique Simple` → La Classique, etc.).

## Limites connues (v0.1)

- Checkout **classique** uniquement (pas le checkout Blocks).
- La capacité d'un créneau n'est pas verrouillée : deux commandes simultanées peuvent dépasser de 1.
- Ingrédients, prix et noms de boutiques sont des exemples à remplacer.
