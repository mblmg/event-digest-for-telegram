<?php
/**
 * Settings and state.
 */

defined('ABSPATH') || exit;

class IMM_TG_Settings
{
    const OPTION = 'imm_tg_settings';
    const STATE  = 'imm_tg_state';

    public static function defaults(): array
    {
        return [
            'enabled'     => 0,
            'token'       => '',
            'chat_id'     => '',
            'thread_id'   => '',
            'weekday'     => 1,       // 1 = Monday … 7 = Sunday (ISO-8601).
            'time'        => '10:00',
            'days_ahead'  => 7,
            'include_new' => 1,
            'link_preview' => 'above', // Preview with image for the next event: above, below or none.
            'intro'       => '',
            'heading_week' => '',     // Empty = translated default.
            'heading_new'  => '',
            'program_url' => home_url('/'),
        ];
    }

    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    /** @return mixed */
    public static function get(string $key)
    {
        $all = self::all();
        return $all[$key] ?? null;
    }

    public static function sanitize($input): array
    {
        $old   = self::all();
        $input = is_array($input) ? $input : [];

        // An empty token field keeps the saved token, so it never has to be shown again.
        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '') {
            $token = $old['token'];
        } elseif (!preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $token)) {
            if (function_exists('add_settings_error')) {
                add_settings_error(self::OPTION, 'token', __('The bot token does not look valid. It was not saved.', 'event-digest-for-telegram'));
            }
            $token = $old['token'];
        }
        if (!empty($input['delete_token'])) {
            $token = '';
        }

        $time = preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim((string) ($input['time'] ?? '')), $m)
            ? sprintf('%02d:%02d', $m[1], $m[2])
            : '10:00';

        return [
            'enabled'      => empty($input['enabled']) ? 0 : 1,
            'token'        => $token,
            'chat_id'      => preg_replace('/[^0-9\-@A-Za-z_]/', '', (string) ($input['chat_id'] ?? '')),
            'thread_id'    => preg_replace('/\D/', '', (string) ($input['thread_id'] ?? '')),
            'weekday'      => min(7, max(1, absint($input['weekday'] ?? 1))),
            'time'         => $time,
            'days_ahead'   => min(31, max(1, absint($input['days_ahead'] ?? 7))),
            'include_new'  => empty($input['include_new']) ? 0 : 1,
            'link_preview' => in_array($input['link_preview'] ?? '', ['above', 'below', 'none'], true) ? $input['link_preview'] : 'above',
            'intro'        => sanitize_textarea_field($input['intro'] ?? ''),
            'heading_week' => sanitize_text_field($input['heading_week'] ?? ''),
            'heading_new'  => sanitize_text_field($input['heading_new'] ?? ''),
            'program_url'  => esc_url_raw($input['program_url'] ?? '') ?: home_url('/'),
        ];
    }

    /** Token and chat are set. */
    public static function is_ready(): bool
    {
        return self::get('token') !== '' && self::get('chat_id') !== '';
    }

    public static function state(): array
    {
        $state = get_option(self::STATE, []);
        return wp_parse_args(is_array($state) ? $state : [], [
            'last_sent'   => 0,   // Unix time of the last scheduled digest that was sent.
            'last_run'    => 0,   // Unix time of the last scheduled run.
            'last_result' => '',  // Short status text of the last run.
        ]);
    }

    public static function update_state(array $changes): void
    {
        update_option(self::STATE, array_merge(self::state(), $changes), false);
    }
}
