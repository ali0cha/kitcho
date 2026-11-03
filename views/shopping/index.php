<?php
$d = ymd($monday);
$prev = $monday->modify('-7 days');
$next = $monday->modify('+7 days');
$pct = $list['total'] ? (int) round($list['done'] * 100 / $list['total']) : 0;
?>
<div class="page-head no-print">
    <div>
        <h1>Liste de courses</h1>
        <p class="muted">Semaine du <?= e(fmt_week_range($monday)) ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('/shopping', ['d' => ymd($prev)])) ?>" aria-label="Semaine précédente">‹</a>
        <a class="btn" href="<?= e(url('/shopping')) ?>">Cette semaine</a>
        <a class="btn" href="<?= e(url('/shopping', ['d' => ymd($next)])) ?>" aria-label="Semaine suivante">›</a>
    </div>
</div>
<h1 class="print-only"><?= e($title) ?></h1>

<?php if (!$meals && !$list['total']): ?>
    <p class="notice">Aucun repas planifié cette semaine. <a href="<?= e(url('/week', ['d' => $d])) ?>">Planifier les repas</a> pour générer la liste.</p>
<?php endif; ?>

<?php if ($list['missing'] || $list['free']): ?>
    <div class="notice no-print small">
        <?php if ($list['missing']): ?>
            ⚠ Sans ingrédients (non inclus) : <?= e(implode(', ', $list['missing'])) ?>.
            <span class="muted">Complétez-les dans les recettes pour qu'ils apparaissent ici.</span><br>
        <?php endif; ?>
        <?php if ($list['free']): ?>
            Repas libres sans liste : <?= e(implode(', ', $list['free'])) ?>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="shop" data-week="<?= e($d) ?>" data-state-url="<?= e(url('/shopping/state', ['d' => $d])) ?>">

<div class="shop-sticky no-print">
    <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>">
        <div class="progress-fill" data-progress-fill style="width: <?= $pct ?>%"></div>
    </div>
    <span class="progress-label"><strong data-done><?= (int) $list['done'] ?></strong> / <span data-total><?= (int) $list['total'] ?></span></span>
</div>

<div class="sync-banner no-print" data-offline hidden>Hors connexion : vos coches sont conservées et seront envoyées dès que le réseau revient.</div>
<div class="sync-banner no-print" data-changed hidden>La liste a été modifiée par quelqu'un d'autre. <button type="button" class="btn-link" onclick="location.reload()">Actualiser</button></div>

<div class="toolbar no-print">
    <button type="button" class="btn btn-small" data-toggle-done aria-pressed="false">Masquer les cochés</button>
    <button type="button" class="btn btn-small" data-wakelock hidden aria-pressed="false">☀ Écran allumé</button>
    <details class="more">
        <summary class="btn btn-small">⋯ Plus</summary>
        <div class="more-menu">
            <button type="button" class="btn btn-small" data-copy-text="<?= e($text) ?>">Copier la liste</button>
            <button type="button" class="btn btn-small" onclick="window.print()">Imprimer</button>
            <form method="post" action="<?= e(url('/shopping/reset')) ?>" class="inline" data-confirm="Tout décocher ?">
                <?= csrf_field() ?><input type="hidden" name="d" value="<?= e($d) ?>">
                <button class="btn btn-small">Tout décocher</button>
            </form>
        </div>
    </details>
</div>

<details class="card no-print add-item">
    <summary><strong>+ Ajouter un article</strong></summary>
    <form method="post" action="<?= e(url('/shopping/extra')) ?>" class="row-form">
        <?= csrf_field() ?><input type="hidden" name="d" value="<?= e($d) ?>">
        <label class="grow">Article <input name="name" required maxlength="150" placeholder="Papier toilette" autocomplete="off"></label>
        <label>Quantité <input name="quantity" maxlength="50" placeholder="2" autocomplete="off"></label>
        <label>Rayon
            <select name="aisle"><option value="">Auto</option><?php foreach (AISLES as $a): ?><option><?= e($a) ?></option><?php endforeach; ?></select>
        </label>
        <button class="btn btn-primary">Ajouter</button>
    </form>
</details>

<div class="shopping">
<?php foreach ($list['groups'] as $aisle => $items):
    $aisleDone = count(array_filter($items, fn($i) => $i['checked']));
?>
    <details class="card aisle" open data-aisle>
        <summary>
            <h2><?= e($aisle) ?></h2>
            <span class="aisle-count"><b data-aisle-done><?= $aisleDone ?></b>/<span data-aisle-total><?= count($items) ?></span></span>
        </summary>
        <ul class="plain checklist">
            <?php foreach ($items as $it): $q = item_quantity_text($it); ?>
                <li class="<?= $it['checked'] ? 'done' : '' ?>" data-key="<?= e($it['key']) ?>" data-name="<?= e(norm_key($it['name'])) ?>">
                    <form method="post" action="<?= e(url('/shopping/toggle')) ?>" class="toggle">
                        <?= csrf_field() ?>
                        <input type="hidden" name="d" value="<?= e($d) ?>">
                        <input type="hidden" name="key" value="<?= e($it['key']) ?>">
                        <input type="hidden" name="checked" value="<?= $it['checked'] ? '0' : '1' ?>">
                        <button class="row-btn" aria-pressed="<?= $it['checked'] ? 'true' : 'false' ?>">
                            <span class="box" aria-hidden="true"><?= $it['checked'] ? '✓' : '' ?></span>
                            <span class="item-text">
                                <span class="item-main"><?php if ($q !== ''): ?><strong class="qty"><?= e($q) ?></strong> <?php endif; ?><?= e($it['name']) ?></span>
                                <?php if ($it['from']): ?><span class="from muted small"><?= e(implode(', ', $it['from'])) ?></span><?php endif; ?>
                            </span>
                        </button>
                    </form>
                    <?php if ($it['extra_id']): ?>
                        <form method="post" action="<?= e(url('/shopping/extra/delete')) ?>" class="inline no-print del" data-confirm="Supprimer « <?= e($it['name']) ?> » de la liste ?">
                            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e($d) ?>"><input type="hidden" name="id" value="<?= (int) $it['extra_id'] ?>">
                            <button class="icon-btn del-btn" aria-label="Supprimer <?= e($it['name']) ?>">✕</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
<?php endforeach; ?>
</div>

<p class="all-done muted center no-print" data-alldone <?= $list['total'] && $list['done'] === $list['total'] ? '' : 'hidden' ?>>✓ Tout est dans le chariot !</p>

</div>
