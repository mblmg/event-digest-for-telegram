<?php
/**
 * Minimal client for the Telegram Bot API.
 */

defined('ABSPATH') || exit;

class IMM_TG_Telegram
{
    /**
     * Calls a Bot API method.
     *
     * @return array|WP_Error The "result" part of the response.
     */
    public static function call(string $method, array $params = [], string $token = '')
    {
        $token = $token !== '' ? $token : (string) IMM_TG_Settings::get('token');
        if ($token === '') {
            return new WP_Error('imm_tg_no_token', __('No bot token saved.', 'event-digest-for-telegram'));
        }
        $response = wp_remote_post('https://api.telegram.org/bot' . $token . '/' . $method, [
            'timeout' => 20,
            'body'    => $params,
        ]);
        if (is_wp_error($response)) {
            // Never pass on the request URL: it contains the token.
            return new WP_Error('imm_tg_http', __('Telegram could not be reached.', 'event-digest-for-telegram'));
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['ok'])) {
            $description = is_array($body) && !empty($body['description']) ? $body['description'] : wp_remote_retrieve_response_message($response);
            /* translators: %s: error message from Telegram */
            return new WP_Error('imm_tg_api', sprintf(__('Telegram reports: %s', 'event-digest-for-telegram'), $description));
        }
        return $body['result'];
    }

    /**
     * Posts a message. With $preview_url, Telegram shows the preview (image, title,
     * description) of that page, like when a link is posted by hand.
     *
     * @return array|WP_Error
     */
    public static function send(string $html, string $preview_url = '')
    {
        $position = (string) IMM_TG_Settings::get('link_preview');
        if ($preview_url !== '' && $position !== 'none') {
            $preview = [
                'url'                => $preview_url,
                'prefer_large_media' => true,
                'show_above_text'    => $position === 'above',
            ];
        } else {
            $preview = ['is_disabled' => true];
        }
        $params = [
            'chat_id'              => IMM_TG_Settings::get('chat_id'),
            'text'                 => $html,
            'parse_mode'           => 'HTML',
            'link_preview_options' => wp_json_encode($preview),
        ];
        $thread = (string) IMM_TG_Settings::get('thread_id');
        if ($thread !== '') {
            $params['message_thread_id'] = $thread;
        }
        return self::call('sendMessage', $params);
    }

    /**
     * Chats and topics the bot has recently seen, to find the chat ID and topic ID.
     *
     * @return array|WP_Error List of ['id', 'title', 'type', 'topics' => [id => name]].
     */
    public static function recent_chats()
    {
        $updates = self::call('getUpdates', [
            'allowed_updates' => wp_json_encode(['message', 'channel_post', 'my_chat_member']),
            'limit'           => 100,
        ]);
        if (is_wp_error($updates)) {
            return $updates;
        }
        $chats = [];
        foreach ($updates as $update) {
            $item = $update['message'] ?? $update['channel_post'] ?? $update['my_chat_member'] ?? null;
            if (!is_array($item) || empty($item['chat']['id'])) {
                continue;
            }
            $chat = $item['chat'];
            $id   = (string) $chat['id'];
            if (!isset($chats[$id])) {
                $title = $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''));
                $chats[$id] = ['id' => $id, 'title' => $title, 'type' => $chat['type'] ?? '', 'topics' => []];
            }
            if (!empty($item['is_topic_message']) && !empty($item['message_thread_id'])) {
                $name = $item['reply_to_message']['forum_topic_created']['name']
                    ?? $item['forum_topic_created']['name']
                    ?? '';
                $chats[$id]['topics'][(string) $item['message_thread_id']] = $name;
            }
        }
        return array_values($chats);
    }
}
