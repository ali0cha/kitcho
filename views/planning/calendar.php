<?php $todayStr = ymd(today()); ?>
<div class="page-head">
    <h1 class="page-title"><?= e(ucfirst(month_name($first))) ?> <?= e($first->format('Y')) ?></h1>
    <div class="page-head__actions">
        <a class="k-iconbtn k-iconbtn--tint" href="<?= e(url('/calendar', ['m' => $prev])) ?>" aria-label="Mois précédent"><?= icon('left') ?></a>
        <a class="k-btn k-btn--secondary k-btn--sm" href="<?= e(url('/calendar')) ?>">Aujourd'hui</a>
        <a class="k-iconbtn k-iconbtn--tint" href="<?= e(url('/calendar', ['m' => $next])) ?>" aria-label="Mois suivant"><?= icon('right') ?></a>
    </div>
</div>

<div class="cal" data-cal>
    <?php foreach (FR_DAYS as $dn): ?><div class="cal-head"><?= e(ucfirst(mb_substr($dn, 0, 3))) ?><span class="hide-sm">.</span></div><?php endforeach; ?>
    <?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')):
        $ds = ymd($d);
        $outside = $d->format('Y-m') !== $first->format('Y-m');
        $dm = $byDate[$ds] ?? [];
    ?>
        <a class="cal-day<?= $outside ? ' is-outside' : '' ?><?= $ds === $todayStr ? ' is-today' : '' ?>" href="<?= e(week_url($d)) ?>" data-date="<?= e($ds) ?>"
           aria-label="<?= e(fmt_day($d)) ?>, <?= count($dm) ?> <?= plural(count($dm), 'repas', 'repas') ?>">
            <span class="cal-num k-num"><?= e($d->format('j')) ?></span>
            <span class="cal-meals">
                <?php foreach (array_slice($dm, 0, 3) as $m): ?>
                    <span class="cal-meal is-<?= e($m['slot']) ?>" title="<?= e(SLOTS[$m['slot']] . ' : ' . $m['name']) ?>"><?= icon($m['slot'] === 'lunch' ? 'sun' : 'moon', 'sm') ?><span><?= e($m['name']) ?></span></span>
                <?php endforeach; ?>
                <?php if (count($dm) > 3): ?><span class="k-meta">+<?= count($dm) - 3 ?></span><?php endif; ?>
            </span>
            <span class="cal-dots" aria-hidden="true">
                <?php foreach (array_slice($dm, 0, 4) as $m): ?><i class="cal-dot is-<?= e($m['slot']) ?>"></i><?php endforeach; ?>
            </span>
        </a>
    <?php endfor; ?>
</div>

<p class="cal-legend k-meta"><span><i class="cal-dot is-lunch"></i> Déjeuner</span> <span><i class="cal-dot is-dinner"></i> Dîner</span></p>

<details class="k-details sub-panel k-noprint" id="abonnement" <?= input('abo') ? 'open' : '' ?>>
    <summary><?= icon('link') ?>S'abonner au calendrier (ICS)<?= icon('down') ?></summary>
    <div class="k-details__body stack">
        <p class="k-help">Retrouvez les repas du foyer dans votre agenda habituel (téléphone, Google, Apple, Outlook, Thunderbird…). L'agenda se met à jour tout seul : chaque dîner (19 h) et déjeuner (12 h) apparaît avec le nombre de personnes, les notes, le lien de la recette et les ingrédients. Les repas ne bloquent pas votre disponibilité.</p>
        <?php if ($feed): ?>
            <div class="k-field"><label class="k-label" for="feed-link">Lien d'abonnement</label>
                <div class="row-form">
                    <input class="k-input copy-input grow" id="feed-link" readonly value="<?= e($feed['link']) ?>">
                    <button type="button" class="k-btn k-btn--secondary" data-copy="<?= e($feed['link']) ?>"><?= icon('copy') ?>Copier</button>
                </div></div>
            <p><a class="k-btn k-btn--primary" href="<?= e($feed['webcal']) ?>"><?= icon('month') ?>S'abonner depuis cet appareil</a></p>
            <div class="k-banner k-banner--warn" role="note"><?= icon('lock') ?><div class="k-banner__body"><b>Lien secret</b>Toute personne qui possède ce lien peut lire les menus du foyer. Ne le publiez pas ; régénérez-le s'il a fuité.</div></div>
            <ul class="plain pwa-steps">
                <li><b>iPhone / iPad / Mac</b> : touchez « S'abonner depuis cet appareil », ou Réglages → Calendrier → Comptes → Ajouter un compte → Autre → Ajouter un calendrier avec abonnement.</li>
                <li><b>Google Agenda</b> (ordinateur) : « Autres agendas » (+) → À partir de l'URL → collez le lien. Google actualise les agendas abonnés lentement (plusieurs heures).</li>
                <li><b>Android</b> : ajoutez le lien dans Google Agenda sur ordinateur ; il apparaît ensuite sur le téléphone.</li>
                <li><b>Outlook</b> : Ajouter un calendrier → S'abonner à partir du web.</li>
                <li><b>Thunderbird</b> : Nouveau calendrier → Sur le réseau → format iCalendar (ICS).</li>
            </ul>
            <div class="row-form">
                <form method="post" action="<?= e(url('/calendar/feed/create')) ?>" class="inline" data-confirm="Générer un nouveau lien ? L'ancien cessera de fonctionner pour tous vos calendriers abonnés.">
                    <?= csrf_field() ?><input type="hidden" name="m" value="<?= e($first->format('Y-m')) ?>">
                    <button class="k-btn k-btn--secondary"><?= icon('refresh') ?>Générer un nouveau lien</button>
                </form>
                <form method="post" action="<?= e(url('/calendar/feed/revoke')) ?>" class="inline" data-confirm="Désactiver l'abonnement ? Les calendriers abonnés ne seront plus mis à jour.">
                    <?= csrf_field() ?><input type="hidden" name="m" value="<?= e($first->format('Y-m')) ?>">
                    <button class="k-btn k-btn--danger-o"><?= icon('trash') ?>Désactiver</button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e(url('/calendar/feed/create')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="m" value="<?= e($first->format('Y-m')) ?>">
                <button class="k-btn k-btn--primary"><?= icon('link') ?>Créer le lien d'abonnement</button>
            </form>
        <?php endif; ?>
    </div>
</details>

<?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')): $ds = ymd($d); $dm = $byDate[$ds] ?? []; ?>
    <section class="cal-detail k-card k-card--pad" data-cal-detail="<?= e($ds) ?>" hidden>
        <h2><?= e(fmt_day($d)) ?></h2>
        <?php if ($dm): ?>
            <ul class="plain list-rows">
                <?php foreach ($dm as $m): ?>
                    <li><span class="k-meal__slot"><?= icon($m['slot'] === 'lunch' ? 'sun' : 'moon', 'sm') ?><?= e(SLOTS[$m['slot']]) ?></span> <b><?= e($m['name']) ?></b></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?><p class="k-muted">Rien de prévu ce jour-là.</p><?php endif; ?>
        <a class="k-btn k-btn--secondary k-btn--block" href="<?= e(week_url($d)) ?>">Ouvrir la semaine</a>
    </section>
<?php endfor; ?>

<p class="k-help cal-hint hide-lg">Touchez un jour pour voir son détail, puis ouvrez la semaine pour le modifier.</p>
