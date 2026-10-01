<?php
/**
 * Plugin Name:       Karuna Booking Search
 * Plugin URI:        https://github.com/ledorofficial/karuna-booking-search
 * Description:        Branded availability search and per-room calendar (replaces the Smoobu widgets). A compact one-month search calendar with live nightly prices, plus a read-only per-room calendar, both pulled from bookings.karunasiargao.com. Use [karuna_booking_search], [karuna_room_calendar], or the "Karuna Booking Search" widget.
 * Version:           1.9.0
 * Author:            Karuna Siargao
 * License:           GPL-2.0-or-later
 * Text Domain:       karuna-booking-search
 * Update URI:        https://github.com/ledorofficial/karuna-booking-search
 */

if (!defined('ABSPATH')) {
    exit;
}

const KBS_VERSION      = '1.9.0';
const KBS_FLATPICKR    = '4.6.13';
const KBS_DEFAULT_BASE = 'https://bookings.karunasiargao.com/';
const KBS_CALENDAR_API = 'https://bookings.karunasiargao.com/api/calendar';
const KBS_REPO         = 'https://github.com/ledorofficial/karuna-booking-search/';

/**
 * One-click updates straight from the public GitHub repo: bump the Version
 * header, push to main, and WordPress shows the normal "update available"
 * notice in Plugins. No token needed while the repo is public.
 */
if (is_readable(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
    require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

    $kbsUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        KBS_REPO,
        __FILE__,
        'karuna-booking-search'
    );
    $kbsUpdateChecker->setBranch('main');
}

/**
 * Render the search widget.
 *
 * @param array<string,mixed> $atts
 */
function kbs_render_widget($atts = []): string
{
    $atts = shortcode_atts([
        'base'       => KBS_DEFAULT_BASE, // booking site; prices/availability are read from its /api/calendar
        'target'     => '_self',
        'max_guests' => 16,
        'prices'     => 'on',       // "off" hides the per-night prices in the calendar
        'layout'     => 'stacked',  // "stacked" (teal card) or "inline" (light horizontal row)
        'button'     => 'Search',   // submit button label
        'room'       => '',         // Smoobu room id: that room's prices + capacity, lands on that room
    ], $atts, 'karuna_booking_search');

    $base       = esc_url($atts['base']);
    $target     = $atts['target'] === '_blank' ? '_blank' : '_self';
    $max_guests = max(1, (int) $atts['max_guests']);
    $prices     = strtolower((string) $atts['prices']) === 'off' ? 'off' : 'on';
    $layout     = strtolower((string) $atts['layout']) === 'inline' ? 'inline' : 'stacked';
    $button     = trim((string) $atts['button']) !== '' ? trim((string) $atts['button']) : 'Search';
    $room       = max(0, (int) $atts['room']);
    $uid        = 'kbs-' . wp_generate_password(6, false, false);
    $action     = $room ? trailingslashit($base) . '#room-' . $room : $base;
    $api        = trailingslashit($base) . 'api/calendar' . ($room ? '?room=' . $room : '');

    kbs_enqueue_assets();

    ob_start();
    ?>
    <form class="kbs-widget kbs-widget--<?php echo esc_attr($layout); ?>" id="<?php echo esc_attr($uid); ?>"
          action="<?php echo esc_url($action); ?>" method="get"
          target="<?php echo esc_attr($target); ?>"
          data-kbs data-kbs-prices="<?php echo esc_attr($prices); ?>"<?php echo $room ? ' data-kbs-room="' . (int) $room . '"' : ''; ?>
          data-kbs-api="<?php echo esc_url($api); ?>">
        <div class="kbs-field kbs-field--date">
            <label for="<?php echo esc_attr($uid); ?>-in">Arrival</label>
            <input type="text" id="<?php echo esc_attr($uid); ?>-in"
                   placeholder="Add date" autocomplete="off" readonly data-kbs-checkin>
        </div>
        <div class="kbs-field kbs-field--date">
            <label for="<?php echo esc_attr($uid); ?>-out">Departure</label>
            <input type="text" id="<?php echo esc_attr($uid); ?>-out"
                   placeholder="Add date" autocomplete="off" readonly data-kbs-checkout>
        </div>
        <div class="kbs-field kbs-field--guests">
            <label>Guests</label>
            <details class="kbs-guests">
                <summary>
                    <span data-kbs-guests-label>2 guests</span>
                    <svg viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
                </summary>
                <div class="kbs-guests-pop">
                    <?php foreach ([['adults', 'Adults', 2], ['children', 'Children', 0]] as [$k, $lbl, $start]) : ?>
                        <div class="kbs-guests-row">
                            <span><?php echo esc_html($lbl); ?></span>
                            <span class="kbs-stepper">
                                <button type="button" data-kbs-step="-1" data-kbs-target="<?php echo esc_attr($k); ?>" aria-label="Fewer <?php echo esc_attr($lbl); ?>">&minus;</button>
                                <span data-kbs-val="<?php echo esc_attr($k); ?>"><?php echo (int) $start; ?></span>
                                <button type="button" data-kbs-step="1" data-kbs-target="<?php echo esc_attr($k); ?>" aria-label="More <?php echo esc_attr($lbl); ?>">+</button>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
            <input type="hidden" name="guests" value="2" data-kbs-guests data-kbs-max="<?php echo esc_attr($max_guests); ?>">
        </div>
        <div class="kbs-field kbs-field--submit">
            <button type="submit"><?php echo esc_html($button); ?></button>
        </div>
        <input type="hidden" name="arrival" data-kbs-arrival>
        <input type="hidden" name="departure" data-kbs-departure>
    </form>
    <?php
    return (string) ob_get_clean();
}

