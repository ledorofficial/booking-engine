<?php
/**
 * One canonical page for "all stays".
 *
 * MotoPress registers an auto-generated "Accommodation Types Archive" at
 * /accommodation/ that duplicates the real page /our-unique-stays/ (thin, off-brand,
 * competes with it in search and AI answer engines). This module:
 *  - 301-redirects the room-type archive to the "our-unique-stays" page in the
 *    visitor's language (/ja/accommodation/ -> /ja/our-unique-stays/);
 *  - drops the archive URL from the Yoast XML sitemap.
 * Individual room pages (/accommodation/skygazer/) are untouched.
 * Filter `kbs_stays_page_slug` to change the target page slug.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Permalink of the canonical "all stays" page in the current language, or '' if it doesn't exist. */
function kbs_ar_target(): string
{
    $page = get_page_by_path((string) apply_filters('kbs_stays_page_slug', 'our-unique-stays'), OBJECT, 'page');
    if (!$page || $page->post_status !== 'publish') {
        return '';
    }
    $id = (int) $page->ID;
    if (function_exists('pll_get_post') && function_exists('pll_current_language')) {
        $cur = pll_current_language('slug');
        if ($cur && ($t = pll_get_post($id, $cur))) {
            $id = (int) $t;
        }
    }
    return (string) get_permalink($id);
}

add_action('template_redirect', function () {
    if (is_admin() || !is_post_type_archive('mphb_room_type')) {
        return;
    }
    $to = kbs_ar_target();
    if ($to !== '') {
        wp_safe_redirect($to, 301);
        exit;
    }
}, 1);

/** Keep the (now redirecting) archive out of the Yoast sitemap. */
add_filter('wpseo_sitemap_post_type_archive_link', function ($link, $post_type) {
    return $post_type === 'mphb_room_type' ? false : $link;
}, 10, 2);
