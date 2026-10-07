<?php
/**
 * Language support: hands the visitor's current site language to the booking
 * engine (?lang=xx), translates the widget's own text, and provides a flag
 * dropdown switcher that matches the one on the booking engine.
 *
 * Works with Polylang (recommended), WPML / TranslatePress (anything that sets
 * the WordPress locale), and does nothing special on a single-language site.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Languages the booking engine speaks: code => [native name, flag file]. */
function kbs_languages(): array
{
    return [
        'en'    => ['English',  'gb'],
        'zh-CN' => ['简体中文', 'cn'],
        'zh-TW' => ['繁體中文', 'tw'],
        'ja'    => ['日本語',   'jp'],
        'ko'    => ['한국어',   'kr'],
        'ru'    => ['Русский',  'ru'],
    ];
}

/** Map any WordPress/Polylang locale or slug (zh_CN, zh-hans, ru_RU, ja …) to an engine code, or null. */
function kbs_normalize_lang(?string $value): ?string
{
    $v = strtolower(str_replace('_', '-', trim((string) $value)));
    if ($v === '') {
        return null;
    }
    if (in_array($v, ['zh-cn', 'zh-hans', 'zh-sg', 'zh'], true) || str_starts_with($v, 'zh-hans')) {
        return 'zh-CN';
    }
    if (in_array($v, ['zh-tw', 'zh-hant', 'zh-hk', 'zh-mo'], true) || str_starts_with($v, 'zh-hant')) {
        return 'zh-TW';
    }
    $primary = explode('-', $v)[0];
    return in_array($primary, ['en', 'ja', 'ko', 'ru'], true) ? $primary : null;
}

/** The engine language for the page being rendered ("en" when unknown / single-language site). */
function kbs_lang(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $lang = null;
    if (function_exists('pll_current_language')) {
        $lang = kbs_normalize_lang((string) pll_current_language('locale'))
            ?? kbs_normalize_lang((string) pll_current_language('slug'));
    } elseif (has_filter('wpml_current_language')) {
        $lang = kbs_normalize_lang((string) apply_filters('wpml_current_language', null));
    }
    $lang ??= kbs_normalize_lang(get_locale());

    $lang = (string) apply_filters('kbs_engine_lang', $lang ?? 'en');
    return $cache = (kbs_languages()[$lang] ?? null) ? $lang : 'en';
}

/** Widget text per language. English is also the fallback for any missing key. */
function kbs_strings(string $lang): array
{
    $t = [
        'en' => [
            'arrival' => 'Arrival', 'departure' => 'Departure', 'guests' => 'Guests',
            'add_date' => 'Add date', 'adults' => 'Adults', 'children' => 'Children',
            'fewer' => 'Fewer', 'more' => 'More',
            'guest_forms' => ['%d guest', '%d guests', '%d guests'],
            'note_room' => 'Nightly prices in %s · this room', 'note_lowest' => 'Nightly prices in %s · lowest available room',
            'earlier' => 'Earlier months', 'later' => 'Later months',
            'button' => ['Search' => 'Search', 'Check availability' => 'Check availability'],
        ],
        'zh-CN' => [
            'arrival' => '入住', 'departure' => '退房', 'guests' => '人数',
            'add_date' => '选择日期', 'adults' => '成人', 'children' => '儿童',
            'fewer' => '减少', 'more' => '增加',
            'guest_forms' => ['%d 位客人', '%d 位客人', '%d 位客人'],
            'note_room' => '每晚价格（%s）· 本房型', 'note_lowest' => '每晚价格（%s）· 最低可订房价',
            'earlier' => '上个月', 'later' => '下个月',
            'button' => ['Search' => '搜索', 'Check availability' => '查询空房'],
        ],
        'zh-TW' => [
            'arrival' => '入住', 'departure' => '退房', 'guests' => '人數',
            'add_date' => '選擇日期', 'adults' => '成人', 'children' => '兒童',
            'fewer' => '減少', 'more' => '增加',
            'guest_forms' => ['%d 位旅客', '%d 位旅客', '%d 位旅客'],
            'note_room' => '每晚價格（%s）· 本房型', 'note_lowest' => '每晚價格（%s）· 最低可訂房價',
            'earlier' => '上個月', 'later' => '下個月',
            'button' => ['Search' => '搜尋', 'Check availability' => '查詢空房'],
        ],
        'ja' => [
            'arrival' => 'チェックイン', 'departure' => 'チェックアウト', 'guests' => 'ご利用人数',
            'add_date' => '日付を選択', 'adults' => '大人', 'children' => '子ども',
            'fewer' => '減らす', 'more' => '増やす',
            'guest_forms' => ['%d名', '%d名', '%d名'],
            'note_room' => '1泊あたりの料金（%s）· このお部屋', 'note_lowest' => '1泊あたりの料金（%s）· 空室の最安料金',
            'earlier' => '前の月', 'later' => '次の月',
            'button' => ['Search' => '検索', 'Check availability' => '空室を確認'],
        ],
        'ko' => [
            'arrival' => '체크인', 'departure' => '체크아웃', 'guests' => '인원',
            'add_date' => '날짜 선택', 'adults' => '성인', 'children' => '어린이',
            'fewer' => '줄이기', 'more' => '늘리기',
            'guest_forms' => ['게스트 %d명', '게스트 %d명', '게스트 %d명'],
            'note_room' => '1박 요금 (%s) · 이 객실', 'note_lowest' => '1박 요금 (%s) · 예약 가능한 최저 요금',
            'earlier' => '이전 달', 'later' => '다음 달',
            'button' => ['Search' => '검색', 'Check availability' => '예약 가능 여부 확인'],
        ],
        'ru' => [
            'arrival' => 'Заезд', 'departure' => 'Выезд', 'guests' => 'Гости',
            'add_date' => 'Выбрать дату', 'adults' => 'Взрослые', 'children' => 'Дети',
            'fewer' => 'Меньше', 'more' => 'Больше',
            'guest_forms' => ['%d гость', '%d гостя', '%d гостей'],
            'note_room' => 'Цена за ночь (%s) · этот номер', 'note_lowest' => 'Цена за ночь (%s) · самый доступный номер',
            'earlier' => 'Предыдущие месяцы', 'later' => 'Следующие месяцы',
            'button' => ['Search' => 'Поиск', 'Check availability' => 'Проверить наличие'],
        ],
    ];

    return array_replace($t['en'], $t[$lang] ?? []);
}

