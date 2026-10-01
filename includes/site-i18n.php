<?php
/**
 * Make the rest of a Polylang-translated site follow the visitor's language:
 *  - menu items point to (and are titled like) the translated pages;
 *  - internal links in the page (Elementor buttons, header, footer) go to the
 *    translated page instead of the English one;
 *  - shared header/footer text (Elementor theme-builder templates, which free
 *    Polylang does not translate) is swapped from a dictionary.
 *
 * The dictionary lives in the option `kbs_i18n_dictionary`:
 *   [ 'ja' => [ 'Book Now' => '今すぐ予約', ... ], 'zh-CN' => [ ... ] ]
 * keyed by engine language code (see kbs_lang()), English text => translation.
 * Does nothing on the default language, or without Polylang.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Polylang slug of the language being rendered, or null when it is the default / Polylang is off. */
function kbs_si_slug(): ?string
{
    if (!function_exists('pll_current_language') || !function_exists('pll_default_language')) {
        return null;
    }
    $cur = pll_current_language('slug');
    return ($cur && $cur !== pll_default_language('slug')) ? (string) $cur : null;
}

/** Translated post for $post_id in the current language, or 0. */
function kbs_si_translated(int $post_id, string $slug): int
{
    $t = function_exists('pll_get_post') ? pll_get_post($post_id, $slug) : 0;
    return $t ? (int) $t : 0;
}

/* ---- Menus --------------------------------------------------------------- */

add_filter('wp_nav_menu_objects', function (array $items): array {
    $slug = kbs_si_slug();
    if ($slug === null) {
        return $items;
    }
    $dict = kbs_si_dictionary();
    foreach ($items as $item) {
        if (($item->type ?? '') === 'post_type' && ($t = kbs_si_translated((int) $item->object_id, $slug))) {
            $item->object_id = $t;
            $item->title     = get_the_title($t);
            $item->url       = get_permalink($t);
        } elseif (isset($dict[$item->title])) {
            $item->title = $dict[$item->title];
        }
    }
    return $items;
}, 20);

/* ---- Page output: header/footer text + internal links -------------------- */

add_action('template_redirect', function () {
    if (kbs_si_slug() === null || is_feed() || is_embed() || wp_doing_ajax()) {
        return;
    }
    // Late priority so this runs before page-cache plugins save the HTML.
    ob_start('kbs_si_filter_html');
}, 99);

/** @return array<string,string> English text => translation, for the current language. */
function kbs_si_dictionary(): array
{
    static $cache = [];
    $lang = kbs_lang();
    if (!isset($cache[$lang])) {
        $all = get_option('kbs_i18n_dictionary', []);
        $cache[$lang] = (array) apply_filters('kbs_i18n_dictionary', is_array($all) ? ($all[$lang] ?? []) : [], $lang);
    }
    return $cache[$lang];
}

function kbs_si_filter_html(string $html): string
{
    $slug = kbs_si_slug();
    if ($slug === null || stripos($html, '<html') === false) {
        return $html;
    }

    // 1. Dictionary text nodes: >English< => >Translation<
    foreach (kbs_si_dictionary() as $en => $tr) {
        $variants = array_unique([$en, esc_html($en), str_replace('&', '&#038;', $en), str_replace('&', '&amp;', $en)]);
        foreach ($variants as $v) {
            $html = preg_replace('~>(\s*)' . preg_quote($v, '~') . '(\s*)<~u', '>${1}' . addcslashes($tr, '\\$') . '${2}<', $html) ?? $html;
        }
    }

    // 2. Internal links -> the translated page.
    $map  = kbs_si_slug_map();
    $home = untrailingslashit(home_url());
    $host = (string) parse_url($home, PHP_URL_HOST);

    return preg_replace_callback('~<a\b([^>]*?)\shref=(["\'])([^"\']*)\2([^>]*)>~i', function ($m) use ($map, $home, $host, $slug) {
        if (stripos($m[0], 'hreflang=') !== false) {          // the language switcher itself
            return $m[0];
        }
        $href = html_entity_decode($m[3]);
        if ($href === '' || $href[0] === '#' || preg_match('~^(mailto|tel|javascript|data):~i', $href)) {
            return $m[0];
        }
        $parts = parse_url($href);
        if ($parts === false || (isset($parts['host']) && strcasecmp($parts['host'], $host) !== 0)) {
            return $m[0];                                      // external link
        }
        $path = $parts['path'] ?? '/';
        $tail = (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        if (trim($path, '/') === '') {
            $target = pll_home_url($slug);
        } else {
            $last = basename(rtrim($path, '/'));
            $pid  = $map[$last] ?? 0;
            $t    = $pid ? kbs_si_translated($pid, $slug) : 0;
            if (!$t) {
                return $m[0];                                  // no translation (uploads, archives, already-localized…)
            }
            $target = get_permalink($t);
        }
        return '<a' . $m[1] . ' href=' . $m[2] . esc_url($target . $tail) . $m[2] . $m[4] . '>';
    }, $html) ?? $html;
}

/** post_name => ID of the default-language pages and rooms, for resolving links. */
function kbs_si_slug_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    global $wpdb;
    $map  = [];
    $def  = pll_default_language('slug');
    $rows = $wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','post','mphb_room_type')");
    foreach ($rows as $r) {
        if (pll_get_post_language((int) $r->ID, 'slug') === $def) {
            $map[$r->post_name] = (int) $r->ID;
        }
    }
    return $map;
}
