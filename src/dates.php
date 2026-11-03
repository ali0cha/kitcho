<?php
declare(strict_types=1);

const FR_DAYS   = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const FR_MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function parse_date(?string $s): ?DateTimeImmutable
{
    if ($s === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s ? $d : null;
}

function today(): DateTimeImmutable
{
    return new DateTimeImmutable('today');
}

function monday_of(DateTimeImmutable $d): DateTimeImmutable
{
    return $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
}

/** @return DateTimeImmutable[] les 7 jours de la semaine commençant à $monday */
function week_days(DateTimeImmutable $monday): array
{
    $out = [];
    for ($i = 0; $i < 7; $i++) {
        $out[] = $monday->modify("+$i days");
    }
    return $out;
}

function day_name(DateTimeImmutable $d): string
{
    return FR_DAYS[(int) $d->format('N') - 1];
}

function month_name(DateTimeImmutable $d): string
{
    return FR_MONTHS[(int) $d->format('n') - 1];
}

/** Mois abrégé à la française : janv., févr., mars, avr., mai, juin, juil., août, sept., oct., nov., déc. */
function month_abbr(DateTimeImmutable $d): string
{
    return ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int) $d->format('n') - 1];
}

function fmt_day(DateTimeImmutable $d): string
{
    return ucfirst(day_name($d)) . ' ' . $d->format('j') . ' ' . month_name($d);
}

function fmt_week_range(DateTimeImmutable $monday): string
{
    $end = $monday->modify('+6 days');
    if ($monday->format('n') === $end->format('n')) {
        return $monday->format('j') . ' – ' . $end->format('j') . ' ' . month_name($end) . ' ' . $end->format('Y');
    }
    return $monday->format('j') . ' ' . month_name($monday) . ' – ' . $end->format('j') . ' ' . month_name($end) . ' ' . $end->format('Y');
}

function ymd(DateTimeImmutable $d): string
{
    return $d->format('Y-m-d');
}

/** Date demandée (?d=) ramenée au lundi de sa semaine ; aujourd'hui par défaut. */
function requested_week(): DateTimeImmutable
{
    return monday_of(parse_date(input_str('d')) ?? today());
}
