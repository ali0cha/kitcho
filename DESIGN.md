# Kitcho — guide d'intégration du design (pour Claude Code)

## Prompt à coller dans Claude Code

> Applique le design Kitcho à l'application existante (PHP rendu côté serveur, CSS à la main, JS minimal, aucune ressource externe).
> 1. Copie `kitcho.css` dans le dossier public de l'app (par ex. `public/css/kitcho.css`) et charge-le dans le layout, avant les styles propres à l'app.
> 2. Lis `KITCHO-DESIGN.md` en entier : c'est la référence (jetons, composants, structure HTML, écrans, règles).
> 3. Migre écran par écran dans cet ordre : Courses, Semaine, Recettes, puis Calendrier, Foyer, Entrée, Partage public, Erreurs. Ne change ni les fonctionnalités, ni le vocabulaire, ni la structure des données.
> 4. Supprime l'ancien CSS que les classes `k-*` remplacent. Ne garde que ce qui est spécifique à l'app.
> 5. Après chaque écran, vérifie : clair et sombre, 390 px et 1100 px, focus au clavier, impression de la liste de courses.

## Principes

- Direction : cuisine de famille, interface d'aujourd'hui. Fond crème, **basilic** (vert) pour agir, **tomate** pour les favoris et les touches chaudes, **beurre** pour « aujourd'hui ».
- Mobile d'abord. L'écran Courses sur téléphone est le plus important.
- Aucun CDN, aucune police externe, aucune bibliothèque d'icônes. Icônes = masques CSS dans `kitcho.css`.
- Interface en français, vouvoiement ou infinitif.
- Zones tactiles ≥ 44 px, contrastes AA, focus visible, jamais la couleur seule (toujours pictogramme ou texte en plus).

## Mise en place

```html
<link rel="stylesheet" href="/css/kitcho.css">
<body class="k-app">
```

