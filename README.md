# Event Digest for Telegram

WordPress plugin that posts a weekly preview of upcoming events to a Telegram group, topic or channel. It uses the events of [Add to Calendar from Custom Fields](https://github.com/mblmg/add-to-calendar-from-custom-fields) (`IMM_TK_Event`, `IMM_TK_Endpoints::feed_events()`).

User documentation lives in [`readme.txt`](readme.txt).

## Development

```
event-digest-for-telegram.php   bootstrap
includes/class-settings.php     settings (option imm_tg_settings) and state (imm_tg_state)
includes/class-telegram.php     Bot API calls: sendMessage, getUpdates, getMe
includes/class-digest.php       selects the events and builds the message (Telegram HTML)
includes/class-scheduler.php    weekly WP-Cron event imm_tg_weekly_digest
includes/class-admin.php        settings page, chat finder, preview, test post
languages/                      .pot template and bundled German translation
```

The event selection in `IMM_TG_Digest::select()` is meant to be shared with a future e-mail newsletter.

## License

GPL-2.0-or-later
