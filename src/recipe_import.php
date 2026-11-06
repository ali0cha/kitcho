<?php
declare(strict_types=1);

/**
 * Import d'une recette depuis une adresse web, dans l'esprit de Mealie :
 *   1. données structurées schema.org « Recipe » (JSON-LD), présentes sur la grande majorité des sites de cuisine ;
 *   2. à défaut, microdonnées (itemprop) ;
 *   3. à défaut, titre / description OpenGraph (recette « vide » à compléter à la main).
 * Le contenu est nettoyé pour le français : entités HTML, numéros d'étapes, quantités (« 1 ½ c. à soupe »),
 * durées ISO 8601, portions (« Pour 4 personnes »).
 *
 * Le téléchargement est protégé : seules les adresses http(s) publiques sont autorisées (jamais le réseau interne),
 * chaque redirection est revérifiée, la taille et la durée sont plafonnées.
 */
final class ImportException extends RuntimeException
{
}

const IMPORT_MAX_BYTES = 3_000_000;
const IMPORT_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0 Kitcho/import';

// ---------------------------------------------------------------- réseau : adresses autorisées

function import_ip_in_cidr(string $ip, string $net, int $bits): bool
{
    $a = @inet_pton($ip);
    $b = @inet_pton($net);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    if ($bytes && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
        return false;
    }
    $rem = $bits % 8;
    if ($rem) {
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }
    return true;
}

/** Vrai si l'adresse IP est publique (ni locale, ni privée, ni réservée). */
function import_ip_is_public(string $ip): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    if (strlen($bin) === 16 && str_starts_with($bin, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {   // ::ffff:a.b.c.d
        $ip = (string) inet_ntop(substr($bin, 12));
        $bin = substr($bin, 12);
    }
    $blocked = strlen($bin) === 4
        ? [['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8], ['169.254.0.0', 16], ['172.16.0.0', 12],
           ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24],
           ['224.0.0.0', 4], ['240.0.0.0', 4]]
        : [['::', 128], ['::1', 128], ['fc00::', 7], ['fe80::', 10], ['ff00::', 8], ['2001:db8::', 32], ['64:ff9b::', 96], ['100::', 64]];
    foreach ($blocked as [$net, $bits]) {
        if (import_ip_in_cidr($ip, $net, $bits)) {
            return false;
        }
    }
    return true;
}

/** @return string[] adresses IP du nom d'hôte, toutes publiques (sinon exception) */
function import_public_ips(string $host): array
{
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        $ips = [];
        foreach ((array) @dns_get_record($host, DNS_A | DNS_AAAA) as $r) {
            $ips[] = $r['ip'] ?? ($r['ipv6'] ?? null);
        }
        if (!$ips) {
            $ips = (array) @gethostbynamel($host);
        }
        $ips = array_values(array_filter($ips));
    }
    if (!$ips) {
        throw new ImportException("Impossible de trouver le site « $host ». Vérifiez l'adresse.");
    }
    foreach ($ips as $ip) {
        if (!import_ip_is_public($ip)) {
            throw new ImportException("Cette adresse n'est pas autorisée (réseau interne).");
        }
    }
    return $ips;
}

/** Nettoie et valide l'adresse saisie. @return array{url:string, scheme:string, host:string, port:int} */
function import_validate_url(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '' || mb_strlen($raw) > 2000) {
        throw new ImportException('Collez l\'adresse de la recette (https://…).');
    }
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)) {
        $raw = 'https://' . ltrim($raw, '/');
    }
    $p = parse_url($raw);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
        throw new ImportException('Adresse invalide : elle doit commencer par http:// ou https://.');
    }
    if (isset($p['user']) || isset($p['pass'])) {
        throw new ImportException('Adresse invalide.');
    }
    $scheme = strtolower($p['scheme']);
    $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
    if (!in_array($port, [80, 443], true)) {
        throw new ImportException('Port non autorisé : seules les adresses web classiques sont acceptées.');
    }
    $host = strtolower(trim((string) $p['host'], '[]'));
    if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $host)) {
        $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
    }
    if (!filter_var($host, FILTER_VALIDATE_IP) && !str_contains($host, '.')) {
        throw new ImportException('Adresse invalide.');
    }
    $url = $scheme . '://' . $host . ($port !== ($scheme === 'https' ? 443 : 80) ? ':' . $port : '') . ($p['path'] ?? '/')
         . (isset($p['query']) ? '?' . $p['query'] : '');
    return ['url' => $url, 'scheme' => $scheme, 'host' => $host, 'port' => $port];
}

