<?php
/**
 * Clean translated URLs with free Polylang.
 *
 * Free Polylang can't give two languages the same slug, so translated posts are
 * stored with a language suffix (amihan-zh). This module hides that:
 *  - the permalink of a translation is the default-language permalink under the
 *    language prefix:   /zh/accommodation/amihan/   (not …/amihan-zh/);
 *  - a request for the clean URL is pointed at the translated post, so it
 *    resolves in that language instead of redirecting to English;
 *  - the old suffixed URL still works and redirects (canonical) to the clean one.
 * Does nothing without Polylang, and never touches the default language.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** default-language slug => [Polylang language slug => translated post_name]. */
function kbs_cs_index(): array
{
    static $idx = null;
    if ($idx !== null) {
        return $idx;
    }
    $idx = [];
    if (!function_exists('pll_get_post') || !function_exists('pll_default_language')) {
        return $idx;
    }
    global $wpdb;
    $def   = pll_default_language('slug');
    $langs = pll_languages_list(['fields' => 'slug']);
    $rows  = $wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_name <> '' AND post_type IN ('page','post','mphb_room_type')");
    foreach ($rows as $r) {
        if (pll_get_post_language((int) $r->ID, 'slug') !== $def) {
            continue;
        }
        foreach ($langs as $l) {
            if ($l === $def || !($t = pll_get_post((int) $r->ID, $l))) {
                continue;
            }
            $name = (string) get_post_field('post_name', $t);
            if ($name !== '' && $name !== $r->post_name) {
                $idx[$r->post_name][$l] = $name;
            }
        }
    }
    return $idx;
}

/** Map each segment of a clean path (a/b/c) to its translated slug in $lang. */
function kbs_cs_map_path(string $path, string $lang): string
{
    $idx = kbs_cs_index();
    $out = [];
    foreach (explode('/', $path) as $seg) {
        $out[] = $idx[$seg][$lang] ?? $seg;
    }
    return implode('/', $out);
}

/** Point clean-URL requests at the translated post. */
add_filter('request', function (array $qv): array {
    if (!function_exists('pll_default_language')) {
        return $qv;
    }
    $lang = (string) ($qv['lang'] ?? '');
    if ($lang === '' || $lang === pll_default_language('slug')) {
        return $qv;
    }
    $keys = ['pagename', 'name'];
    foreach (get_post_types(['public' => true], 'objects') as $pt) {
        if (!empty($pt->query_var)) {
            $keys[] = (string) $pt->query_var;
        }
    }
    foreach (array_unique($keys) as $k) {
        if (!empty($qv[$k]) && is_string($qv[$k])) {
            $qv[$k] = kbs_cs_map_path($qv[$k], $lang);
        }
    }
    return $qv;
}, 20);

/** Permalink of a translation = default-language permalink under the language prefix. */
function kbs_cs_link(string $link, $post): string
{
    if (!function_exists('pll_get_post_language') || strpos($link, '%') !== false) {
        return $link;
    }
    $p = get_post($post);
    if (!$p) {
        return $link;
    }
    $def  = pll_default_language('slug');
    $lang = pll_get_post_language($p->ID, 'slug');
    if (!$lang || $lang === $def) {
        return $link;
    }
    $front = (int) get_option('page_on_front');
    if ($front && in_array($front, pll_get_post_translations($p->ID), true)) {
        return $link;                      // the front page translation is the language home
    }
    $src = pll_get_post($p->ID, $def);
    if (!$src || (int) $src === (int) $p->ID) {
        return $link;
    }
    $srcLink = get_permalink($src);
    $home    = trailingslashit(home_url('/'));
    if (!$srcLink || strpos($srcLink, '?') !== false || strpos($srcLink, $home) !== 0) {
        return $link;
    }
    return trailingslashit(pll_home_url($lang)) . ltrim(substr($srcLink, strlen($home)), '/');
}

add_filter('page_link', fn ($link, $id) => kbs_cs_link($link, $id), 99, 2);
add_filter('post_link', fn ($link, $post) => kbs_cs_link($link, $post), 99, 2);
add_filter('post_type_link', fn ($link, $post) => kbs_cs_link($link, $post), 99, 2);

/** The old suffixed URL (…/amihan-zh/) redirects to the clean one. */
add_action('template_redirect', function () {
    if (!function_exists('pll_current_language') || !is_singular() || is_preview() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return;
    }
    if (pll_current_language('slug') === pll_default_language('slug')) {
        return;
    }
    $clean = get_permalink();
    if (!$clean) {
        return;
    }
    $want = untrailingslashit((string) parse_url($clean, PHP_URL_PATH));
    $have = untrailingslashit((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
    if ($want !== '' && $want !== $have && empty($_POST)) {
        $q = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        wp_safe_redirect($clean . ($q !== '' ? '?' . $q : ''), 301);
        exit;
    }
}, 5);
