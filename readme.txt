=== Event Digest for Telegram ===
Contributors: mblmg
Tags: telegram, events, digest, calendar, bot
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Posts a weekly preview of your upcoming events to a Telegram group, topic or channel.

== Description ==

Once a week, your Telegram bot posts what is coming up:

* **This week:** all events in the coming days (default: 7) with date, time (doors and start, if you have both) and a link to the event page.
* **New in the programme:** events published since the last digest that are further ahead.
* A link to your programme page at the end.

In weeks with no upcoming and no new events, nothing is posted.

The events come from the plugin "Add to Calendar from Custom Fields", which reads dates, times and locations from your existing custom fields (ACF, ACPT or plain post meta). Set that plugin up first.

Features:

* Post to a group, a topic in a group with topics, or a channel
* Choose weekday, time and how many days ahead
* Optional intro text and your own headings
* Link preview with image for the next event, like a link you post yourself
* Preview on the settings page, and a button to post the preview right away
* Built-in helper that finds the chat ID and topic ID for you

== Installation ==

1. Install and set up "Add to Calendar from Custom Fields".
2. Install and activate this plugin.
3. In Telegram, create a bot with @BotFather (`/newbot`) and copy the token.
4. Go to Settings → Telegram Digest and paste the token.
5. Add the bot to your group (or as administrator to your channel). In a group with topics, send `/start@YourBotName` in the topic the digest should go to.
6. Click "Find chats", copy the chat ID (and topic ID) into the settings and save.
7. Check the preview, post it once to test, then turn on "Post automatically every week".

== Frequently Asked Questions ==

= The digest was posted late. =

WordPress runs scheduled tasks when someone visits the site. On sites with few visitors, set up a real cron job at your host that calls `wp-cron.php` every 15 minutes.

= Can the same events be posted twice? =

The weekly digest is posted at most once every six days. The test button on the settings page does not count as the weekly digest.

= How does the plugin get updates? =

From the GitHub releases of https://github.com/mblmg/event-digest-for-telegram. New versions appear under Dashboard → Updates like any other plugin update, and automatic updates can be turned on in the plugin list.

= Developer hooks =

* Filter `imm_tg_digest_events` ($selection): change the selected events (`week` and `new`).
* Filter `imm_tg_message` ($message, $selection): change the message (Telegram HTML).

== External services ==

This plugin connects to the Telegram Bot API (api.telegram.org), provided by Telegram. It is needed to post the digest and to find chats.

* When the weekly digest is due, or when an administrator clicks "Post this preview to the chat now", the plugin sends the message text (event titles, dates, times, venues and links to your site) together with the bot token and the chat ID to Telegram.
* When an administrator clicks "Find chats", the plugin asks Telegram for the bot's recent updates (chats and topics the bot was added to or received commands in).

Updates: the plugin asks the GitHub API (api.github.com) up to twice a day whether a new release exists and, when an administrator installs an update, downloads it from github.com. These requests contain no data about your site or its visitors beyond what any web request contains (such as your server's IP address). GitHub's terms: [Terms of Service](https://docs.github.com/site-policy/github-terms/github-terms-of-service), [Privacy Statement](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement).

No data about visitors of your site is sent. Telegram's terms: [Terms of Service](https://telegram.org/tos), [Bot Developer Terms](https://telegram.org/tos/bot-developers), [Privacy Policy](https://telegram.org/privacy).

== Changelog ==

= 1.0.0 =
* First release.