/** Retire les paramètres de suivi (utm_*, fbclid…) d'un lien pour l'enregistrer proprement. */
function import_clean_link(string $url): string
{
    $p = parse_url($url);
    if (!$p || !isset($p['query'])) {
        return preg_replace('/#.*$/', '', $url);
    }
    parse_str($p['query'], $q);
    foreach (array_keys($q) as $k) {
        if (preg_match('/^(utm_|mc_|sfmc|fbclid$|gclid$|igshid$|msclkid$)/i', (string) $k)) {
            unset($q[$k]);
        }
    }
    $base = preg_replace('/[?#].*$/', '', $url);
    return $base . ($q ? '?' . http_build_query($q) : '');
}

// ---------------------------------------------------------------- réseau : téléchargement

/**
 * Télécharge une page. Les redirections sont suivies à la main (5 max) et chaque étape est revalidée ;
 * l'adresse IP vérifiée est « épinglée » pour la connexion (pas de changement entre la vérification et l'accès).
 *
 * @return array{html: string, url: string}
 */
function import_fetch(string $rawUrl): array
{
    if (!function_exists('curl_init')) {
        throw new ImportException("L'import par lien nécessite l'extension PHP cURL sur ce serveur.");
    }
    $current = $rawUrl;
    $deadline = microtime(true) + 25;                 // durée totale maximale, redirections comprises
    @set_time_limit(45);
    for ($hop = 0; $hop <= 5; $hop++) {
        if (microtime(true) > $deadline) {
            throw new ImportException('Le site met trop de temps à répondre. Réessayez dans un instant.');
        }
        $u = import_validate_url($current);
        $ips = import_public_ips($u['host']);
        $ip = $ips[0];
        foreach ($ips as $candidate) {                      // préférence IPv4 (plus souvent joignable)
            if (!str_contains($candidate, ':')) {
                $ip = $candidate;
                break;
            }
        }
        $body = '';
        $type = '';
        $location = '';
        $ch = curl_init($u['url']);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE        => [$u['host'] . ':' . $u['port'] . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT        => max(3, (int) min(15, $deadline - microtime(true))),
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => IMPORT_USER_AGENT,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.5', 'Accept-Language: fr-FR,fr;q=0.9,en;q=0.5'],
            CURLOPT_HEADERFUNCTION => function ($c, string $h) use (&$type, &$location): int {
                if (stripos($h, 'HTTP/') === 0) {
                    $type = $location = '';
                } elseif (stripos($h, 'content-type:') === 0) {
                    $type = strtolower(trim(substr($h, 13)));
                } elseif (stripos($h, 'location:') === 0) {
                    $location = trim(substr($h, 9));
                }
                return strlen($h);
            },
            CURLOPT_WRITEFUNCTION  => function ($c, string $d) use (&$body): int {
                $body .= $d;
                return strlen($body) > IMPORT_MAX_BYTES ? -1 : strlen($d);
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($code >= 300 && $code < 400 && $location !== '') {
            $current = import_absolute_url($location, $u['url']);
            continue;
        }
        if ($errno === CURLE_WRITE_ERROR && strlen($body) > IMPORT_MAX_BYTES) {
            throw new ImportException('Cette page est trop volumineuse pour être importée.');
        }
        if ($ok === false) {
            throw new ImportException($errno === CURLE_OPERATION_TIMEDOUT
                ? 'Le site met trop de temps à répondre. Réessayez dans un instant.'
                : "Impossible de joindre ce site. Vérifiez l'adresse.");
        }
        if ($code === 404 || $code === 410) {
            throw new ImportException('Page introuvable (erreur ' . $code . ') : vérifiez l\'adresse.');
        }
        if ($code === 401 || $code === 403 || $code === 429 || $code === 503) {
            throw new ImportException('Ce site refuse la lecture automatique (erreur ' . $code . '). Copiez la recette à la main ou essayez une autre adresse.');
        }
        if ($code < 200 || $code >= 300) {
            throw new ImportException('Le site a répondu par une erreur (' . $code . ').');
        }
        if ($type !== '' && !preg_match('#text/html|application/xhtml|text/plain|application/xml|text/xml#', $type)) {
            throw new ImportException("Cette adresse ne mène pas à une page web (type « $type »).");
        }
        return ['html' => import_to_utf8($body, $type), 'url' => $u['url']];
    }
    throw new ImportException('Trop de redirections.');
}

function import_absolute_url(string $location, string $base): string
{
    if (preg_match('#^https?://#i', $location)) {
        return $location;
    }
    $p = parse_url($base);
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($location, '//')) {
        return $p['scheme'] . ':' . $location;
    }
    if (str_starts_with($location, '/')) {
        return $origin . $location;
    }
    $dir = preg_replace('#/[^/]*$#', '/', $p['path'] ?? '/');
    return $origin . $dir . $location;
}

/** Page → UTF-8 (anciens sites en ISO-8859-1 / Windows-1252 fréquents en français). */
function import_to_utf8(string $html, string $contentType): string
{
    $charset = '';
    if (preg_match('/charset=([\w-]+)/i', $contentType, $m)) {
        $charset = strtolower($m[1]);
    } elseif (preg_match('/<meta[^>]+charset=["\']?\s*([\w-]+)/i', substr($html, 0, 4096), $m)) {
        $charset = strtolower($m[1]);
    }
    if (str_starts_with($html, "\xEF\xBB\xBF")) {
        $html = substr($html, 3);
    }
    if ($charset !== '' && !in_array($charset, ['utf-8', 'utf8'], true)) {
        $from = in_array($charset, ['iso-8859-1', 'latin1', 'iso-8859-15', 'windows-1252', 'cp1252'], true) ? 'Windows-1252' : $charset;
        $conv = @mb_convert_encoding($html, 'UTF-8', $from);
        if ($conv !== false && $conv !== '') {
            return $conv;
        }
    }
    return mb_check_encoding($html, 'UTF-8') ? $html : (string) mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
}

// ---------------------------------------------------------------- nettoyage de texte

function import_text(mixed $v): string
{
    if (is_array($v)) {
        if (isset($v['@value'])) {
            return import_text($v['@value']);
        }
        foreach (['text', 'name', 'item'] as $k) {
            if (isset($v[$k]) && import_text($v[$k]) !== '') {
                return import_text($v[$k]);
            }
        }
        foreach ($v as $x) {
            if (($t = import_text($x)) !== '') {
                return $t;
            }
        }
        return '';
    }
    if (!is_scalar($v)) {
        return '';
    }
    $s = (string) $v;
    $s = preg_replace('#<\s*br\s*/?\s*>|</\s*(?:p|li|div|h[1-6]|tr)\s*>#i', "\n", $s);
    $s = strip_tags($s);
    $s = html_entity_decode(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace(["\u{00A0}", "\u{202F}", "\u{2009}"], ' ', $s);
    $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s);
    $s = preg_replace('/[ \t]+/u', ' ', $s);
    $s = preg_replace('/ ?\n ?/u', "\n", $s);
    return trim(preg_replace('/\n{3,}/', "\n\n", $s));
}

/** « PT1H30M », « P0DT0H20M », « 20 min », « 1h15 » → minutes. */
function import_minutes(mixed $v): ?int
{
    $s = trim(import_text($v));
    if ($s === '') {
        return null;
    }
    if (preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/i', $s, $m)) {
        $min = (int) ($m[1] ?? 0) * 1440 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0) + ((int) ($m[4] ?? 0) >= 30 ? 1 : 0);
        return $min > 0 && $min <= 4320 ? $min : null;
    }
    $min = 0;
    $found = false;
    if (preg_match('/(\d+)\s*(?:h|heures?)\s*(\d+)?/iu', $s, $m)) {
        $min += (int) $m[1] * 60 + (int) ($m[2] ?? 0);
        $found = true;
    } elseif (preg_match('/(\d+)\s*(?:min|minutes?|mn)\b/iu', $s, $m)) {
        $min += (int) $m[1];
        $found = true;
    }
    return $found && $min > 0 && $min <= 4320 ? $min : null;
}

