<?php
/**
 * Selects the events for the weekly preview and builds the Telegram message.
 *
 * Part 1: events in the coming days (setting "days_ahead").
 * Part 2: events published since the last digest that are not already in part 1.
 * No message if both parts are empty.
 */

defined('ABSPATH') || exit;

class IMM_TG_Digest
{
    const MAX_LENGTH = 4000; // Telegram allows 4096 characters per message.

    /**
     * @param int|null $since Unix time: events published after this count as new.
     *                        Null = time of the last digest, or the last 7 days before the first one.
     * @return array{week: IMM_TK_Event[], new: IMM_TK_Event[]}
     */
    public static function select(?int $since = null): array
    {
        if (!class_exists('IMM_TK_Endpoints') || !IMM_TK_Settings::is_configured()) {
            return ['week' => [], 'new' => []];
        }
        $tz    = wp_timezone();
        $now   = new DateTimeImmutable('now', $tz);
        $until = (new DateTimeImmutable('today', $tz))->modify('+' . (int) IMM_TG_Settings::get('days_ahead') . ' days');

        if ($since === null) {
            $last  = (int) IMM_TG_Settings::state()['last_sent'];
            $since = $last ?: $now->modify('-7 days')->getTimestamp();
        }

        $week = [];
        $new  = [];
        foreach (IMM_TK_Endpoints::feed_events() as $event) {
            if ($event->end <= $now) {
                continue; // Over.
            }
            if ($event->start < $until) {
                $week[] = $event;
                continue;
            }
            if (IMM_TG_Settings::get('include_new')) {
                $published = get_post_time('U', true, $event->id);
                if ($published && $published > $since) {
                    $new[] = $event;
                }
            }
        }

        $sort = function (IMM_TK_Event $a, IMM_TK_Event $b) {
            return $a->start <=> $b->start;
        };
        usort($week, $sort);
        usort($new, $sort);

        return apply_filters('imm_tg_digest_events', ['week' => $week, 'new' => $new]);
    }

    /** Builds the message in Telegram HTML. Empty string if there is nothing to post. */
    public static function message(?array $selection = null): string
    {
        $selection = $selection ?? self::select();
        if (!$selection['week'] && !$selection['new']) {
            return '';
        }

        $blocks = [];
        $intro  = trim((string) IMM_TG_Settings::get('intro'));
        if ($intro !== '') {
            $blocks[] = self::esc($intro);
        }

        if ($selection['week']) {
            $heading = IMM_TG_Settings::get('heading_week') ?: __('This week', 'event-digest-for-telegram');
            $items   = [];
            foreach ($selection['week'] as $event) {
                $lines = [
                    '<b>' . self::esc(self::date($event)) . '</b>',
                    self::link($event),
                ];
                $details = array_filter([self::time($event), self::venue($event)]);
                if ($details) {
                    $lines[] = self::esc(implode(', ', $details));
                }
                $items[] = implode("\n", $lines);
            }
            $blocks[] = '<b>' . self::esc($heading) . "</b>\n\n" . implode("\n\n", $items);
        }

        if ($selection['new']) {
            $heading = IMM_TG_Settings::get('heading_new') ?: __('New in the programme', 'event-digest-for-telegram');
            $items   = [];
            foreach ($selection['new'] as $event) {
                $items[] = self::esc(self::date($event)) . ': ' . self::link($event);
            }
            $blocks[] = '<b>' . self::esc($heading) . "</b>\n" . implode("\n", $items);
        }

        $url = (string) IMM_TG_Settings::get('program_url');
        if ($url !== '') {
            $label    = preg_replace('#^https?://(www\.)?#', '', untrailingslashit($url));
            $blocks[] = sprintf(
                /* translators: %s: link to the programme page */
                self::esc(__('Full programme and calendar subscription: %s', 'event-digest-for-telegram')),
                '<a href="' . esc_url($url) . '">' . self::esc($label) . '</a>'
            );
        }

        $message = implode("\n\n", $blocks);

        // Too long: drop events from the end of the lists until it fits.
        if (mb_strlen($message) > self::MAX_LENGTH) {
            if (count($selection['new']) > 0) {
                array_pop($selection['new']);
            } else {
                array_pop($selection['week']);
            }
            return self::message($selection);
        }

        return (string) apply_filters('imm_tg_message', $message, $selection);
    }

    private static function date(IMM_TK_Event $event): string
    {
        /* translators: date format for the event date in the message, see https://www.php.net/manual/datetime.format.php */
        $format = __('D, F j', 'event-digest-for-telegram');
        return wp_date($format, $event->start->getTimestamp(), $event->start->getTimezone());
    }

    private static function time(IMM_TK_Event $event): string
    {
        if ($event->all_day) {
            return __('all day', 'event-digest-for-telegram');
        }
        $format = get_option('time_format') ?: 'H:i';
        $tz     = $event->start->getTimezone();
        $start  = wp_date($format, $event->main_start->getTimestamp(), $tz);
        if ($event->doors && $event->doors < $event->main_start) {
            return sprintf(
                /* translators: 1: doors time, 2: start time */
                __('doors %1$s, start %2$s', 'event-digest-for-telegram'),
                wp_date($format, $event->doors->getTimestamp(), $tz),
                $start
            );
        }
        /* translators: %s: start time */
        return sprintf(__('at %s', 'event-digest-for-telegram'), $start);
    }

    /** Venue name, unless it is the default venue (no need to repeat it every week). */
    private static function venue(IMM_TK_Event $event): string
    {
        $venue = $event->location_parts[0] ?? '';
        if ($venue === '' || $venue === (string) IMM_TK_Settings::get('venue_name')) {
            return '';
        }
        return $venue;
    }

    private static function link(IMM_TK_Event $event): string
    {
        return '<a href="' . esc_url($event->url) . '">' . self::esc($event->title) . '</a>';
    }

    /** Escapes text for Telegram's HTML mode (only &, < and > are special). */
    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    }
}
