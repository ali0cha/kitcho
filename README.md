# Kitcho — planning de repas, courses et foyers partagés

Application PHP 8.1+ / MySQL (ou MariaDB), sans framework ni dépendance : on dépose les fichiers sur l'hébergement et c'est parti.

## Page d'accueil publique

Un visiteur non connecté arrive sur une **page de présentation** (`/`) : accroche, maquette animée de la liste de courses, « comment ça marche » en trois temps, aperçu de la semaine, atouts, questions fréquentes et appel à l'inscription. Si `ALLOW_REGISTRATION` est à `false`, les boutons deviennent « Se connecter » et la page précise que l'inscription se fait sur invitation. Elle est indexable (métadonnées, Open Graph, image de partage `assets/og-image.png`) ; `robots.txt` ne laisse accessible que cette page. Un utilisateur connecté qui ouvre `/` est redirigé vers sa semaine.

## Design

L'interface suit le système de design Kitcho (fond crème, basilic pour agir, tomate pour les favoris, beurre pour « aujourd'hui », titres en serif, motif vichy) : jetons et composants dans `assets/kitcho.css`, mise en page dans `assets/style.css`, guide complet dans [DESIGN.md](DESIGN.md). Mode clair et sombre automatiques, navigation par onglets en bas sur mobile, aucune ressource externe.

## Application installable (PWA)

Kitcho s'installe comme une vraie application : **icône sur l'écran d'accueil, plein écran** (manifeste `fullscreen`, repli `standalone`), et un **service worker** qui garde les fichiers statiques et les dernières pages consultées. Hors connexion, la liste de courses, la semaine, etc. restent lisibles, et les coches faites sans réseau sont envoyées au retour de la connexion. Les pages gardées sont effacées à la déconnexion ; une page portant un message, la connexion et les exports ne sont jamais mis en cache.

Il faut servir l'application en **HTTPS** (sauf `localhost`) : voir [TRUENAS.md](TRUENAS.md), section « Publier sur Internet en HTTPS ». Le manifeste (`/manifest.webmanifest`) et le service worker (`/sw.js`) sont générés par PHP pour suivre le dossier d'installation ; les icônes sont dans `assets/icons/`.

## Fonctionnalités

- **Premiers pas (onboarding)** : au premier lancement, une carte d'accueil sur la page Semaine explique le principe (recettes → menu → courses) et propose l'étape suivante. Les étapes se cochent toutes seules : recettes ajoutées, ingrédients saisis, premier menu, liste de courses ouverte, foyer invité (propriétaires), application installée en plein écran. La carte se masque (croix) et se rouvre depuis le profil ; un membre invité dans un foyer déjà rempli ne voit que les étapes qui le concernent.

- **Comptes** : inscription, connexion, changement de mot de passe, limitation des tentatives de connexion, protection CSRF.
- **Foyers (« households »)** : un utilisateur peut appartenir à plusieurs foyers et basculer de l'un à l'autre. Rôles *administrateur* / *membre*.
  - **Lien d'invitation** : `…/join/<jeton>`, durée 1 j / 7 j / 30 j / illimitée, usage unique ou multiple, désactivable à tout moment. La personne invitée crée son compte (ou se connecte) et rejoint le foyer automatiquement.
  - Les administrateurs nomment/retirent des membres, renomment le foyer, règlent le nombre de portions par défaut, suppriment le foyer.