/** « 4 », « Pour 6 personnes », ["4", "4 portions"], « 6 à 8 parts » → 4 / 6 / 4 / 6. */
function import_servings(mixed $v): ?int
{
    $s = import_text($v);
    if (preg_match('/\d+/', $s, $m)) {
        $n = (int) $m[0];
        return $n >= 1 && $n <= 30 ? $n : null;
    }
    return null;
}

// ---------------------------------------------------------------- schema.org : JSON-LD

function import_ld_decode(string $raw): mixed
{
    $raw = trim(preg_replace(['/^\s*<!--/', '/-->\s*$/', '/^\s*\/\/\s*<!\[CDATA\[|\/\/\s*\]\]>\s*$/'], '', trim($raw)));
    $data = json_decode($raw, true);
    if ($data === null) {                                                     // JSON « sale » : retours à la ligne bruts dans les chaînes
        $data = json_decode(import_json_escape_controls($raw), true);
    }
    if ($data === null) {
        $data = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5), true);
    }
    return $data;
}

/** Échappe les caractères de contrôle (retours à la ligne, tabulations) situés À L'INTÉRIEUR des chaînes JSON. */
function import_json_escape_controls(string $raw): string
{
    $out = '';
    $inString = false;
    $escaped = false;
    $len = strlen($raw);
    for ($i = 0; $i < $len; $i++) {
        $c = $raw[$i];
        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($c === '\\') {
                $escaped = true;
            } elseif ($c === '"') {
                $inString = false;
            } elseif ($c === "\n") {
                $c = '\\n';
            } elseif ($c === "\r") {
                $c = '';
            } elseif ($c === "\t" || ord($c) < 0x20) {
                $c = ' ';
            }
        } elseif ($c === '"') {
            $inString = true;
        }
        $out .= $c;
    }
    return $out;
}

