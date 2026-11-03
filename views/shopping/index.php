<?php
$d = ymd($monday);
$prev = $monday->modify('-7 days');
$next = $monday->modify('+7 days');
$total = (int) $list['total'];
$done = (int) $list['done'];
$pct = $total ? (int) round($done * 100 / $total) : 0;
$complete = $total > 0 && $done === $total;
?>
<div class="page-head k-noprint">
    <div>
        <h1 class="page-title">Courses</h1>
        <p class="k-muted">Semaine du <?= e(fmt_week_range($monday)) ?></p>
    </div>
    <div class="page-head__actions">
        <a class="k-iconbtn k-iconbtn--tint" href="<?= e(url('/shopping', ['d' => ymd($prev)])) ?>" aria-label="Semaine précédente"><?= icon('left') ?></a>
        <a class="k-btn k-btn--secondary k-btn--sm" href="<?= e(url('/shopping')) ?>">Cette semaine</a>
        <a class="k-iconbtn k-iconbtn--tint" href="<?= e(url('/shopping', ['d' => ymd($next)])) ?>" aria-label="Semaine suivante"><?= icon('right') ?></a>
        <details class="more">
            <summary class="k-iconbtn k-iconbtn--tint" aria-label="Plus d'actions"><?= icon('more') ?></summary>
            <div class="more-menu k-card k-card--raised">
                <button type="button" class="k-btn k-btn--ghost k-btn--block more-item" data-copy-text="<?= e($text) ?>"><?= icon('copy') ?>Copier la liste</button>
                <button type="button" class="k-btn k-btn--ghost k-btn--block more-item" onclick="window.print()"><?= icon('print') ?>Imprimer</button>
                <form method="post" action="<?= e(url('/shopping/reset')) ?>" data-confirm="Tout décocher ?">
                    <?= csrf_field() ?><input type="hidden" name="d" value="<?= e($d) ?>">
                    <button class="k-btn k-btn--ghost k-btn--block more-item"><?= icon('refresh') ?>Tout décocher</button>
                </form>
            </div>
        </details>
    </div>
</div>
<h1 class="print-only"><?= e($title) ?></h1>

<div class="shop" data-week="<?= e($d) ?>" data-state-url="<?= e(url('/shopping/state', ['d' => $d])) ?>"
     data-toggle-url="<?= e(url('/shopping/toggle')) ?>" data-extra-del-url="<?= e(url('/shopping/extra/delete')) ?>">
