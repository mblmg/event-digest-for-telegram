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
includes/class-updater.php      updates from GitHub releases (Update URI header)
languages/                      .pot template and bundled German translation
```

The event selection in `IMM_TG_Digest::select()` is meant to be shared with a future e-mail newsletter.

## Releasing

1. Bump the version in three places: plugin header (`Version:`), `IMM_TG_VERSION` and `Stable tag:` in `readme.txt`. Add a changelog entry.
2. Commit, then tag and push: `git tag 1.0.1 && git push origin main --tags`
3. The **Release** workflow checks the version numbers, builds `event-digest-for-telegram.zip` and publishes a GitHub release with it.
4. Installed sites see the update within 12 hours under Dashboard → Updates (or immediately after "Check again").

The release zip must keep the folder name `event-digest-for-telegram`, otherwise WordPress installs the update as a second plugin.

## License

GPL-2.0-or-later