function import_is_recipe_node(mixed $n): bool
{
    if (!is_array($n) || !isset($n['@type'])) {
        return false;
    }
    foreach ((array) $n['@type'] as $t) {
        if (is_string($t) && strcasecmp(preg_replace('#^.*[/\#]#', '', $t), 'Recipe') === 0) {
            return true;
        }
    }
    return false;
}

/** Tous les nœuds Recipe d'un arbre JSON-LD (@graph, tableaux, mainEntity…). */
function import_collect_recipes(mixed $node, array &$found, int $depth = 0): void
{
    if (!is_array($node) || $depth > 8) {
        return;
    }
    if (import_is_recipe_node($node)) {
        $found[] = $node;
    }
    foreach ($node as $k => $child) {
        if (is_array($child) && ($k === '@graph' || $k === 'mainEntity' || $k === 'mainEntityOfPage' || is_int($k) || $k === 'itemListElement' || $k === 'item')) {
            import_collect_recipes($child, $found, $depth + 1);
        }
    }
}

function import_recipe_score(array $n): int
{
    return count((array) ($n['recipeIngredient'] ?? $n['ingredients'] ?? [])) * 3 + count((array) ($n['recipeInstructions'] ?? [])) * 3 + (isset($n['name']) ? 1 : 0);
}

/** Étapes d'un champ recipeInstructions (chaîne, liste, HowToStep, HowToSection…). @return array<int, array{kind:string,text:string}> */
function import_ld_steps(mixed $v): array
{
    $out = [];
    if (is_string($v)) {
        return import_split_instructions($v);
    }
    if (!is_array($v)) {
        return [];
    }
    if (isset($v['@type']) || isset($v['itemListElement']) || isset($v['text'])) {
        $v = [$v];
    }
    foreach ($v as $item) {
        if (is_string($item)) {
            array_push($out, ...import_split_instructions($item));
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        $type = strtolower(implode(',', (array) ($item['@type'] ?? '')));
        if (str_contains($type, 'howtosection') || (isset($item['itemListElement']) && !isset($item['text']))) {
            $title = import_text($item['name'] ?? '');
            if ($title !== '') {
                $out[] = ['kind' => 'heading', 'text' => rtrim(mb_substr($title, 0, 150), ' :')];
            }
            array_push($out, ...import_ld_steps($item['itemListElement'] ?? []));
            continue;
        }
        $text = import_text($item['text'] ?? ($item['name'] ?? ($item['description'] ?? ($item['item'] ?? ''))));
        if ($text !== '') {
            array_push($out, ...import_split_instructions($text));
        }
    }
    return $out;
}

/** Texte d'instructions → étapes : une par ligne, ou découpé sur « 1. … 2. … » s'il est sur une seule ligne. */
function import_split_instructions(string $text): array
{
    $text = import_text($text);
    if ($text === '') {
        return [];
    }
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn($l) => $l !== ''));
    if (count($lines) === 1 && preg_match('/^\s*(?:étape\s*)?1\s*[.):]\s/iu', $lines[0]) && preg_match_all('/(?:^|\s)\d{1,2}\s*[.)]\s+\p{Lu}/u', $lines[0]) >= 2) {
        $lines = array_values(array_filter(array_map('trim', preg_split('/(?:^|\s)(?=\d{1,2}\s*[.)]\s+\p{Lu})/u', $lines[0]))));
    }
    $out = [];
    foreach ($lines as $l) {
        $l = strip_step_numbering($l);
        if ($l !== '' && !preg_match('/^étapes?\s*\d*\s*:?$/iu', $l)) {
            $out[] = ['kind' => 'step', 'text' => mb_substr($l, 0, 4000)];
        }
    }
    return $out;
}

