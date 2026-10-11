# Passation : Les Poutineurs (WordPress / WooCommerce)

Dépôt : `tanguyp14/les-poutineur-revival`, branche `claude/poutine-builder-plugin` (pas de PR ouverte). Le client parle français ; répondre en français.

## Objectif
Site WordPress + WooCommerce pour **Les Poutineurs** (restaurant de poutines, Caen) : présentation du **click & collect**, commande en ligne d'une poutine (recette + fromage + topping), **retrait en 2 boutiques**, envoi de la commande à la **caisse Cashpad**, et page de suivi pour l'équipe. Le back-office se fait « comme d'hab » avec **ACF** (surtout pour le thème).

## Ce qui est fait (tout est sur la branche)
- **Plugin** `wp-content/plugins/poutine-builder` : configurateur `[poutine_builder]` (Ta poutine → Ton fromage → Ton topping → Extras, étape vide masquée), prix recalculé côté serveur, 2 boutiques avec créneaux de retrait (horaires, jours fermés, préparation, capacité), envoi caisse asynchrone avec 5 essais (`PB_POS_Dispatcher`, adaptateurs via le filtre `pb_pos_adapters`, adaptateur générique « webhook JSON signé HMAC »), page de suivi équipe `[poutine_staff]` (3 colonnes, rafraîchissement 15 s, bip), shortcodes d'affichage `[poutine_menu]`, `[poutine_options]`, `[poutine_stores]`. Ingrédients = CPT `pb_ingredient` (méta `_pb_step`, `_pb_price`, `_pb_badge`, `_pb_contents`, `_pb_available`).
- **Thème de blocs** `wp-content/themes/poutineurs` : accueil funky (hero, bandeaux défilants croisés, texte sur courbe SVG, cartes « plat gourmand » avec macaron de prix, animations GSAP au scroll, `prefers-reduced-motion` respecté). GSAP 3.15 et la police Bowlby One sont auto-hébergés (`assets/vendor`, `assets/fonts`). Contenu d'accueil éditable via **ACF gratuit** (`inc/acf-fields.php`, champs texte seulement, valeurs par défaut si ACF absent). En-tête = logo **typographique seul** (`assets/images/wordmark.svg`).
- **Outillage** : `.wp-env.json` + `package.json` + `bin/local-setup.sh` (`npm install && npm start`, Docker requis) ; `bin/build-zips.sh` + workflow GitHub Actions qui produisent `poutine-builder.zip` et `poutineurs.zip`. README à jour.
- **Maquette interactive** (artifact privé, générée depuis le vrai code avec de fausses données) : https://claude.ai/artifact/8rEgBKfbTRCk8haL8FzVQ8. Les sources de génération étaient dans le scratchpad de l'ancienne session et ne sont pas versionnées.

## La carte (source : le client)
La Classique 10 € (« Basique Simple »), La Classique Bacon 12 € (« On revoit les bases »), Chili ! 13 € (« Aie Caramba ! »), La Cochonne 13 € (« C'est le nom de la recette »), La Saucisse 12 € (« Mais t'avais dit qu'on ferait des knakis :( »), La Stephen Curry 13 € (« Not 1, not 2, but 3 🏀 »), La Végétarienne | Vegan 12 € (« Viens pas woingner on a pensé à toi »), La Blanquette des Vikings 13 € (« Comment est votre Blanquette ? »).
Fromage (badge **Nouveau**) : fromage en grains, camembert, raclette fondue. Topping au choix : persil, coriandre, oignons frits, oignons rouges, cacahuètes, jalapeños ; **topping du mois** : chips de carottes maison.
Hypothèse non confirmée : chaque accroche est associée à la recette qui la suit sur la carte.

## À recevoir du client
Contenu de chaque recette, liste des **extras avec prix**, suppléments éventuels des fromages/toppings (0 € = inclus pour l'instant), **horaires** des 2 boutiques, règles de **paiement** (en ligne ou au retrait) et d'**annulation** (réponses « À renseigner » dans la FAQ), photos des plats (images factices « PHOTO » en attendant, remplacées par l'image mise en avant), éventuel logo haute définition.
Adresses reprises d'un annuaire en ligne, **non vérifiées** : 12 avenue de la Libération et 21 rue Écuyère, Caen. Prix, fromages et toppings à 0 € sont des valeurs de départ.

## Caisse Cashpad : en attente
Aucune doc API publique trouvée pour *créer* une commande depuis un site (l'API connue sert surtout à lire les commandes clôturées). Le client contacte Cashpad : API de création de commande, clés **par boutique**, format d'identification, paiement transmis ou non, ou intermédiaire intégré (Deliverect, Obypay : payant, tarif non public, et Obypay remplacerait le configurateur). **Prochaine étape dès réponse** : écrire une classe `PB_POS_Cashpad implements PB_POS_Adapter` (`includes/pos/`), l'enregistrer via `pb_pos_adapters`, ajouter les champs nécessaires dans `PB_Settings`.

## Limites et pièges
- **Rien n'a été testé dans un vrai WordPress** : wordpress.org est bloqué dans l'environnement cloud. Vérifié : syntaxe PHP/JS, logique de prix par tests avec des stubs, interfaces dans Chromium avec des données factices. Le client installe en local (`npm start` ou LocalWP) : corriger d'abord ce qui casse au premier lancement.
- Le site existant `lespoutineurs.fr` est inaccessible depuis le cloud (réseau). Le client ne peut pas modifier l'accès réseau depuis son téléphone ; il peut envoyer captures ou texte. Pas de Railway : il a refusé.
- Checkout **classique** uniquement (pas le checkout Blocks). Capacité d'un créneau non verrouillée. Pas d'e-mail par boutique ; e-mails WooCommerce à configurer en SMTP.
- ACF : version gratuite suffisante ; options pages et blocs (Pro) non utilisés. Si le client veut passer le back-office des ingrédients en ACF, c'est à faire (aujourd'hui : meta box native).
- Conventions : pas d'emoji/icône dans l'interface (images factices à la place) ; ne pas ouvrir de PR sans demande ; messages de commit terminés par les lignes d'attribution Claude.
