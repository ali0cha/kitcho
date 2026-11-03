<?php
declare(strict_types=1);

/** Heures des repas dans le calendrier (heure locale « flottante » : s'affiche pareil dans tous les fuseaux). */
const ICS_SLOT_TIMES = ['lunch' => ['12:00', '13:00'], 'dinner' => ['19:00', '20:00']];

function ics_escape(string $s): string
{
    return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\;", "\\,", "\\n", "\\n", "\\n"], $s);
}

/** Lignes de 75 octets maximum (RFC 5545), sans couper un caractère UTF-8 en deux. */
function ics_fold(string $line): string
{
    $out = '';
    $limit = 75;
    while (strlen($line) > $limit) {
        $cut = $limit;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
        $limit = 74;                     // la ligne de continuation commence par une espace
    }
    return $out . $line;
}

function ics_utc(string $dbDateTime): string
{
    return (new DateTimeImmutable($dbDateTime))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

/** Ingrédients d'un repas ramenés au nombre de personnes prévu, en lignes de texte. */
function ics_ingredients(array $ings, float $factor): array
{
    $lines = [];
    foreach ($ings as $i) {
        $q = $i['quantity'] !== null ? (float) $i['quantity'] * $factor : null;
        $lines[] = '• ' . ($q !== null ? trim(format_qty($q) . ' ' . unit_label($i['unit'] ?: null, $q)) . ' ' : '') . $i['name'];
    }
    return $lines;
}

/** Flux iCalendar d'un foyer : un événement par repas (déjeuner 12 h, dîner 19 h), de 90 jours en arrière à tout le futur. */
function build_calendar_feed(array $household): string
{
    $hid = (int) $household['id'];
    $default = (int) $household['default_servings'];
    $meals = db_all(
        'SELECT m.id, m.meal_date, m.slot, m.servings, m.notes, m.label, m.created_at, m.recipe_id,
                r.name AS recipe_name, r.url AS recipe_url, r.servings AS recipe_servings
           FROM meals m LEFT JOIN recipes r ON r.id = m.recipe_id
          WHERE m.household_id = ? AND m.meal_date >= ?
          ORDER BY m.meal_date, m.slot = "dinner", m.id LIMIT 3000',
        [$hid, date('Y-m-d', strtotime('-90 days'))]
    );
    $ingByRecipe = [];
    $ids = array_values(array_unique(array_filter(array_map(fn($m) => $m['recipe_id'], $meals))));
    if ($ids) {
        foreach (db_all('SELECT recipe_id, name, quantity, unit FROM recipe_ingredients WHERE recipe_id IN (' . in_clause($ids) . ') ORDER BY position', $ids) as $i) {
            $ingByRecipe[$i['recipe_id']][] = $i;
        }
    }

    $host = parse_url(abs_url('/'), PHP_URL_HOST) ?: 'kitcho';
    $app = (string) config('app.name', 'Kitcho');
    $tz = (string) config('app.timezone', 'Europe/Brussels');
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . $app . '//Menus//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
        'X-WR-CALNAME:' . ics_escape($app . ' — ' . $household['name']),
        'X-WR-CALDESC:' . ics_escape('Menus du foyer ' . $household['name']),
        'X-WR-TIMEZONE:' . $tz,
        'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H',
    ];
    foreach ($meals as $m) {
        [$start, $end] = ICS_SLOT_TIMES[$m['slot']] ?? ICS_SLOT_TIMES['dinner'];
        $day = str_replace('-', '', $m['meal_date']);
        $name = $m['recipe_name'] ?? $m['label'] ?? 'Repas';
        $desc = [];
        $persons = (int) ($m['servings'] ?: $default);
        $desc[] = $persons . ' ' . plural($persons, 'personne', 'personnes');
        if ($m['notes']) {
            $desc[] = 'Note : ' . $m['notes'];
        }
        if ($m['recipe_id']) {
            if ($u = safe_url($m['recipe_url'])) {
                $desc[] = 'Recette : ' . $u;
            }
            $ings = $ingByRecipe[$m['recipe_id']] ?? [];
            if ($ings) {
                $desc[] = '';
                $desc[] = 'Ingrédients pour ' . $persons . ' :';
                array_push($desc, ...ics_ingredients($ings, $persons / max(1, (int) $m['recipe_servings'])));
            }
        }
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:meal-' . $m['id'] . '@' . $host;
        $lines[] = 'DTSTAMP:' . ics_utc($m['created_at']);
        $lines[] = 'DTSTART:' . $day . 'T' . str_replace(':', '', $start) . '00';
        $lines[] = 'DTEND:' . $day . 'T' . str_replace(':', '', $end) . '00';
        $lines[] = 'SUMMARY:' . ics_escape(SLOTS[$m['slot']] . ' : ' . $name);
        $lines[] = 'DESCRIPTION:' . ics_escape(implode("\n", $desc));
        if ($m['recipe_id']) {
            $lines[] = 'URL:' . abs_url('/recipes/' . (int) $m['recipe_id']);
        }
        $lines[] = 'CATEGORIES:' . ics_escape(SLOTS[$m['slot']]);
        $lines[] = 'TRANSP:TRANSPARENT';          // n'occupe pas votre agenda : les repas ne bloquent pas vos réunions
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
}