/** "2 guests" in the page's language (Russian has three plural forms). */
function kbs_guest_label(int $n): string
{
    $lang  = kbs_lang();
    $forms = kbs_strings($lang)['guest_forms'];
    $i     = $n === 1 ? 0 : 2;
    if ($lang === 'ru') {
        $m10 = $n % 10;
        $m100 = $n % 100;
        $i = ($m10 === 1 && $m100 !== 11) ? 0 : (($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) ? 1 : 2);
    }
    return sprintf($forms[$i], $n);
}

/** Translate a shortcode's button label when it is one of the known English defaults. */
function kbs_button_label(string $label): string
{
    $map = kbs_strings(kbs_lang())['button'];
    return $map[$label] ?? $label;
}

/** String shown for a given key, in the page's language. */
function kbs_t(string $key): string
{
    $s = kbs_strings(kbs_lang())[$key] ?? kbs_strings('en')[$key] ?? '';
    return is_string($s) ? $s : '';
}

/** Strings handed to the widget's JS (window.KBS_I18N). */
function kbs_js_strings(): array
{
    $s = kbs_strings(kbs_lang());
    unset($s['button']);
    $s['lang'] = kbs_lang();
    return $s;
}

/** Hosts whose links should carry ?lang=: the plugin's default base, plus any "bookings." host. */
function kbs_booking_hosts(): array
{
    $hosts = [(string) parse_url(KBS_DEFAULT_BASE, PHP_URL_HOST)];
    return array_values(array_unique(array_filter((array) apply_filters('kbs_booking_hosts', $hosts))));
}

/** The flatpickr locale file for a language, or null for English. */
function kbs_flatpickr_locale(string $lang): ?string
{
    return ['zh-CN' => 'zh', 'zh-TW' => 'zh_tw', 'ja' => 'ja', 'ko' => 'ko', 'ru' => 'ru'][$lang] ?? null;
}

/**
 * Footer script: every link to the booking engine on the page (Book Now
 * buttons, menu items, room "Book" links) gets ?lang=<current language>, so a
 * guest browsing in Japanese lands on the Japanese booking page.
 */
function kbs_print_lang_links(): void
{
    $hosts = wp_json_encode(kbs_booking_hosts());
    $lang  = wp_json_encode(kbs_lang());
    ?>
<script data-cfasync="false" id="kbs-lang-links">
(function(){
  var HOSTS = <?php echo $hosts; ?>, LANG = <?php echo $lang; ?>;
  function fix(a){
    if (!a || !a.getAttribute) return;
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) return;
    var u; try { u = new URL(href, location.href); } catch (e) { return; }
    if (HOSTS.indexOf(u.hostname) === -1 && !/^bookings\./.test(u.hostname)) return;
    if (u.searchParams.get('lang') === LANG) return;
    u.searchParams.set('lang', LANG);
    a.setAttribute('href', u.toString());
  }
  function all(){ document.querySelectorAll('a[href]').forEach(fix); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', all); else all();
  // Menus/popups built after load (mobile menu clones, Elementor popups): fix at the moment of interaction.
  ['pointerdown','focusin','touchstart'].forEach(function(ev){
    document.addEventListener(ev, function(e){ fix(e.target.closest && e.target.closest('a[href]')); }, true);
  });
})();
</script>
    <?php
}
add_action('wp_footer', 'kbs_print_lang_links', 20);