add_shortcode('karuna_booking_search', 'kbs_render_widget');

/** Register the flatpickr libs + the widget's own inline CSS/JS. */
function kbs_enqueue_assets(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $cdn = 'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/' . KBS_FLATPICKR;

    wp_enqueue_style('flatpickr', $cdn . '/flatpickr.min.css', [], KBS_FLATPICKR);
    wp_enqueue_script('flatpickr', $cdn . '/flatpickr.min.js', [], KBS_FLATPICKR, true);
    wp_enqueue_script('flatpickr-range', $cdn . '/plugins/rangePlugin.min.js', ['flatpickr'], KBS_FLATPICKR, true);

    wp_register_style('kbs-widget', false, ['flatpickr'], KBS_VERSION);
    wp_enqueue_style('kbs-widget');
    wp_add_inline_style('kbs-widget', kbs_asset('kbs.css'));

    wp_register_script('kbs-widget', false, ['flatpickr', 'flatpickr-range'], KBS_VERSION, true);
    wp_enqueue_script('kbs-widget');
    wp_add_inline_script('kbs-widget', str_replace(
        '__KBS_API__',
        esc_url_raw(KBS_CALENDAR_API),
        kbs_asset('kbs.js')
    ));
}

/**
 * Render a single room's own availability calendar (branded replacement for
 * Smoobu's embeddable per-apartment calendar iframe). Read-only: shows a
 * couple of months with sold-out nights shaded, no date picking or form.
 *
 * @param array<string,mixed> $atts
 */
function kbs_render_room_calendar($atts = []): string
{
    $atts = shortcode_atts([
        'room_id' => '',
        'months'  => 6,
        'base'    => KBS_DEFAULT_BASE,
    ], $atts, 'karuna_room_calendar');

    $roomId = (int) $atts['room_id'];
    if ($roomId <= 0) {
        return '';
    }
    $months = max(2, min(12, (int) $atts['months']));
    $base   = esc_url($atts['base']);
    $uid    = 'kbs-room-' . wp_generate_password(6, false, false);

    kbs_enqueue_room_calendar_assets();

    return sprintf(
        '<div class="kbs-room-cal" id="%1$s" data-kbs-room data-room-id="%2$d" data-months="%3$d" data-base="%4$s"><div class="kbs-room-cal-months"></div></div>',
        esc_attr($uid),
        $roomId,
        $months,
        esc_attr($base)
    );
}

add_shortcode('karuna_room_calendar', 'kbs_render_room_calendar');

