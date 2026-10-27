<?php
/**
 * Weekly schedule via WP-Cron.
 *
 * WP-Cron runs when someone visits the site. On sites with little traffic the post can
 * be late; a real cron job calling wp-cron.php makes it punctual.
 */

defined('ABSPATH') || exit;

class IMM_TG_Scheduler
{
    const HOOK = 'imm_tg_weekly_digest';

    public static function init(): void
    {
        add_action(self::HOOK, [self::class, 'run']);
        add_action('init', [self::class, 'ensure_scheduled']);
        add_action('update_option_' . IMM_TG_Settings::OPTION, [self::class, 'reschedule']);
        add_action('add_option_' . IMM_TG_Settings::OPTION, [self::class, 'reschedule']);
    }

    public static function ensure_scheduled(): void
    {
        $active = IMM_TG_Settings::get('enabled') && IMM_TG_Settings::is_ready();
        $next   = wp_next_scheduled(self::HOOK);
        if ($active && !$next) {
            wp_schedule_single_event(self::next_run(), self::HOOK);
        } elseif (!$active && $next) {
            self::unschedule();
        }
    }

    public static function reschedule(): void
    {
        self::unschedule();
        self::ensure_scheduled();
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    /** Next configured weekday and time after now, as Unix time. */
    public static function next_run(): int
    {
        $tz  = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);
        [$hour, $minute] = array_map('intval', explode(':', (string) IMM_TG_Settings::get('time')));
        $weekday = (int) IMM_TG_Settings::get('weekday');

        $run = $now->setTime($hour, $minute);
        $diff = ($weekday - (int) $run->format('N') + 7) % 7;
        $run  = $run->modify('+' . $diff . ' days');
        if ($run <= $now) {
            $run = $run->modify('+7 days');
        }
        return $run->getTimestamp();
    }

    public static function run(): void
    {
        $state = IMM_TG_Settings::state();
        $now   = time();

        // Guard against double posts, e.g. if cron fires twice.
        if ($state['last_sent'] && $now - $state['last_sent'] < 6 * DAY_IN_SECONDS) {
            self::reschedule();
            return;
        }

        $result = IMM_TG_Digest::post();
        if ($result === null) {
            IMM_TG_Settings::update_state([
                'last_run'    => $now,
                'last_result' => __('Nothing posted: no events in the coming days and nothing new.', 'event-digest-for-telegram'),
            ]);
        } else {
            if (is_wp_error($result)) {
                IMM_TG_Settings::update_state([
                    'last_run'    => $now,
                    /* translators: %s: error message */
                    'last_result' => sprintf(__('Error: %s', 'event-digest-for-telegram'), $result->get_error_message()),
                ]);
            } else {
                IMM_TG_Settings::update_state([
                    'last_run'    => $now,
                    'last_sent'   => $now,
                    'last_result' => __('Posted.', 'event-digest-for-telegram'),
                ]);
            }
        }
        self::reschedule();
    }
}
