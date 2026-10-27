<?php
/**
 * Settings page under Settings → Telegram Digest.
 */

defined('ABSPATH') || exit;

class IMM_TG_Admin
{
    const PAGE = 'event-digest-for-telegram';

    public static function init(): void
    {
        add_action('admin_menu', function () {
            add_options_page(
                __('Event Digest for Telegram', 'event-digest-for-telegram'),
                __('Telegram Digest', 'event-digest-for-telegram'),
                'manage_options',
                self::PAGE,
                [self::class, 'render']
            );
        });
        add_action('admin_init', function () {
            register_setting('imm_tg', IMM_TG_Settings::OPTION, [
                'type'              => 'array',
                'sanitize_callback' => [IMM_TG_Settings::class, 'sanitize'],
                'default'           => IMM_TG_Settings::defaults(),
            ]);
        });
        add_action('admin_post_imm_tg_find_chats', [self::class, 'handle_find_chats']);
        add_action('admin_post_imm_tg_send_test', [self::class, 'handle_send_test']);
        add_action('admin_notices', [self::class, 'dependency_notice']);
        add_filter('plugin_action_links_' . plugin_basename(IMM_TG_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(self::url()) . '">' . esc_html__('Settings', 'event-digest-for-telegram') . '</a>');
            return $links;
        });
    }

    private static function url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::PAGE], $args), admin_url('options-general.php'));
    }

    public static function dependency_notice(): void
    {
        if (class_exists('IMM_TK_Event') || !current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-warning"><p>'
            . esc_html__('Event Digest for Telegram needs the plugin "Add to Calendar from Custom Fields", which provides the events.', 'event-digest-for-telegram')
            . '</p></div>';
    }

    /** Asks Telegram which chats and topics the bot has seen. */
    public static function handle_find_chats(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'event-digest-for-telegram'));
        }
        check_admin_referer('imm_tg_find_chats');

        $me    = IMM_TG_Telegram::call('getMe');
        $chats = is_wp_error($me) ? $me : IMM_TG_Telegram::recent_chats();
        set_transient('imm_tg_chats_' . get_current_user_id(), [
            'bot'   => is_wp_error($me) ? '' : ($me['username'] ?? ''),
            'chats' => $chats,
        ], HOUR_IN_SECONDS);

        wp_safe_redirect(self::url(['imm_tg' => 'chats']) . '#imm-tg-chats');
        exit;
    }

    /** Sends the current preview to the configured chat. Does not count as the weekly post. */
    public static function handle_send_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'event-digest-for-telegram'));
        }
        check_admin_referer('imm_tg_send_test');

        $result = IMM_TG_Digest::post();
        if ($result === null) {
            $status = 'empty';
        } else {
            if (is_wp_error($result)) {
                set_transient('imm_tg_error_' . get_current_user_id(), $result->get_error_message(), 5 * MINUTE_IN_SECONDS);
                $status = 'error';
            } else {
                $status = 'sent';
            }
        }
        wp_safe_redirect(self::url(['imm_tg' => $status]) . '#imm-tg-preview');
        exit;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s     = IMM_TG_Settings::all();
        $name  = IMM_TG_Settings::OPTION;
        $field = function (string $key) use ($name) {
            return esc_attr($name . '[' . $key . ']');
        };
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects which notice to show.
        $notice = isset($_GET['imm_tg']) ? sanitize_key(wp_unslash($_GET['imm_tg'])) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Event Digest for Telegram', 'event-digest-for-telegram'); ?></h1>
            <p><?php esc_html_e('Posts a weekly preview to Telegram: the events of the coming days and everything newly added to the programme. Nothing is posted in weeks without either.', 'event-digest-for-telegram'); ?></p>

            <?php self::render_notice($notice); ?>
            <?php self::render_status(); ?>

            <form method="post" action="options.php">
                <?php settings_fields('imm_tg'); ?>

                <h2><?php esc_html_e('Bot and chat', 'event-digest-for-telegram'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="imm-tg-token"><?php esc_html_e('Bot token', 'event-digest-for-telegram'); ?></label></th>
                        <td>
                            <input type="password" class="regular-text" id="imm-tg-token" name="<?php echo $field('token'); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $field. ?>" value="" autocomplete="off"
                                   placeholder="<?php echo $s['token'] !== '' ? esc_attr__('Saved. Leave empty to keep it.', 'event-digest-for-telegram') : '123456789:AA…'; ?>">
                            <?php if ($s['token'] !== '') : ?>
                                <label style="margin-left:1em"><input type="checkbox" name="<?php echo $field('delete_token'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="1"> <?php esc_html_e('Delete token', 'event-digest-for-telegram'); ?></label>
                            <?php endif; ?>
                            <p class="description"><?php echo wp_kses(__('Create a bot with <code>@BotFather</code> in Telegram (command <code>/newbot</code>) and paste the token it gives you. Keep the token secret.', 'event-digest-for-telegram'), ['code' => []]); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="imm-tg-chat"><?php esc_html_e('Chat ID', 'event-digest-for-telegram'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="imm-tg-chat" name="<?php echo $field('chat_id'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="<?php echo esc_attr($s['chat_id']); ?>">
                            <p class="description"><?php esc_html_e('ID of the group or channel, e.g. -1001234567890. Use "Find chats" below to look it up.', 'event-digest-for-telegram'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="imm-tg-thread"><?php esc_html_e('Topic ID (optional)', 'event-digest-for-telegram'); ?></label></th>
                        <td>
                            <input type="text" class="small-text" id="imm-tg-thread" name="<?php echo $field('thread_id'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="<?php echo esc_attr($s['thread_id']); ?>">
                            <p class="description"><?php esc_html_e('For groups with topics: the topic the digest goes to. Empty = the general chat.', 'event-digest-for-telegram'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Schedule', 'event-digest-for-telegram'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Weekly digest', 'event-digest-for-telegram'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo $field('enabled'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="1" <?php checked(!empty($s['enabled'])); ?>> <?php esc_html_e('Post automatically every week', 'event-digest-for-telegram'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="imm-tg-weekday"><?php esc_html_e('Day and time', 'event-digest-for-telegram'); ?></label></th>
                        <td>
                            <select id="imm-tg-weekday" name="<?php echo $field('weekday'); // phpcs:ignore WordPress.Security.EscapeOutput ?>">
                                <?php
                                global $wp_locale;
                                for ($day = 1; $day <= 7; $day++) {
                                    printf('<option value="%d" %s>%s</option>', (int) $day, selected((int) $s['weekday'], $day, false), esc_html($wp_locale->get_weekday($day % 7)));
                                }
                                ?>
                            </select>
                            <input type="time" name="<?php echo $field('time'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="<?php echo esc_attr($s['time']); ?>">
                            <p class="description"><?php esc_html_e('In the time zone of the site. WordPress runs scheduled tasks when the site is visited, so on quiet sites the post can be a little late.', 'event-digest-for-telegram'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="imm-tg-days"><?php esc_html_e('Days ahead', 'event-digest-for-telegram'); ?></label></th>
                        <td>
                            <input type="number" class="small-text" id="imm-tg-days" min="1" max="31" name="<?php echo $field('days_ahead'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="<?php echo esc_attr($s['days_ahead']); ?>">
                            <p class="description"><?php esc_html_e('Events starting within this many days are listed in the first part.', 'event-digest-for-telegram'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('New events', 'event-digest-for-telegram'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo $field('include_new'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="1" <?php checked(!empty($s['include_new'])); ?>> <?php esc_html_e('Also list events published since the last digest', 'event-digest-for-telegram'); ?></label></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Message', 'event-digest-for-telegram'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="imm-tg-preview-pos"><?php esc_html_e('Link preview', 'event-digest-for-telegram'); ?></label></th>
                        <td>
                            <select id="imm-tg-preview-pos" name="<?php echo $field('link_preview'); // phpcs:ignore WordPress.Security.EscapeOutput ?>">
                                <option value="above" <?php selected($s['link_preview'], 'above'); ?>><?php esc_html_e('Above the text', 'event-digest-for-telegram'); ?></option>
                                <option value="below" <?php selected($s['link_preview'], 'below'); ?>><?php esc_html_e('Below the text', 'event-digest-for-telegram'); ?></option>
                                <option value="none" <?php selected($s['link_preview'], 'none'); ?>><?php esc_html_e('No preview', 'event-digest-for-telegram'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Shows the preview of the next event page with its image, as when you post a link yourself. Image, title and description come from the page (e.g. set by your SEO plugin).', 'event-digest-for-telegram'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="imm-tg-intro"><?php esc_html_e('Intro (optional)', 'event-digest-for-telegram'); ?></label></th>
                        <td><textarea class="large-text" rows="3" id="imm-tg-intro" name="<?php echo $field('intro'); // phpcs:ignore WordPress.Security.EscapeOutput ?>"><?php echo esc_textarea($s['intro']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="imm-tg-hw"><?php esc_html_e('Heading for the coming days', 'event-digest-for-telegram'); ?></label></th>
                        <td><input type="text" class="regular-text" id="imm-tg-hw" name="<?php echo $field('heading_week'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="<?php echo esc_attr($s['heading_week']); ?>" placeholder="<?php esc_attr_e('This week', 'event-digest-for-telegram'); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="imm-tg-hn"><?php esc_html_e('Heading for new events', 'event-digest-for-telegram'); ?></label></th>
                        <td><input type="text" class="regular-text" id="imm-tg-hn" name="<?php echo $field('heading_new'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="<?php echo esc_attr($s['heading_new']); ?>" placeholder="<?php esc_attr_e('New in the programme', 'event-digest-for-telegram'); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="imm-tg-url"><?php esc_html_e('Programme page', 'event-digest-for-telegram'); ?></label></th>
                        <td>
                            <input type="url" class="regular-text" id="imm-tg-url" name="<?php echo $field('program_url'); // phpcs:ignore WordPress.Security.EscapeOutput ?>" value="<?php echo esc_attr($s['program_url']); ?>">
                            <p class="description"><?php esc_html_e('Linked at the end of every digest.', 'event-digest-for-telegram'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save settings', 'event-digest-for-telegram')); ?>
            </form>

            <?php self::render_chat_finder($notice === 'chats'); ?>
            <?php self::render_preview(); ?>
        </div>
        <?php
    }

    private static function render_notice(string $notice): void
    {
        $messages = [
            'sent'  => ['success', __('The preview was posted to the chat.', 'event-digest-for-telegram')],
            'empty' => ['warning', __('Nothing to post: no events in the coming days and nothing new.', 'event-digest-for-telegram')],
        ];
        if ($notice === 'error') {
            $error = get_transient('imm_tg_error_' . get_current_user_id());
            delete_transient('imm_tg_error_' . get_current_user_id());
            $messages['error'] = ['error', (string) $error];
        }
        if (isset($messages[$notice])) {
            printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($messages[$notice][0]), esc_html($messages[$notice][1]));
        }
    }

    private static function render_status(): void
    {
        $state  = IMM_TG_Settings::state();
        $next   = wp_next_scheduled(IMM_TG_Scheduler::HOOK);
        $format = get_option('date_format') . ' ' . get_option('time_format');
        $rows   = [
            __('Next digest', 'event-digest-for-telegram') => $next ? wp_date($format, $next) : __('not scheduled (turn on the weekly digest and enter token and chat ID)', 'event-digest-for-telegram'),
            __('Last posted', 'event-digest-for-telegram') => $state['last_sent'] ? wp_date($format, $state['last_sent']) : '–',
        ];
        if ($state['last_run']) {
            $rows[__('Last run', 'event-digest-for-telegram')] = wp_date($format, $state['last_run']) . ': ' . $state['last_result'];
        }
        echo '<table class="widefat striped" style="max-width:900px;margin:1em 0"><tbody>';
        foreach ($rows as $label => $value) {
            echo '<tr><th style="width:12em">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_chat_finder(bool $show_result): void
    {
        echo '<h2 id="imm-tg-chats">' . esc_html__('Find chats', 'event-digest-for-telegram') . '</h2>';
        echo '<ol>';
        echo '<li>' . esc_html__('Add the bot to your group (or as administrator to your channel) and allow it to post messages.', 'event-digest-for-telegram') . '</li>';
        echo '<li>' . wp_kses(__('In a group with topics: open the topic and send <code>/start@YourBotName</code> there. In a channel: post any message.', 'event-digest-for-telegram'), ['code' => []]) . '</li>';
        echo '<li>' . esc_html__('Click the button. The chats and topics the bot has seen in the last 24 hours appear below.', 'event-digest-for-telegram') . '</li>';
        echo '</ol>';

        if (IMM_TG_Settings::get('token') === '') {
            echo '<p><em>' . esc_html__('Save the bot token first.', 'event-digest-for-telegram') . '</em></p>';
            return;
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="imm_tg_find_chats">';
        wp_nonce_field('imm_tg_find_chats');
        submit_button(__('Find chats', 'event-digest-for-telegram'), 'secondary', 'submit', false);
        echo '</form>';

        if (!$show_result) {
            return;
        }
        $data = get_transient('imm_tg_chats_' . get_current_user_id());
        if (!$data) {
            return;
        }
        if (is_wp_error($data['chats'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($data['chats']->get_error_message()) . '</p></div>';
            return;
        }
        if ($data['bot']) {
            /* translators: %s: bot username */
            echo '<p>' . esc_html(sprintf(__('Bot: @%s', 'event-digest-for-telegram'), $data['bot'])) . '</p>';
        }
        if (!$data['chats']) {
            echo '<p>' . esc_html__('The bot has not seen any chat yet. Follow the steps above and try again.', 'event-digest-for-telegram') . '</p>';
            return;
        }
        echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>' . esc_html__('Chat', 'event-digest-for-telegram') . '</th><th>' . esc_html__('Type', 'event-digest-for-telegram') . '</th><th>' . esc_html__('Chat ID', 'event-digest-for-telegram') . '</th><th>' . esc_html__('Topics (ID: name)', 'event-digest-for-telegram') . '</th></tr></thead><tbody>';
        foreach ($data['chats'] as $chat) {
            $topics = [];
            foreach ($chat['topics'] as $id => $topic) {
                $topics[] = '<code>' . esc_html($id) . '</code>' . ($topic !== '' ? ': ' . esc_html($topic) : '');
            }
            echo '<tr><td>' . esc_html($chat['title']) . '</td><td>' . esc_html($chat['type']) . '</td><td><code>' . esc_html($chat['id']) . '</code></td><td>' . wp_kses(implode('<br>', $topics), ['code' => [], 'br' => []]) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_preview(): void
    {
        echo '<h2 id="imm-tg-preview">' . esc_html__('Preview', 'event-digest-for-telegram') . '</h2>';
        if (!class_exists('IMM_TK_Event')) {
            return;
        }
        $selection = IMM_TG_Digest::select();
        $message   = IMM_TG_Digest::message($selection);
        if ($message === '') {
            echo '<p>' . esc_html__('Right now there would be nothing to post: no events in the coming days and nothing new.', 'event-digest-for-telegram') . '</p>';
            return;
        }
        echo '<p>' . esc_html__('This is what the digest would look like if it were posted now:', 'event-digest-for-telegram') . '</p>';

        // Approximation of Telegram's link preview: the featured image of the preview event.
        $position = (string) IMM_TG_Settings::get('link_preview');
        $event    = IMM_TG_Digest::preview_event($selection);
        $image    = ($event && $position !== 'none') ? get_the_post_thumbnail_url($event->id, 'medium_large') : '';
        $figure   = $image ? '<img src="' . esc_url($image) . '" alt="" style="display:block;width:100%;height:auto;border-radius:6px;margin:8px 0">' : '';

        echo '<div style="max-width:480px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:12px 16px;line-height:1.5">'
            . ($position === 'above' ? $figure : '')
            . wp_kses(nl2br($message), ['a' => ['href' => []], 'b' => [], 'br' => []])
            . ($position === 'below' ? $figure : '')
            . '</div>';

        if (!IMM_TG_Settings::is_ready()) {
            return;
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:1em">';
        echo '<input type="hidden" name="action" value="imm_tg_send_test">';
        wp_nonce_field('imm_tg_send_test');
        submit_button(__('Post this preview to the chat now', 'event-digest-for-telegram'), 'secondary', 'submit', false);
        echo '<p class="description">' . esc_html__('Goes to the chat and topic above. Does not change the weekly schedule.', 'event-digest-for-telegram') . '</p>';
        echo '</form>';
    }
}