function import_list(mixed $v): array
{
    if (is_string($v)) {
        $v = preg_split('/\R|<br\s*\/?>/i', $v);
    }
    $out = [];
    foreach ((array) $v as $x) {
        $t = import_text($x);
        if ($t !== '') {
            foreach (explode("\n", $t) as $line) {
                if (trim($line) !== '') {
                    $out[] = trim($line);
                }
            }
        }
    }
    return $out;
}

// ---------------------------------------------------------------- schema.org : microdonnées et OpenGraph

function import_microdata(string $html): ?array
{
    if (!class_exists('DOMDocument')) {
        return null;
    }
    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $xp = new DOMXPath($dom);
    $scope = $xp->query('//*[@itemtype][contains(translate(@itemtype,"RECIPE","recipe"),"schema.org/recipe")]')->item(0);
    if (!$scope) {
        return null;
    }
    $val = function (DOMNode $n): string {
        foreach (['content', 'datetime', 'src', 'href'] as $a) {
            if ($n instanceof DOMElement && $n->hasAttribute($a) && $n->getAttribute($a) !== '') {
                return $n->getAttribute($a);
            }
        }
        return trim($n->textContent);
    };
    $all = function (string $prop) use ($xp, $scope): array {
        $out = [];
        foreach ($xp->query('.//*[@itemprop="' . $prop . '"]', $scope) as $n) {
            $out[] = $n;
        }
        return $out;
    };
    $first = function (string $prop) use ($all, $val): string {
        $n = $all($prop)[0] ?? null;
        return $n ? $val($n) : '';
    };
    $steps = [];
    foreach ($all('recipeInstructions') as $n) {
        $items = $xp->query('.//li|.//p', $n);
        if ($items->length > 0) {
            foreach ($items as $li) {
                $steps[] = trim($li->textContent);
            }
        } else {
            $steps[] = trim($n->textContent);
        }
    }
    $ings = [];
    foreach (array_merge($all('recipeIngredient'), $all('ingredients')) as $n) {
        $ings[] = trim(preg_replace('/\s+/u', ' ', $n->textContent));
    }
    return [
        'name' => $first('name'), 'description' => $first('description'), 'recipeIngredient' => $ings,
        'recipeInstructions' => $steps, 'recipeYield' => $first('recipeYield'),
        'prepTime' => $first('prepTime'), 'cookTime' => $first('cookTime'), 'totalTime' => $first('totalTime'),
        'recipeCategory' => $first('recipeCategory'), 'keywords' => $first('keywords'),
    ];
}

function import_meta(string $html, string $property): string
{
    foreach (['property', 'name'] as $attr) {
        if (preg_match('/<meta[^>]+' . $attr . '=["\']' . preg_quote($property, '/') . '["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)
            || preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]*' . $attr . '=["\']' . preg_quote($property, '/') . '["\']/i', $html, $m)) {
            return import_text($m[1]);
        }
    }
    return '';
}

// ---------------------------------------------------------------- repli : lecture du texte de la page

/**
 * Pour les pages sans données structurées (ou dont la liste d'étapes est vide) : cherche les titres
 * « Ingrédients » et « Préparation » et lit les listes / paragraphes qui suivent. Lecture approximative.
 *
 * @return array{ingredients: string[], steps: array}
 */
