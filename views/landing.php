<?php
$app = (string) config('app.name', 'Kitcho');
$canRegister = (bool) config('app.allow_registration', true);
$cta = $canRegister ? ['Créer mon compte', url('/register')] : ['Se connecter', url('/login')];
$title = $app . ' — menus de la semaine et liste de courses partagée';
$desc = 'Planifiez vos repas de la semaine en un clic, obtenez la liste de courses automatiquement et partagez-la avec toute la famille. À cocher dans les rayons, même sans réseau.';
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FBF5EA" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#17110E" media="(prefers-color-scheme: dark)">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <link rel="canonical" href="<?= e(abs_url('/')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="<?= e($app) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:url" content="<?= e(abs_url('/')) ?>">
    <meta property="og:image" content="<?= e(abs_url('/assets/og-image.png')) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
    <meta name="apple-mobile-web-app-title" content="<?= e($app) ?>">
    <link rel="stylesheet" href="<?= e(asset('kitcho.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => $app, 'url' => abs_url('/'),
        'description' => $desc, 'applicationCategory' => 'LifestyleApplication', 'operatingSystem' => 'Web, Android, iOS', 'inLanguage' => 'fr',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body class="k-app lp" data-base="<?= e(base_path()) ?>" data-guest>

<header class="lp-top">
    <?= logo() ?>
    <nav class="lp-top__nav" aria-label="Accès">
        <a class="k-btn k-btn--ghost k-btn--sm" href="<?= e(url('/login')) ?>">Se connecter</a>
        <?php if ($canRegister): ?><a class="k-btn k-btn--primary k-btn--sm" href="<?= e(url('/register')) ?>">Créer un compte</a><?php endif; ?>
    </nav>
</header>

<main>
<!-- ============================ Héros ============================ -->
<section class="lp-hero">
    <div class="lp-hero__text">
        <p class="k-eyebrow">Le planning de repas des familles</p>
        <h1>Fini le « qu'est-ce qu'on mange ce soir ? »</h1>
        <p class="lp-lead"><?= e($app) ?> planifie vos repas de la semaine, fabrique la liste de courses toute seule et la partage avec tout le foyer. À cocher dans les rayons, même quand le réseau lâche.</p>
        <div class="lp-cta">
            <a class="k-btn k-btn--primary lp-btn" href="<?= e($cta[1]) ?>"><?= e($cta[0]) ?><?= icon('right', 'sm') ?></a>
            <?php if ($canRegister): ?><a class="k-btn k-btn--secondary lp-btn" href="<?= e(url('/login')) ?>">J'ai déjà un compte</a>
            <?php else: ?><a class="k-btn k-btn--secondary lp-btn" href="#comment">Voir comment ça marche</a><?php endif; ?>
        </div>
        <ul class="lp-assure plain">
            <li><?= icon('check', 'sm') ?>Sans publicité</li>
            <li><?= icon('check', 'sm') ?>Sans pistage</li>
            <li><?= icon('check', 'sm') ?>Sans abonnement</li>
        </ul>
        <?php if (!$canRegister): ?><p class="k-help">L'inscription se fait sur invitation. Vous avez reçu un lien ? Ouvrez-le pour rejoindre un foyer.</p><?php endif; ?>
    </div>

    <!-- Maquette vivante de la liste de courses (décorative) -->
    <div class="lp-hero__demo" role="img" aria-label="Aperçu de la liste de courses : articles rangés par rayon, avec une barre de progression, qui se cochent un à un.">
        <div class="phone" aria-hidden="true" data-demo>
            <div class="phone__notch"></div>
            <div class="phone__screen">
                <p class="phone__title k-display">Courses</p>
                <div class="k-progress">
                    <div class="k-progress__top"><span class="k-progress__num"><span data-demo-done>2</span><small>/ 6</small></span><span class="k-meta">articles cochés</span></div>
                    <div class="k-progress__track"><div class="k-progress__fill" data-demo-fill style="width: 33%"></div></div>
                </div>
                <section class="k-aisle">
                    <div class="k-aisle__head"><span class="k-aisle__icon"><?= icon('apple') ?></span><span class="k-aisle__name">Fruits &amp; légumes</span><span class="k-count">4</span></div>
                    <div class="k-aisle__body">
                        <?php foreach ([['800 g', 'tomates', 'Pâtes tomate, Soupe', true], ['6', 'oignons', 'Pâtes tomate, Quiche', true], ['2', 'courgettes', 'Gratin', false], ['1', 'citron', 'Soupe de potiron', false]] as [$q, $n, $from, $on]): ?>
                            <div class="k-row" data-demo-row>
                                <input class="k-row__input" type="checkbox" tabindex="-1" disabled <?= $on ? 'checked' : '' ?>>
                                <span class="k-box"><?= icon('check') ?></span>
                                <span class="k-row__body"><span class="k-row__line"><b class="k-qty"><?= e($q) ?></b><?= e($n) ?></span><span class="k-meta"><?= e($from) ?></span></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <section class="k-aisle">
                    <div class="k-aisle__head"><span class="k-aisle__icon"><?= icon('milk') ?></span><span class="k-aisle__name">Crèmerie</span><span class="k-count">2</span></div>
                    <div class="k-aisle__body">
                        <?php foreach ([['250 ml', 'crème', 'Pâtes tomate'], ['', 'parmesan', 'Pâtes tomate']] as [$q, $n, $from]): ?>
                            <div class="k-row" data-demo-row>
                                <input class="k-row__input" type="checkbox" tabindex="-1" disabled>
                                <span class="k-box"><?= icon('check') ?></span>
                                <span class="k-row__body"><span class="k-row__line"><?php if ($q !== ''): ?><b class="k-qty"><?= e($q) ?></b><?php endif; ?><?= e($n) ?></span><span class="k-meta"><?= e($from) ?></span></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>