- **Recettes** (partagées dans le foyer) : catégorie, lien, notes, portions, favori, ingrédients saisis en texte libre (`200 g pâtes`, `2 oignons`, `1/2 c. à café de sel`…). Le rayon du magasin est deviné, ou forcé avec `; Crèmerie`. La liste se **filtre pendant la frappe** (sans accents ni majuscules, plusieurs mots possibles) et se combine avec le filtre par catégorie. **Export / import** d'un fichier JSON (recettes + ingrédients) pour sauvegarder ou transférer vers une autre installation, sans doublons.
- **Calendrier** : vue mois et vue semaine (déjeuner / dîner, portions par repas, notes, déplacement d'un repas à une autre date, repas « libres »).
  - **Abonnement ICS** : un lien secret par foyer (`/cal/<jeton>.ics`) permet de suivre les repas dans Google Agenda, Apple Calendrier, Outlook, Thunderbird… Chaque repas devient un événement (déjeuner 12 h, dîner 19 h, sans bloquer la disponibilité) avec le nombre de personnes, la note, le lien de la recette et les ingrédients recalculés. Le lien se régénère ou se désactive depuis la page Calendrier ; il n'exige pas de connexion, donc ne le partagez qu'avec les personnes du foyer.
  - **Saisie d'un repas par un simple champ texte avec autocomplétion** : si le texte correspond à une recette (accents, pluriels et « de/du/la » ignorés, abréviations et petites fautes tolérées, ex. « pâtes bolo » → *Pâtes bolognaise*), elle est utilisée. Sinon, **la recette est créée automatiquement** (catégorie devinée, sans ingrédients) — sauf si un plat qui y ressemble déjà (ex. « Pizza » pour *Pizza maison*) : le repas reste alors libre et l'appli vous indique la recette proche. Les libellés qui ne sont pas des plats (« Restaurant », « Restes », « Chez Marie », « Cantine »…) restent des repas libres.
- **Génération du menu de la semaine** : choisissez les jours, l'app propose des plats variés — elle évite ceux faits ces N dernières semaines, favorise les favoris, limite la répétition d'une catégorie et garantit un minimum de repas végétariens. Bouton ↻ pour changer un plat. « Copier la semaine précédente » disponible.
- **Liste de courses hebdomadaire** : ingrédients agrégés (même article = additionné, kg/g et l/cl/ml convertis), adaptés au nombre de portions de chaque repas, rangés par rayon, cases à cocher sans rechargement, **mode magasin** plein écran (un rayon à la fois, grosses cibles), articles ajoutés à la main, copie en texte, impression.
  - **Pensée pour le magasin, sur téléphone** : toute la ligne est cliquable (grande zone tactile), les articles cochés descendent en bas du rayon et sont barrés, une barre de progression reste visible en haut, chaque rayon est repliable avec son compteur, « Masquer les cochés » et « Écran allumé » (l'écran ne se met pas en veille).
  - **Réseau capricieux** : la coche s'affiche instantanément ; sans réseau elle est conservée et envoyée dès le retour de la connexion.
  - **À plusieurs en même temps** : si une autre personne du foyer coche un article, votre liste se met à jour toute seule (toutes les ~12 s).
- **Menus partagés** : tous les membres du foyer voient et modifient le même planning. En plus, un **lien de partage en lecture seule** (menu + courses d'une semaine) peut être créé pour quelqu'un hors du foyer.

## Installation sur TrueNAS

Voir [TRUENAS.md](TRUENAS.md) : l'image est publiée automatiquement par GitHub Actions et se déploie avec un simple YAML à coller (fichier [`truenas/compose.yaml`](truenas/compose.yaml)).

## Installation avec Docker Compose

```bash
cp .env.example .env        # puis changez au minimum DB_PASS
docker compose up -d --build
```

L'application est alors disponible sur http://localhost:8080 (port modifiable avec `APP_PORT`). Créez votre compte sur `/register`.

- Deux services : `app` (PHP 8.3 + Apache, `mod_rewrite` activé) et `db` (MariaDB 11, données dans le volume `db_data`).
- Le schéma SQL est créé par l'application elle-même au démarrage (`bin/migrate.php`, idempotent) ; aucune étape d'installation web (`install.php` est retiré de l'image).
- La configuration se fait par variables d'environnement (`.env`) : `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_NAME`, `ALLOW_REGISTRATION`, `APP_DEBUG`, `TZ`.
- Importer vos recettes : `docker compose exec app php bin/import_recipes.php` (liste les foyers), puis `docker compose exec app php bin/import_recipes.php 1`.
- Sauvegarde : `docker compose exec db sh -c 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > sauvegarde.sql`
- Mise à jour après modification du code : `docker compose up -d --build`. Pour repartir de zéro (efface les données) : `docker compose down -v`.
- Derrière un reverse proxy HTTPS (Traefik, Caddy, nginx…), transmettez `X-Forwarded-Proto: https` : le cookie de session passe alors en `Secure`. N'exposez pas le port de la base.

## Installation (hébergement OVH mutualisé ou autre)

1. Créez une base MySQL vide depuis le manager de l'hébergeur.
2. Envoyez le contenu du dossier sur le serveur (ici `public_html/menu/`).
3. Ouvrez `https://votre-site/menu/install.php`, saisissez les identifiants de la base : l'installeur crée les tables et écrit `config.php`.
4. **Supprimez `install.php`** du serveur.
5. Créez votre compte sur `/register` : un premier foyer est créé avec.

Alternative manuelle : importez `sql/schema.sql` via phpMyAdmin, copiez `config.sample.php` en `config.php` et renseignez la base.

Prérequis : PHP ≥ 8.1 avec `pdo_mysql` et `mbstring` ; Apache avec `mod_rewrite` (le `.htaccess` fourni protège `src/`, `views/`, `sql/`, `bin/`, `seed/` et `config.php`).

### Options (`config.php`)

| Clé | Rôle |
|---|---|
| `app.allow_registration` | `false` = inscription possible uniquement via un lien d'invitation |
| `app.debug` | affiche les erreurs détaillées (laisser à `false` en production) |
| `app.timezone` | fuseau horaire (défaut `Europe/Brussels`) |

## Importer les recettes de votre fichier Excel

`seed/recipes.json` a été généré depuis `Menu.xlsx` : les libellés de la feuille « Feuille 1 » ont été regroupés par plat (variantes d'orthographe fusionnées : « bolo », « pâtes bolognaise », « pates bolo » → *Pâtes bolognaise*), avec leur nombre d'occurrences, et les liens de la feuille « Recettes » ont été ajoutés. Les plats cuisinés au moins 4 fois sont marqués favoris.

En SSH :

```bash
php bin/import_recipes.php            # liste vos foyers et leurs identifiants
php bin/import_recipes.php 1          # importe dans le foyer n° 1 (plats cuisinés ≥ 2 fois + idées jamais testées)
php bin/import_recipes.php 1 --min=1  # importe tout
```

Sans SSH : sur la page *Recettes*, « Importer une liste de plats » accepte un nom par ligne.

Les recettes importées n'ont **pas d'ingrédients** (votre Excel n'en contient pas) : elles sont utilisables dans le calendrier et la génération, mais n'alimentent la liste de courses qu'une fois leurs ingrédients saisis (un ⚠ le signale dans la semaine et la page Courses).

## Version déployée

L'image Docker embarque le commit Git qui l'a construite (fichier `VERSION`, écrit par le workflow GitHub). Elle s'affiche dans *Profil → À propos* et sur `/version`. `bin/check_deploy.sh` compare le dépôt, GitHub, l'image publiée et l'application en ligne ; en local, `GIT_SHA=$(git rev-parse HEAD) docker compose up -d --build` renseigne aussi la version.

## Structure

```
index.php            contrôleur frontal + table des routes
install.php          installeur (à supprimer après usage)
src/                 noyau (db, auth, dates, ingrédients/courses, générateur) et contrôleurs
views/               gabarits PHP
assets/              CSS + un petit JS (confirmations, copie, cases à cocher)
sql/schema.sql       schéma
bin/import_recipes.php, seed/recipes.json
```

## Limites connues

- Connexion mémorisée **30 jours** sur l'appareil. Les sessions sont gardées dans le conteneur : après une mise à jour de l'image, il faut se reconnecter une fois (les coches non envoyées de la liste de courses sont conservées sur l'appareil et renvoyées après reconnexion).
- Pas de « mot de passe oublié » par e-mail : l'administrateur du serveur réinitialise un mot de passe avec `php bin/reset_password.php <email>` (voir TRUENAS.md pour Docker).
- Semaine du lundi au dimanche ; la liste de courses porte sur une semaine à la fois.
- L'historique daté de votre Excel n'est pas importé (dates à la semaine, jours inconnus, et trop ancien pour influencer la génération).