function import_html_heuristic(string $html): array
{
    $none = ['ingredients' => [], 'steps' => []];
    if (!class_exists('DOMDocument')) {
        return $none;
    }
    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $xp = new DOMXPath($dom);
    foreach ($xp->query('//script|//style|//noscript|//nav|//footer|//aside|//form|//svg|//iframe|//header[not(ancestor::article)]') as $n) {
        $n->parentNode?->removeChild($n);
    }
    $root = $xp->query('//article')->item(0) ?? $xp->query('//main')->item(0) ?? $xp->query('//body')->item(0);
    if (!$root) {
        return $none;
    }

    // Blocs de texte dans l'ordre de la page : [type, texte, classes]. « leaf » = bloc sans sous-bloc (div de texte simple).
    $blocks = [];
    $blockTags = './/div|.//p|.//ul|.//ol|.//li|.//table|.//section|.//article|.//h1|.//h2|.//h3|.//h4|.//h5|.//h6';
    $checks = 0;                                       // plafond de requêtes XPath : pages géantes
    $walk = function (DOMNode $node, string $parentClass = '') use (&$walk, &$blocks, &$checks, $xp, $blockTags): void {
        foreach ($node->childNodes as $c) {
            if (!($c instanceof DOMElement)) {
                continue;
            }
            $tag = strtolower($c->tagName);
            $cls = $c->getAttribute('class') . ' ' . $parentClass;
            if (preg_match('/^h[1-6]$/', $tag)) {
                $blocks[] = ['h', import_text($c->textContent), ''];
            } elseif ($tag === 'li') {
                $blocks[] = ['li', import_text($c->textContent), $cls];
            } elseif ($tag === 'p') {
                $t = import_text($c->textContent);
                $strong = $c->getElementsByTagName('strong')->length + $c->getElementsByTagName('b')->length;
                $isHeading = $strong && mb_strlen($t) <= 60 && preg_match('/^(?:ingr[ée]dients?|pr[ée]paration|[ée]tapes?|instructions?|r[ée]alisation)\b/iu', $t);
                $blocks[] = [$isHeading ? 'h' : 'p', $t, $cls];
            } elseif (in_array($tag, ['div', 'span', 'section', 'td'], true) && ++$checks < 4000 && mb_strlen($c->textContent) >= 20 && mb_strlen($c->textContent) <= 1200
                      && $xp->query($blockTags, $c)->length === 0) {
                $blocks[] = ['p', import_text($c->textContent), $cls];
            } else {
                $walk($c, $c->getAttribute('class'));
            }
        }
    };
    $walk($root);

    $mode = null;
    $ings = $steps = $ingPara = $stepPara = [];
    foreach ($blocks as [$type, $text, $cls]) {
        $text = trim($text);
        if ($text === '') {
            continue;
        }
        if ($type === 'h') {
            $t = rtrim($text, ' :');
            if (mb_strlen($t) <= 70 && preg_match('/ingr[ée]dients?/iu', $t)) {
                $mode = 'ing';
            } elseif (mb_strlen($t) <= 70 && preg_match('/^(?:la |les |notre |ma )?(?:pr[ée]paration|instructions?|[ée]tapes?|r[ée]alisation|d[ée]roul[ée]|mode op[ée]ratoire|comment (?:faire|pr[ée]parer)|m[ée]thode)\b|\bpr[ée]paration\b/iu', $t)) {
                $mode = 'steps';
            } elseif ($mode === 'steps' && count($steps) + count($stepPara) > 0) {
                $mode = null;                                  // titre suivant : fin des étapes (astuces, commentaires…)
            } elseif ($mode === 'ing' && count($ings) + count($ingPara) > 0 && !preg_match('/^pour\b/iu', $t)) {
                $mode = null;
            }
            continue;
        }
        if ($mode === 'ing' && mb_strlen($text) <= 220) {
            $type === 'li' ? $ings[] = [$text, $cls] : $ingPara[] = [$text, $cls];
        } elseif ($mode === 'steps' && mb_strlen($text) >= 12) {
            $type === 'li' ? $steps[] = [$text, $cls] : $stepPara[] = [$text, $cls];
        }
    }
    // Si certains blocs portent une classe explicite (« recipe__step », « ingredient »…), on ne garde qu'eux :
    // cela écarte les encadrés d'avertissement ou de publicité intercalés.
    $pick = function (array $items, string $hint): array {
        $hinted = array_filter($items, fn($i) => preg_match($hint, $i[1]));
        return array_column(array_values($hinted ?: $items), 0);
    };
    $ings = $pick($ings ?: $ingPara, '/ingredient/i');
    $steps = $pick($steps ?: $stepPara, '/step|instruction|etape|method|prepar/i');
    return [
        'ingredients' => array_slice(array_values(array_unique($ings)), 0, 80),
        'steps'       => array_map(fn($t) => ['kind' => 'step', 'text' => strip_step_numbering($t)], array_slice($steps, 0, MAX_STEPS)),
    ];
}