<!-- ============================ Comment ça marche ============================ -->
<section class="lp-section" id="comment" aria-labelledby="comment-t">
    <p class="k-eyebrow">Comment ça marche</p>
    <h2 id="comment-t" class="lp-h2">Trois temps, et c'est réglé pour la semaine</h2>
    <ol class="lp-steps plain">
        <li>
            <span class="lp-steps__n">1</span>
            <span class="k-aisle__icon lp-ico"><?= icon('book', 'lg') ?></span>
            <h3>Rassemblez vos recettes</h3>
            <p>Collez une liste de plats ou créez vos recettes avec leurs ingrédients. Elles sont partagées avec tout le foyer.</p>
        </li>
        <li>
            <span class="lp-steps__n">2</span>
            <span class="k-aisle__icon lp-ico"><?= icon('spark', 'lg') ?></span>
            <h3>Générez le menu</h3>
            <p>Un clic remplit la semaine : plats variés, pas de répétition, des repas végétariens garantis. Un plat ne plaît pas ? ↻ en propose un autre.</p>
        </li>
        <li>
            <span class="lp-steps__n">3</span>
            <span class="k-aisle__icon lp-ico"><?= icon('basket', 'lg') ?></span>
            <h3>Faites les courses</h3>
            <p>La liste est prête : ingrédients additionnés, quantités adaptées au nombre de convives, rangés par rayon. Il ne reste qu'à cocher.</p>
        </li>
    </ol>
</section>

<!-- ============================ Aperçu de la semaine ============================ -->
<section class="lp-section lp-week" aria-labelledby="week-t">
    <p class="k-eyebrow">La semaine d'un coup d'œil</p>
    <h2 id="week-t" class="lp-h2">Votre menu, jour après jour</h2>
    <p class="lp-sub">Déjeuner ou dîner, nombre de personnes, notes : tout se règle en deux touches. Un champ unique avec suggestions suffit pour ajouter un repas.</p>
    <div class="lp-week__grid" aria-hidden="true">
        <?php foreach ([
            ['Lundi', '2 nov.', false, [['moon', 'Dîner', 'Pâtes à la crème', 'vegetarien', '4 pers.']]],
            ['Mardi', '3 nov.', true, [['sun', 'Déjeuner', 'Salade grecque', 'salade', ''], ['moon', 'Dîner', 'Tartare de saumon', 'poisson', '2 pers.']]],
            ['Mercredi', '4 nov.', false, [['moon', 'Dîner', 'Soupe de potiron', 'soupe', '4 pers.']]],
        ] as [$day, $date, $today, $meals]): ?>
            <section class="k-daycard<?= $today ? ' is-today' : '' ?>">
                <header class="k-daycard__head"><h3 class="k-daycard__name"><?= e($day) ?> <span class="k-muted"><?= e($date) ?></span></h3><?php if ($today): ?><span class="k-badge k-badge--today">Aujourd'hui</span><?php endif; ?></header>
                <?php foreach ($meals as [$ic, $slot, $name, $cat, $pers]): ?>
                    <article class="k-meal">
                        <?= thumb($cat) ?>
                        <div class="meal-main">
                            <span class="k-meal__slot"><?= icon($ic, 'sm') ?><?= e($slot) ?></span>
                            <span class="k-meal__name"><?= e($name) ?></span>
                            <?php if ($pers !== ''): ?><span class="k-meal__sub"><span><?= icon('user', 'sm') ?> <?= e($pers) ?></span></span><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================ Atouts ============================ -->
