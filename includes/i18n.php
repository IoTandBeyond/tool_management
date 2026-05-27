<?php
declare(strict_types=1);

/** @return 'en'|'es' */
function tm_resolve_lang(): string
{
    if (!empty($_COOKIE['tm-lang']) && in_array($_COOKIE['tm-lang'], ['en', 'es'], true)) {
        return $_COOKIE['tm-lang'];
    }
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if ($accept !== '' && (str_starts_with($accept, 'es') || str_contains($accept, 'es-'))) {
        return 'es';
    }
    return 'en';
}

/** @return array<string, mixed> */
function tm_load_locale(string $lang): array
{
    if (!in_array($lang, ['en', 'es'], true)) {
        $lang = 'en';
    }
    $path = dirname(__DIR__) . '/lang/' . $lang . '.json';
    if (!is_readable($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function tm_t(string $key, array $vars = []): string
{
    static $locale = null;
    static $lang = null;
    if ($locale === null) {
        $lang = tm_resolve_lang();
        $locale = tm_load_locale($lang);
    }
    $parts = explode('.', $key);
    $v = $locale;
    foreach ($parts as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) {
            return $key;
        }
        $v = $v[$part];
    }
    if (!is_string($v)) {
        return $key;
    }
    foreach ($vars as $k => $val) {
        $v = str_replace('{' . $k . '}', (string) $val, $v);
    }
    return $v;
}

function tm_i18n_boot_script(): void
{
    $lang = tm_resolve_lang();
    $locale = tm_load_locale($lang);
    echo '<script>window.__TM_LANG=' . json_encode($lang, JSON_UNESCAPED_UNICODE) . ';';
    echo 'window.__TM_LOCALE=' . json_encode($locale, JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
}
