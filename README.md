# les-poutineur-revival

WordPress + WooCommerce : composition de poutine en ligne, retrait en magasin (click & collect) dans 2 boutiques, envoi des commandes vers la caisse.

Tout est dans le plugin `wp-content/plugins/poutine-builder`.

## Fonctionnement

1. **Configurateur** `[poutine_builder]` : étapes Taille → Frites → Fromage → Sauce → Garnitures (max 3) → Extras (max 4). Tuiles tactiles, progression, récap + total en direct (barre collée en bas sur mobile).
2. **Prix** : recalculé côté serveur à partir des ingrédients (le prix envoyé par le navigateur est ignoré). La composition est revérifiée au panier (ingrédient en rupture → ligne retirée).
3. **Retrait** : au checkout, le client choisit la boutique puis un créneau (horaires, jours fermés, préparation minimum, capacité par créneau configurables).
4. **Caisse** : quand la commande passe en « En cours » (payée, ou paiement au retrait), un envoi asynchrone (Action Scheduler) part vers la caisse de la boutique choisie, avec 5 essais (1 min, 5 min, 15 min, 1 h). Le statut est visible dans les notes de commande, et l'action « Renvoyer la commande à la caisse » existe sur chaque commande.

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

## Limites connues (v0.1)

- Checkout **classique** uniquement (pas le checkout Blocks).
- La capacité d'un créneau n'est pas verrouillée : deux commandes simultanées peuvent dépasser de 1.
- Ingrédients, prix et noms de boutiques sont des exemples à remplacer.