// ---------------------------------------------------------------- analyse d'une page

/**
 * @return array{source:string, name:string, description:string, ingredients:string[], steps:array, servings:?int,
 *               prep:?int, cook:?int, total:?int, category:string, keywords:string[]}
 */
function import_parse(string $html): array
{
    $result = ['source' => 'aucune', 'name' => '', 'description' => '', 'ingredients' => [], 'steps' => [], 'servings' => null,
               'prep' => null, 'cook' => null, 'total' => null, 'category' => '', 'keywords' => []];

    $node = null;
    if (preg_match_all('#<script\b[^>]*type\s*=\s*["\']?application/ld\+json["\']?[^>]*>(.*?)</script>#is', $html, $blocks)) {
        $found = [];
        foreach ($blocks[1] as $raw) {
            import_collect_recipes(import_ld_decode($raw), $found);
        }
        usort($found, fn($a, $b) => import_recipe_score($b) <=> import_recipe_score($a));
        $node = $found[0] ?? null;
        if ($node) {
            $result['source'] = 'schema.org (JSON-LD)';
        }
    }
    if (!$node) {
        $node = import_microdata($html);
        if ($node && (count($node['recipeIngredient']) || count($node['recipeInstructions']))) {
            $result['source'] = 'schema.org (microdonnées)';
        } else {
            $node = null;
        }
    }

    if ($node) {
        $result['name'] = import_text($node['name'] ?? '');
        $result['description'] = import_text($node['description'] ?? '');
        $result['ingredients'] = import_list($node['recipeIngredient'] ?? ($node['ingredients'] ?? []));
        $result['steps'] = import_ld_steps($node['recipeInstructions'] ?? []);
        $result['servings'] = import_servings($node['recipeYield'] ?? '');
        $result['prep'] = import_minutes($node['prepTime'] ?? '');
        $result['cook'] = import_minutes($node['cookTime'] ?? '');
        $result['total'] = import_minutes($node['totalTime'] ?? '');
        $result['category'] = mb_strtolower(implode(' ', import_list($node['recipeCategory'] ?? '')));
        $kw = $node['keywords'] ?? '';
        $result['keywords'] = array_map('mb_strtolower', is_string($kw) ? array_map('trim', explode(',', import_text($kw))) : import_list($kw));
    }

    // Données manquantes (aucune recette structurée, ou étapes / ingrédients vides) : lecture du texte de la page.
    $result['approximate'] = false;
    if (!$result['ingredients'] || !$result['steps']) {
        $h = import_html_heuristic($html);
        if (!$result['ingredients'] && count($h['ingredients']) >= 2) {
            $result['ingredients'] = $h['ingredients'];
            $result['approximate'] = true;
        }
        if (!$result['steps'] && count($h['steps']) >= 2) {
            $result['steps'] = $h['steps'];
            $result['approximate'] = true;
        }
        if ($result['approximate'] && $result['source'] === 'aucune') {
            $result['source'] = 'texte de la page';
        }
    }

    if ($result['name'] === '') {                                              // repli OpenGraph / <title>
        $title = import_meta($html, 'og:title');
        if ($title === '' && preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = import_text($m[1]);
        }
        $site = import_meta($html, 'og:site_name');
        if ($site !== '') {
            $title = preg_replace('/\s*[-–—|:·]\s*' . preg_quote($site, '/') . '\s*$/iu', '', $title);
        }
        $result['name'] = $title;
        if ($result['description'] === '') {
            $result['description'] = import_meta($html, 'og:description') ?: import_meta($html, 'description');
        }
        if (in_array($result['source'], ['aucune'], true) && $title !== '') {
            $result['source'] = 'titre de la page';
        }
    }
    return $result;
}

