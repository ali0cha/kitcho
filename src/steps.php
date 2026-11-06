<?php
declare(strict_types=1);

const MAX_STEPS = 100;

/** Retire un numéro ou une puce en tête d'étape : « 1. », « 2) », « Étape 3 : », « - ». */
function strip_step_numbering(string $text): string
{
    return trim((string) preg_replace('/^(?:[-*•–]\s+|(?:étape\s*)?\d{1,2}\s*[.):]\s+|étape\s*\d{1,2}\s*[-–]?\s*)/iu', '', trim($text)));
}

/**
 * Texte saisi → liste d'étapes. Une étape = un paragraphe (séparés par une ligne vide). Sans ligne vide, chaque
 * ligne est une étape. Une ligne « # Pour la pâte » est un intertitre. Les numéros (« 1. », « Étape 2 : »)
 * et les puces sont retirés : la numérotation est refaite à l'affichage.
 *
 * @return array<int, array{kind: string, text: string}>
 */
function parse_steps(string $text): array
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
        return [];
    }
    $blocks = preg_split('/\n\s*\n/u', $text);
    if (count($blocks) === 1 && str_contains($blocks[0], "\n")) {
        $blocks = explode("\n", $blocks[0]);
    }
    $out = [];
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        if (!str_contains($block, "\n") && preg_match('/^#{1,6}\s*(.+?)\s*:?$/u', $block, $m)) {
            $out[] = ['kind' => 'heading', 'text' => mb_substr($m[1], 0, 150)];
            continue;
        }
        $block = strip_step_numbering($block);
        $lines = array_filter(array_map('trim', explode("\n", $block)), fn($l) => $l !== '');
        $block = implode("\n", $lines);
        if ($block !== '') {
            $out[] = ['kind' => 'step', 'text' => mb_substr($block, 0, 4000)];
        }
        if (count($out) >= MAX_STEPS) {
            break;
        }
    }
    return $out;
}

/** Inverse de parse_steps, pour pré-remplir le formulaire. */
function steps_to_text(array $rows): string
{
    $parts = [];
    foreach ($rows as $r) {
        $parts[] = $r['kind'] === 'heading' ? '# ' . $r['text'] : $r['text'];
    }
    return implode("\n\n", $parts);
}

function recipe_steps(int $recipeId): array
{
    return db_all('SELECT kind, text FROM recipe_steps WHERE recipe_id = ? ORDER BY position, id', [$recipeId]);
}

/** Remplace les étapes d'une recette par celles du texte ; retourne le nombre d'étapes (hors intertitres). */
function save_recipe_steps(int $recipeId, string|array $steps): int
{
    $rows = is_array($steps) ? $steps : parse_steps($steps);
    db_run('DELETE FROM recipe_steps WHERE recipe_id = ?', [$recipeId]);
    $n = 0;
    foreach (array_slice($rows, 0, MAX_STEPS) as $i => $r) {
        $kind = ($r['kind'] ?? 'step') === 'heading' ? 'heading' : 'step';
        $text = trim((string) ($r['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        db_insert('recipe_steps', ['recipe_id' => $recipeId, 'position' => $i, 'kind' => $kind, 'text' => mb_substr($text, 0, 4000)]);
        $n += $kind === 'step' ? 1 : 0;
    }
    return $n;
}

/** 45 → « 45 min », 90 → « 1 h 30 », 120 → « 2 h ». */
function format_minutes(int $min): string
{
    if ($min < 60) {
        return $min . ' min';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $h . ' h' . ($m ? ' ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
}

/** Temps total : celui renseigné, sinon préparation + cuisson. */
function recipe_total_minutes(array $r): ?int
{
    if (!empty($r['total_minutes'])) {
        return (int) $r['total_minutes'];
    }
    $sum = (int) ($r['prep_minutes'] ?? 0) + (int) ($r['cook_minutes'] ?? 0);
    return $sum > 0 ? $sum : null;
}

/** Entier de minutes (0 à 3 jours) depuis un champ de formulaire, null si vide. */
function minutes_input(string $key): ?int
{
    $v = input($key);
    if (!is_string($v) || trim($v) === '' || !preg_match('/^\d{1,4}$/', trim($v))) {
        return null;
    }
    $n = (int) trim($v);
    return $n > 0 && $n <= 4320 ? $n : null;
}

/** Étapes venant d'un fichier ou d'un import : liste de textes ou de {kind, text}, nettoyée. */
function sanitize_steps(mixed $in): array
{
    if (!is_array($in)) {
        return [];
    }
    $out = [];
    foreach ($in as $st) {
        if (is_string($st)) {
            $st = ['kind' => 'step', 'text' => $st];
        }
        if (!is_array($st) || !is_string($st['text'] ?? null) || trim($st['text']) === '') {
            continue;
        }
        $out[] = ['kind' => ($st['kind'] ?? 'step') === 'heading' ? 'heading' : 'step', 'text' => mb_substr(trim($st['text']), 0, 4000)];
        if (count($out) >= MAX_STEPS) {
            break;
        }
    }
    return $out;
}

function minutes_value(mixed $v): ?int
{
    return is_numeric($v) && (int) $v > 0 && (int) $v <= 4320 ? (int) $v : null;
}
