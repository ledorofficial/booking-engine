<?php
/**
 * /accommodation/ is THE "all stays" page.
 *
 * MotoPress registers an auto-generated room-type archive at /accommodation/. We turn
 * the archive off so a normal WordPress page with the slug `accommodation` (Our Unique
 * Stays, translated per language) owns that URL, while single rooms keep their
 * /accommodation/<room>/ addresses. The old page URL (/our-unique-stays/, also
 * /ja/our-unique-stays/ and the old suffixed /ja/our-unique-stays-ja/) 301s to it.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('register_post_type_args', function ($args, $post_type) {
    if ($post_type === 'mphb_room_type') {
        $args['has_archive'] = false;
    }
    return $args;
}, 10, 2);

/** Rewrite rules changed in this version: rebuild them once per plugin version. */
add_action('init', function () {
    if (get_option('kbs_rewrite_ver') !== KBS_VERSION) {
        flush_rewrite_rules(false);
        update_option('kbs_rewrite_ver', KBS_VERSION, false);
    }
}, 99);

add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }
    $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    if (!preg_match('~^(?:(zh|tw|ja|ko|ru)/)?our-unique-stays(?:-(zh|tw|ja|ko|ru))?$~', $path, $m)) {
        return;
    }
    $lang  = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    wp_safe_redirect(home_url('/' . ($lang !== '' ? $lang . '/' : '') . 'accommodation/') . ($query !== '' ? '?' . $query : ''), 301);
    exit;
}, 1);