<section class="lp-section" id="atouts" aria-labelledby="atouts-t">
    <p class="k-eyebrow">Pourquoi <?= e($app) ?></p>
    <h2 id="atouts-t" class="lp-h2">Pensé pour le quotidien d'une famille</h2>
    <div class="lp-features">
        <?php foreach ([
            ['basket', 'Une liste de courses qui se remplit toute seule', "Les ingrédients de chaque recette sont additionnés (kg, g, cl, ml convertis) et ajustés au nombre de personnes de chaque repas."],
            ['check', 'Faite pour le magasin', "Grandes cases à cocher, mode magasin plein écran, écran qui reste allumé. Sans réseau, vos coches sont gardées puis envoyées au retour."],
            ['home', 'Un foyer, plusieurs cuisiniers', "Invitez votre famille par un simple lien : tout le monde voit et modifie le même menu, et la liste se met à jour chez chacun en temps réel."],
            ['month', 'Dans votre agenda', "Abonnez-vous au calendrier des repas depuis Google Agenda, Apple Calendrier ou Outlook : le dîner de ce soir s'affiche avec ses ingrédients."],
            ['spark', 'Des menus variés, sans effort', "Kitcho évite les plats récents, privilégie vos favoris et équilibre les catégories. Vous gardez la main : changez un plat d'un tap."],
            ['plus', 'Une vraie application', "Installez-la en plein écran avec son icône, en clair ou en sombre. Aucune publicité, aucun outil de suivi."],
        ] as [$ic, $t, $p]): ?>
            <article class="lp-feature k-card k-card--pad">
                <span class="k-aisle__icon lp-ico"><?= icon($ic, 'lg') ?></span>
                <h3><?= e($t) ?></h3>
                <p><?= e($p) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================ Questions ============================ -->
<section class="lp-section lp-faq" aria-labelledby="faq-t">
    <p class="k-eyebrow">Questions fréquentes</p>
    <h2 id="faq-t" class="lp-h2">Avant de vous lancer</h2>
    <?php foreach ([
        ["Comment invite-t-on les autres membres du foyer ?", "Depuis la page Foyer, on crée un lien d'invitation (valable 1, 7 ou 30 jours, à usage unique si on veut). La personne l'ouvre, crée son compte et rejoint le foyer."],
        ["Est-ce que ça marche sans réseau ?", "Oui pour ce qui compte en magasin : les pages déjà ouvertes (liste de courses, semaine) restent lisibles et les articles cochés hors connexion sont envoyés dès que le réseau revient."],
        ["Puis-je suivre mes repas dans mon agenda ?", "Oui. Un lien d'abonnement (ICS) propre à votre foyer fait apparaître dîners et déjeuners dans Google Agenda, Apple Calendrier, Outlook ou Thunderbird, avec le nombre de personnes et les ingrédients."],
        ["Et mes données ?", "Aucune publicité, aucun outil de suivi. Les mots de passe sont chiffrés, et seuls les membres de votre foyer voient vos recettes et vos menus."],
    ] as [$q, $a]): ?>
        <details class="k-details"><summary><?= e($q) ?><?= icon('down') ?></summary><div class="k-details__body"><p class="k-muted"><?= e($a) ?></p></div></details>
    <?php endforeach; ?>
</section>

<!-- ============================ Appel final ============================ -->
<section class="lp-final k-gingham">
    <?= icon('pot', 'xl', 'k-logo__mark') ?>
    <h2 class="lp-h2"><?= $canRegister ? 'Prêt à simplifier vos repas ?' : 'Déjà membre d\'un foyer ?' ?></h2>
    <p class="lp-sub"><?= $canRegister ? 'Créez votre compte en une minute, ajoutez vos premières recettes et laissez ' . e($app) . ' préparer la semaine.' : 'Connectez-vous pour retrouver votre menu et votre liste de courses.' ?></p>
    <div class="lp-cta lp-cta--center">
        <a class="k-btn k-btn--primary lp-btn" href="<?= e($cta[1]) ?>"><?= e($cta[0]) ?><?= icon('right', 'sm') ?></a>
        <?php if ($canRegister): ?><a class="k-btn k-btn--secondary lp-btn" href="<?= e(url('/login')) ?>">Se connecter</a><?php endif; ?>
    </div>
</section>
</main>

<footer class="lp-foot">
    <?= logo() ?>
    <p class="k-meta">Planifiez vos repas, partagez vos courses.</p>
    <nav class="lp-foot__nav" aria-label="Liens"><a href="<?= e(url('/login')) ?>">Connexion</a><?php if ($canRegister): ?><a href="<?= e(url('/register')) ?>">Inscription</a><?php endif; ?></nav>
</footer>
</body>
</html>