// ---------------------------------------------------------------- résultat → formulaire Kitcho

function import_guess_category(string $name, array $ingredientNames, string $category, array $keywords): string
{
    $hay = norm_key($category . ' ' . implode(' ', $keywords));
    if (preg_match('/vegetarien|vegan|vegetalien|sans viande/', $hay)) {
        return 'vegetarien';
    }
    if (preg_match('/dessert|gateau|biscuit|boisson|cocktail|pain\b|sauce|confiture|brioche|tarte sucree|patisserie|gouter/', $hay . ' ' . norm_key($name))) {
        return 'autre';
    }
    $byName = guess_category($name);
    if ($byName !== 'autre') {
        return $byName;
    }
    $ings = norm_key(implode(' ', $ingredientNames));
    if (preg_match('/saumon|cabillaud|thon|poisson|crevette|colin|merlu|sole\b|dorade|truite/', $ings)) {
        return 'poisson';
    }
    if (preg_match('/poulet|boeuf|porc|veau|agneau|dinde|jambon|lardon|saucisse|viande|canard|steak|bacon|chorizo|merguez|hache/', $ings)) {
        return 'viande';
    }
    return count($ingredientNames) >= 3 ? 'vegetarien' : 'autre';
}

/**
 * Données d'import → valeurs du formulaire de recette.
 *
 * @return array{recipe: array, ingText: string, stepsText: string, warnings: string[], counts: array}
 */
function import_to_form(array $p, string $url, array $household): array
{
    $warnings = [];
    $rows = [];
    $seen = [];
    foreach ($p['ingredients'] as $line) {
        $line = trim(preg_replace('/\s+/u', ' ', $line));
        if ($line === '' || (str_ends_with($line, ':') && !preg_match('/\d/', $line))) {   // « Pour la pâte : » = intertitre, pas un ingrédient
            continue;
        }
        $row = parse_ingredient_line($line);
        if ($row && !isset($seen[norm_key($line)])) {
            $seen[norm_key($line)] = true;
            $rows[] = $row;
        }
    }
    $steps = $p['steps'];
    $name = mb_ucfirst(mb_substr($p['name'], 0, 200));
    if ($name === '') {
        $warnings[] = 'Aucun titre trouvé : donnez un nom à la recette.';
    }
    if (!empty($p['approximate'])) {
        $warnings[] = "Cette page n'a pas de recette structurée : ingrédients et/ou étapes ont été lus dans le texte, de façon approximative. Relisez-les attentivement.";
    }
    if ($p['source'] === 'aucune' || $p['source'] === 'titre de la page') {
        $warnings[] = "Cette page n'expose pas de données de recette lisibles automatiquement : seul le titre a pu être récupéré. Ajoutez les ingrédients et les étapes à la main.";
    } else {
        if (!$rows) {
            $warnings[] = 'Aucun ingrédient trouvé sur cette page.';
        }
        if (!$steps) {
            $warnings[] = 'Aucune étape de préparation trouvée sur cette page.';
        }
    }
    $notes = $p['description'];
    $notes = mb_strlen($notes) > 1000 ? rtrim(mb_substr($notes, 0, 997)) . '…' : $notes;

    $recipe = [
        'name' => $name, 'category' => import_guess_category($name, array_column($rows, 'name'), $p['category'], $p['keywords']),
        'url' => mb_substr(import_clean_link($url), 0, 500), 'notes' => $notes !== '' ? $notes : null,
        'servings' => $p['servings'] ?? (int) $household['default_servings'],
        'prep_minutes' => $p['prep'], 'cook_minutes' => $p['cook'], 'total_minutes' => $p['total'],
        'favorite' => 0, 'auto_ok' => 1,
    ];
    return [
        'recipe' => $recipe, 'ingText' => ingredients_to_text($rows), 'stepsText' => steps_to_text($steps),
        'warnings' => $warnings,
        'counts' => ['ingredients' => count($rows), 'steps' => count(array_filter($steps, fn($s) => $s['kind'] === 'step')), 'source' => $p['source']],
    ];
}

/** Hôte lisible d'une adresse : « www.marmiton.org » → « marmiton.org ». */
function import_host(string $url): string
{
    return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
}