/**
 * [karuna_language_switcher] — round-flag dropdown listing the site's
 * languages (from Polylang), styled like the booking engine's. Each link goes
 * to the translation of the page being viewed.
 *
 * Attributes: align="right|left" (which edge the menu lines up with),
 * theme="dark|light" (dark = for a teal/dark header, light = for a white one),
 * code="on|off" (show the language code next to the flag).
 *
 * @param array<string,mixed> $atts
 */
function kbs_render_language_switcher($atts = []): string
{
    $atts = shortcode_atts(['align' => 'right', 'theme' => 'dark', 'code' => 'off'], $atts, 'karuna_language_switcher');

    if (!function_exists('pll_the_languages')) {
        // No Polylang: nothing to switch between. Tell editors, stay silent for visitors.
        return current_user_can('manage_options')
            ? '<span class="kbs-lang-missing">[Language switcher: install and activate Polylang]</span>'
            : '';
    }

    $items = pll_the_languages(['raw' => 1, 'hide_if_empty' => 0, 'hide_if_no_translation' => 0]);
    if (!is_array($items) || count($items) < 2) {
        return '';
    }

    $known   = kbs_languages();
    $current = null;
    $rows    = [];
    foreach ($items as $slug => $item) {
        $code = kbs_normalize_lang((string) ($item['locale'] ?? '')) ?? kbs_normalize_lang((string) $slug);
        if ($code === null) {
            continue;
        }
        $row = [
            'code'    => $code,
            'slug'    => (string) $slug,
            'name'    => $known[$code][0],
            'flag'    => plugins_url('assets/flags/' . $known[$code][1] . '.svg', KBS_FILE),
            'url'     => (string) ($item['url'] ?? home_url('/')),
            'current' => !empty($item['current_lang']),
            'locale'  => (string) ($item['locale'] ?? $slug),
        ];
        $rows[] = $row;
        if ($row['current']) {
            $current = $row;
        }
    }
    if (count($rows) < 2) {
        return '';
    }
    $current ??= $rows[0];

    kbs_enqueue_switcher_assets();

    $theme = $atts['theme'] === 'light' ? 'light' : 'dark';
    $align = $atts['align'] === 'left' ? 'left' : 'right';

    ob_start();
    ?>
    <details class="kbs-lang kbs-lang--<?php echo esc_attr($theme); ?> kbs-lang--<?php echo esc_attr($align); ?>" data-kbs-lang-menu>
        <summary aria-label="<?php echo esc_attr__('Language', 'booking-engine'); ?>" title="<?php echo esc_attr($current['name']); ?>">
            <img src="<?php echo esc_url($current['flag']); ?>" alt="" width="24" height="24">
            <?php if (strtolower((string) $atts['code']) === 'on') : ?>
                <span class="kbs-lang-code"><?php echo esc_html(strtoupper(explode('-', $current['code'])[0])); ?></span>
            <?php endif; ?>
        </summary>
        <ul>
            <?php foreach ($rows as $row) : ?>
                <li>
                    <a href="<?php echo esc_url($row['url']); ?>" lang="<?php echo esc_attr(str_replace('_', '-', $row['locale'])); ?>"
                       hreflang="<?php echo esc_attr(str_replace('_', '-', $row['locale'])); ?>"
                       <?php echo $row['current'] ? 'aria-current="true" class="is-current"' : ''; ?>>
                        <img src="<?php echo esc_url($row['flag']); ?>" alt="" width="20" height="20" loading="lazy">
                        <span><?php echo esc_html($row['name']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php
    return (string) ob_get_clean();
}
add_shortcode('karuna_language_switcher', 'kbs_render_language_switcher');

function kbs_enqueue_switcher_assets(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    wp_register_style('kbs-lang', false, [], KBS_VERSION);
    wp_enqueue_style('kbs-lang');
    wp_add_inline_style('kbs-lang', kbs_asset('kbs-lang.css'));

    wp_register_script('kbs-lang', false, [], KBS_VERSION, true);
    wp_enqueue_script('kbs-lang');
    wp_add_inline_script('kbs-lang', kbs_asset('kbs-lang.js'));
}