<div class="shop-grid">

    <div class="shop-side k-noprint">
        <div class="k-progress shop-progress">
            <div class="k-progress__top">
                <span class="k-progress__num"><span data-done><?= $done ?></span><small>/ <span data-total><?= $total ?></span></small></span>
                <span class="k-meta">articles cochés</span>
            </div>
            <div class="k-progress__track" role="progressbar" aria-label="Avancement des courses" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>">
                <div class="k-progress__fill" data-progress-fill style="width: <?= $pct ?>%"></div>
            </div>
        </div>

        <div class="shop-tools">
            <button type="button" class="k-pill" data-toggle-done aria-pressed="false"><?= icon('check', 'sm', 'pill-check') ?><?= icon('eyeoff', 'sm') ?><span>Masquer les cochés</span></button>
            <button type="button" class="k-pill" data-wakelock hidden aria-pressed="false"><?= icon('check', 'sm', 'pill-check') ?><?= icon('sun', 'sm') ?><span>Écran allumé</span></button>
            <?php if ($total): ?>
                <button type="button" class="k-pill" data-store-mode><?= icon('basket', 'sm') ?><span>Mode magasin</span></button>
            <?php endif; ?>
        </div>

        <details class="k-details add-item">
            <summary><?= icon('plus') ?>Ajouter un article<?= icon('down') ?></summary>
            <div class="k-details__body">
                <form method="post" action="<?= e(url('/shopping/extra')) ?>" class="stack">
                    <?= csrf_field() ?><input type="hidden" name="d" value="<?= e($d) ?>">
                    <div class="k-field"><label class="k-label" for="x-name">Article</label>
                        <input class="k-input" id="x-name" name="name" required maxlength="150" placeholder="Papier toilette" autocomplete="off"></div>
                    <div class="row-form">
                        <div class="k-field"><label class="k-label" for="x-qty">Quantité</label>
                            <input class="k-input" id="x-qty" name="quantity" maxlength="50" placeholder="2" autocomplete="off"></div>
                        <div class="k-field grow"><label class="k-label" for="x-aisle">Rayon</label>
                            <span class="k-selectwrap"><select class="k-select" id="x-aisle" name="aisle"><option value="">Automatique</option>
                                <?php foreach (AISLES as $a): ?><option><?= e($a) ?></option><?php endforeach; ?></select><?= icon('down', 'sm') ?></span></div>
                    </div>
                    <button class="k-btn k-btn--primary k-btn--block"><?= icon('plus') ?>Ajouter</button>
                </form>
            </div>
        </details>
    </div>

    <div class="shop-alerts k-noprint">
        <div class="k-banner k-banner--off" data-offline role="status" hidden>
            <?= icon('wifioff') ?><div class="k-banner__body"><b>Hors connexion</b>Vos coches sont conservées et seront envoyées dès le retour du réseau.</div>
        </div>
        <div class="k-banner k-banner--err" data-expired role="alert" hidden>
            <?= icon('lock') ?><div class="k-banner__body"><b>Session expirée</b>Vos coches sont conservées sur cet appareil. Reconnectez-vous pour les envoyer.</div>
            <button type="button" class="k-banner__act" onclick="location.reload()">Se reconnecter</button>
        </div>
        <div class="k-banner" data-changed role="status" hidden>
            <?= icon('info') ?><div class="k-banner__body"><b>Liste modifiée</b>Quelqu'un d'autre a changé la liste.</div>
            <button type="button" class="k-banner__act" onclick="location.reload()">Actualiser</button>
        </div>
        <div class="k-banner k-banner--ok all-done" data-alldone role="status" <?= $complete ? '' : 'hidden' ?>>
            <?= icon('check') ?><div class="k-banner__body"><b>Tout est dans le chariot !</b>Bonnes courses terminées.</div>
        </div>
    </div>

    <div class="shop-main">
    <?php if (!$meals && !$total): ?>
        <section class="k-card k-empty">
            <?= empty_art() ?>
            <h2>Rien au menu cette semaine</h2>
            <p>Planifiez quelques repas : la liste de courses se remplit toute seule.</p>
            <a class="k-btn k-btn--primary" href="<?= e(url('/week', ['d' => $d])) ?>">Planifier les repas</a>
        </section>
    <?php endif; ?>

    <?php foreach ($list['groups'] as $aisle => $items):
        $aisleDone = count(array_filter($items, fn($i) => $i['checked']));
        $aisleComplete = $aisleDone === count($items);
    ?>
        <section class="k-aisle<?= $aisleComplete ? ' is-done' : '' ?>" data-aisle>
            <button type="button" class="k-aisle__head" aria-expanded="true">
                <span class="k-aisle__icon"><?= icon(aisle_icon($aisle)) ?></span>
                <h2 class="k-aisle__name"><?= e($aisle) ?></h2>
                <span class="k-count<?= $aisleComplete ? ' k-count--done' : '' ?>"><span data-aisle-done><?= $aisleDone ?></span>/<span data-aisle-total><?= count($items) ?></span></span>
                <?= icon('down', '', 'k-aisle__chev') ?>
            </button>
            <div class="k-aisle__body">
                <?php foreach ($items as $it):
                    $q = item_quantity_text($it);
                    $id = 'it-' . $it['key'];
                    $tag = $it['extra_id'] ? 'div' : 'label';
                ?>
                    <<?= $tag ?> class="k-row" data-key="<?= e($it['key']) ?>" data-name="<?= e(norm_key($it['name'])) ?>"<?= $it['extra_id'] ? ' data-extra="' . (int) $it['extra_id'] . '"' : '' ?>>
                        <input class="k-row__input" type="checkbox" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-t" <?= $it['checked'] ? 'checked' : '' ?>>
                        <span class="k-box"><?= icon('check') ?></span>
                        <span class="k-row__body" id="<?= e($id) ?>-t">
                            <span class="k-row__line"><?php if ($q !== ''): ?><b class="k-qty"><?= e($q) ?></b><?php endif; ?><?= e($it['name']) ?></span>
                            <?php if ($it['from']): ?><span class="k-meta"><?= e(implode(', ', $it['from'])) ?></span><?php endif; ?>
                        </span>
                        <?php if ($it['extra_id']): ?>
                            <button type="button" class="k-iconbtn row-del k-noprint" data-extra-del="<?= (int) $it['extra_id'] ?>" aria-label="Supprimer <?= e($it['name']) ?>"><?= icon('trash') ?></button>
                        <?php endif; ?>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    </div>
    <div class="shop-notes k-noprint">
        <?php if ($list['missing']): ?>
            <div class="k-banner k-banner--warn" role="status">
                <?= icon('warn') ?><div class="k-banner__body"><b>Recettes sans ingrédients, non incluses</b>
                    <?= e(implode(', ', $list['missing'])) ?>. Complétez-les dans les recettes pour qu'elles apparaissent ici.</div>
            </div>
        <?php endif; ?>
        <?php if ($list['free']): ?>
            <div class="k-banner" role="status">
                <?= icon('info') ?><div class="k-banner__body"><b>Repas libres, sans liste</b><?= e(implode(', ', $list['free'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="store-bar k-noprint" hidden>
    <button type="button" class="k-btn k-btn--secondary" data-store-prev aria-label="Rayon précédent"><?= icon('left') ?></button>
    <button type="button" class="k-btn k-btn--primary grow" data-store-next><span data-store-next-label>Rayon suivant</span><?= icon('right') ?></button>
    <button type="button" class="k-btn k-btn--ghost" data-store-exit>Quitter</button>
</div>
</div>