/** Register the room-calendar's own inline CSS/JS (no flatpickr needed — read-only grid). */
function kbs_enqueue_room_calendar_assets(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    wp_register_style('kbs-room-calendar', false, [], KBS_VERSION);
    wp_enqueue_style('kbs-room-calendar');
    wp_add_inline_style('kbs-room-calendar', kbs_asset('kbs-room.css'));

    wp_register_script('kbs-room-calendar', false, [], KBS_VERSION, true);
    wp_enqueue_script('kbs-room-calendar');
    wp_add_inline_script('kbs-room-calendar', str_replace(
        '__KBS_API__',
        esc_url_raw(KBS_DEFAULT_BASE),
        kbs_asset('kbs-room.js')
    ));
}

/** Read a shared asset file (kept identical to the standalone snippet). */
function kbs_asset(string $file): string
{
    return (string) file_get_contents(__DIR__ . '/assets/' . $file);
}

/** Classic widget — also works in the block editor via the "Legacy Widget" block. */
add_action('widgets_init', fn () => register_widget('KBS_Widget'));

class KBS_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('kbs_widget', 'Karuna Booking Search', [
            'description' => 'Branded availability search that submits to bookings.karunasiargao.com',
        ]);
    }

    /** @param array<string,mixed> $args @param array<string,mixed> $instance */
    public function widget($args, $instance): void
    {
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . esc_html($instance['title']) . $args['after_title'];
        }
        echo kbs_render_widget([
            'target'     => !empty($instance['new_tab']) ? '_blank' : '_self',
            'max_guests' => !empty($instance['max_guests']) ? (int) $instance['max_guests'] : 16,
            'prices'     => !empty($instance['hide_prices']) ? 'off' : 'on',
            'layout'     => ($instance['layout'] ?? 'stacked') === 'inline' ? 'inline' : 'stacked',
            'button'     => !empty($instance['button']) ? $instance['button'] : 'Search',
        ]);
        echo $args['after_widget'];
    }

    /** @param array<string,mixed> $instance */
    public function form($instance): void
    {
        $title       = $instance['title'] ?? '';
        $max_guests  = $instance['max_guests'] ?? 16;
        $new_tab     = !empty($instance['new_tab']);
        $hide_prices = !empty($instance['hide_prices']);
        $layout      = ($instance['layout'] ?? 'stacked') === 'inline' ? 'inline' : 'stacked';
        $button      = $instance['button'] ?? 'Search';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Title:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('layout')); ?>">Layout:</label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('layout')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('layout')); ?>">
                <option value="stacked" <?php selected($layout, 'stacked'); ?>>Stacked — teal card (Smoobu-style)</option>
                <option value="inline" <?php selected($layout, 'inline'); ?>>Inline — light horizontal row (homepage)</option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('button')); ?>">Button label:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('button')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('button')); ?>" type="text"
                   value="<?php echo esc_attr($button); ?>" placeholder="Search">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('max_guests')); ?>">Max guests (Adults + Children):</label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('max_guests')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('max_guests')); ?>" type="number" min="1" max="99"
                   value="<?php echo esc_attr($max_guests); ?>">
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('new_tab')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('new_tab')); ?>" <?php checked($new_tab); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('new_tab')); ?>">Open results in a new tab</label>
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('hide_prices')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('hide_prices')); ?>" <?php checked($hide_prices); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('hide_prices')); ?>">Hide per-night prices in the calendar</label>
        </p>
        <?php
    }

    /** @param array<string,mixed> $new @param array<string,mixed> $old @return array<string,mixed> */
    public function update($new, $old): array
    {
        return [
            'title'       => sanitize_text_field($new['title'] ?? ''),
            'max_guests'  => max(1, (int) ($new['max_guests'] ?? 16)),
            'new_tab'     => !empty($new['new_tab']),
            'hide_prices' => !empty($new['hide_prices']),
            'layout'      => ($new['layout'] ?? '') === 'inline' ? 'inline' : 'stacked',
            'button'      => sanitize_text_field($new['button'] ?? 'Search'),
        ];
    }
}