- Mode clair par défaut, sombre automatique via `prefers-color-scheme`.
- Forcer un thème sur un élément : `data-theme="light"` ou `data-theme="dark"`.
- Sur `<html>` ou `<body>`, ne mettez pas `data-theme` sauf si vous proposez un sélecteur de thème (le sombre automatique ne s'applique que sans attribut).
- Titres en serif : `--font-display` commence par `"Fraunces"`. Si vous hébergez Fraunces (fichiers `.woff2` locaux avec `@font-face`), elle sera utilisée ; sinon repli Palatino, Georgia.

## Jetons (variables CSS)

Même nom en clair et en sombre.

| Rôle | Variables |
|---|---|
| Fonds | `--bg`, `--surface`, `--surface-2`, `--surface-3` |
| Texte | `--text`, `--muted` |
| Bordures | `--border` (séparateurs), `--border-strong` (champs, cases : contraste 3:1) |
| Action (basilic) | `--brand`, `--brand-hover`, `--on-brand`, `--brand-soft`, `--brand-ink` |
| Coché / succès | `--basil`, `--on-basil`, `--basil-soft`, `--basil-ink` |
| Chaud (tomate) | `--tomato`, `--tomato-soft` |
| Aujourd'hui / attention | `--butter`, `--butter-soft`, `--butter-ink` |
| Danger / info | `--danger`, `--on-danger`, `--danger-soft`, `--danger-ink` ; `--info`, `--info-soft`, `--info-ink` |
| Focus | `--focus` |
| Catégories | `--cat-veg`, `--cat-fish`, `--cat-meat`, `--cat-soup`, `--cat-salad`, `--cat-other` et leur fond `…-s` |
| Motif vichy | `--gingham` |
| Échelle | `--fs-xs…4xl` (12→48), `--s-1…8` (4→64), `--r-sm/md/lg/xl/pill` (8/12/18/26), `--sh-1/2/3`, `--t-fast/med/slow`, `--ease`, `--spring`, `--tap` (44px) |

Règles : n'utilisez jamais de couleur en dur. Texte sur fond coloré : utilisez la paire `--x-ink` sur `--x-soft`.

## Icônes

`<i class="k-i i-check" aria-hidden="true"></i>` : couleur = `currentColor`. Tailles : `k-i--sm` 16, défaut 22, `k-i--lg` 28, `k-i--xl` 40.

Disponibles : `check plus x refresh down up left right more sun moon eyeoff copy print star starfill external warn info wifioff trash user mail spark link share logout search edit lock` ; navigation `week month basket book home` ; catégories `leaf fish meat soup salad plate` ; rayons `apple bread meat milk jar snow glass bag`.

Icône seule dans un bouton : toujours `aria-label` sur le bouton.

## Catégories et rayons

| Catégorie | Classe | Icône |
|---|---|---|
| Végétarien | `cat-veg` | `i-leaf` |
| Poisson | `cat-fish` | `i-fish` |
| Viande | `cat-meat` | `i-meat` |
| Soupe | `cat-soup` | `i-soup` |
| Salade | `cat-salad` | `i-salad` |
| Autre | `cat-other` | `i-plate` |

Rayons, dans l'ordre du magasin : Fruits & légumes `i-apple` → Boulangerie `i-bread` → Boucherie & poissonnerie `i-meat` → Crèmerie `i-milk` → Épicerie `i-jar` → Surgelés `i-snow` → Boissons `i-glass` → Autre `i-bag`.

## Composants (classes et HTML)

**Boutons** : `k-btn` + `k-btn--primary | --secondary | --ghost | --danger | --danger-o`, taille `k-btn--sm`, pleine largeur `k-btn--block`. Icône seule : `k-iconbtn` (+ `k-iconbtn--tint`). Un seul bouton primaire par zone.

**Bascule de barre d'outils** : `<button class="k-pill" aria-pressed="true|false">`. Quand actif, ajouter une icône `i-check` devant le libellé.

**Puce de jour** : `<button class="k-day" aria-pressed="true">Lun</button>` (coche affichée automatiquement quand actif).

**Champs** :
```html
<div class="k-field"><label class="k-label" for="x">Nom</label><input class="k-input" id="x"><p class="k-help">Aide</p></div>
<div class="k-selectwrap"><select class="k-select">…</select><i class="k-i k-i--sm i-down" aria-hidden="true"></i></div>
<div class="k-searchbox"><i class="k-i i-search" aria-hidden="true"></i><input class="k-input" type="search"></div>
<label class="k-check"><input type="checkbox">Libellé</label>
```
Erreur de champ : bordure `--danger`, `aria-invalid="true"`, message dans `.k-help` avec icône `i-warn`.

**Badges** : `<span class="k-badge cat-veg"><i class="k-i i-leaf"></i>Végétarien</span>`. Autres : `k-badge--admin`, `--member`, `--manual` (pointillés), `--today`. Compteur : `k-count` (`k-count--done` quand complet).

**Vignette de recette (sans photo)** : `<span class="k-thumb cat-fish"><i class="k-i i-fish"></i></span>` ; grande : `k-thumb--lg`.

**Ligne de courses (élément clé)** :
```html
<label class="k-row">
  <input class="k-row__input" type="checkbox" checked>
  <span class="k-box"><i class="k-i i-check" aria-hidden="true"></i></span>
  <span class="k-row__body">
    <span class="k-row__line"><b class="k-qty">800 g</b>tomates</span>
    <span class="k-meta">Pâtes tomate, Soupe</span>
  </span>
</label>
```
Toute la ligne est cliquable (l'input recouvre la ligne). Coché : case verte avec coche, texte barré et atténué. Les cochés descendent en bas du rayon (tri côté serveur ou JS).

**Rayon repliable** :
```html
<section class="k-aisle [is-closed] [is-done]">
  <button class="k-aisle__head" aria-expanded="true">
    <span class="k-aisle__icon"><i class="k-i i-apple"></i></span>
    <span class="k-aisle__name">Fruits &amp; légumes</span>
    <span class="k-count">2/6</span>
    <i class="k-i k-aisle__chev i-down"></i>
  </button>
  <div class="k-aisle__body">…lignes…</div>
</section>
```
Le JS bascule `is-closed` et `aria-expanded`, et `is-done` quand le compteur est complet.

**Progression** : `.k-progress` > `.k-progress__top` (`.k-progress__num` « 4 / 18 ») + `.k-progress__track` > `.k-progress__fill` (largeur en %). Mettre `role="progressbar"` et `aria-valuenow/max` sur la piste. Coller en haut de l'écran Courses (`position: sticky; top: 0`).

**Bandeaux** : `.k-banner` (info) + `--ok | --warn | --err | --off`, structure `icône + .k-banner__body (<b>titre</b>texte) + .k-banner__act` optionnel. `role="status"` ; `role="alert"` pour une erreur.

**Dépliants** : `<details class="k-details"><summary>…<i class="k-i i-down"></i></summary><div class="k-details__body">…</div></details>` (l'icône chevron doit être le dernier enfant de `summary`).

**Navigation** : mobile `nav.k-tabbar` avec 5 `a.k-tab` dans l'ordre Semaine, Calendrier, **Courses** (`k-tab--cta`, centre, surélevé), Recettes, Foyer ; page courante `aria-current="page"`. Bureau `header.k-topbar` avec `nav.k-nav` + sélecteur de foyer + utilisateur + déconnexion. Prévoir `padding-bottom` ≥ 110 px sur le contenu mobile pour ne pas passer sous la barre.

**Semaine** : `.k-daycard` (+ `is-today`), `.k-daycard__head` > `h2.k-daycard__name`, repas `.k-meal` (`.k-meal__slot` avec icône `i-sun`/`i-moon`, `.k-meal__name`, `.k-meal__sub`), ligne d'ajout `.k-add`.

**États vides** : `.k-empty` (illustration, `h2`, `p`, bouton). Illustration : cocotte du logo sans la coche sur un rond `k-gingham`.

**Messages éphémères** : `.k-toast`. **Feuille d'ajout** : `.k-sheet`. **Carte** : `.k-card` (+ `k-card--pad`, `k-card--raised`).

## Écrans : ce qu'il faut reproduire

1. **Courses (mobile)** : en-tête (logo, « Courses », « Semaine du … », bouton ⋯) ; progression collée ; pastilles « Masquer les cochés » et « Écran allumé » ; bouton pointillé « Ajouter un article » (nom, quantité, rayon) ; un `k-aisle` par rayon, repliable, compteur ; bandeau d'avertissement « recettes sans ingrédients non incluses » ; bandeaux hors connexion et « liste modifiée — Actualiser » ; état vide « Rien au menu cette semaine » ; état « Tout est dans le chariot ! » (carte verte, rayons repliés en `is-done`). Menu ⋯ : copier, imprimer, tout décocher. **Mode en magasin** : plein écran, lignes de 84 px, case 42 px, un rayon à la fois, gros bouton « Rayon suivant ».
2. **Courses (bureau)** : rayons en deux colonnes (`column-width: 400px`), colonne latérale collante (progression, copier, tout décocher, formulaire d'ajout).
3. **Semaine** : titre « Semaine du 2 novembre », navigation ‹ Aujourd'hui ›, bouton Courses, panneau « Générer le menu » (puces de jours, Déjeuner/Dîner, éviter N semaines, végétarien minimum, remplacer l'existant), 7 cartes de jours, ajout de repas par jour, copier/vider/partager. Mobile : liste verticale + bande des 7 jours pour sauter à un jour.
4. **Recettes** : recherche en direct, filtre catégorie, tri, compteur « 9 / 83 », liste (★ favori en `--tomato`, vignette, nom, badge, ingrédients, planifiée, dernière fois, badge « manuelle »), dépliants import/export, fiche (ingrédients avec rayon, notes, formulaire « Planifier »), formulaire (aide de saisie des ingrédients).
5. **Calendrier** : grille Lun–Dim, pastilles Déjeuner/Dîner (3 max puis « +N »), aujourd'hui en beurre, clic → semaine. Mobile : points (anneau = déjeuner, plein = dîner) + détail du jour sous la grille.
6. **Foyer / Invitations / Profil** : membres (rôle, nommer admin, retirer), réglages, invitations (durée, usage unique, lien copiable, état actif/utilisé/expiré, désactiver), zone sensible (quitter, supprimer en saisissant le nom), Mes foyers, Profil.
7. **Entrée** : connexion, inscription (bandeau « vous rejoindrez le foyer X »), page d'invitation et ses états valable / expiré / désactivé / déjà utilisé. Bandeau vichy en tête.
8. **Partage public** : lecture seule, sans navigation, mise en page fluide (deux colonnes dès ~700 px), bandeau vichy, pied « Fait avec Kitcho ».
9. **Erreurs** 403 / 404 / 419 / 500 (gros code sur vichy, titre chaleureux, un bouton) et messages succès / info / erreur.

## Comportements à implémenter en JS (minimal, sans framework)

- Coche : bascule l'input, met à jour compteurs de rayon et progression, déplace la ligne en bas du rayon, vibration courte : `navigator.vibrate && navigator.vibrate(12)`. Sauvegarde côté serveur (et file d'attente hors ligne si le réseau manque).
- « Écran allumé » : `navigator.wakeLock.request('screen')` si disponible.
- « Masquer les cochés » : classe ou attribut sur le conteneur, rayons entièrement cochés masqués.
- Rayon repliable : `is-closed` + `aria-expanded`.
- Respecter `prefers-reduced-motion` (déjà géré dans le CSS).

## Impression de la liste de courses

`kitcho.css` contient déjà un `@media print` : masque navigation et boutons, aplatit les rayons, cases carrées vides. Mettre `k-noprint` sur tout élément à cacher à l'impression.

## Règles de conformité

- Utiliser les variables, jamais de valeurs en dur.
- Un seul `h1` par page, en serif (déjà le cas pour `h1–h4` dans `.k-app`).
- Chaque contrôle est un vrai `<button>`, `<a>`, `<input>` avec `<label>`.
- Ne pas ajouter de dépendance, ni de police ou d'icône chargée depuis Internet.
- Test final : contraste en clair et en sombre, tabulation au clavier, largeurs 390 / 768 / 1100 px.

## Fichiers fournis

- `kitcho.css` : jetons, icônes, composants, impression. Une seule retouche par rapport à la livraison d'origine : la règle de couleur des liens est écrite `:where(.k-app) a{…}` (au lieu de `.k-app a{…}`) pour ne plus écraser la couleur des `<a class="k-btn…">`, `k-tab`, `k-logo`.
- Les maquettes (26 planches, clair et sombre) restent la référence visuelle dans le canvas de design.
