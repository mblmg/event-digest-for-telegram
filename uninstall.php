<?php
/**
 * Removes the settings (including the bot token) and the schedule.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('imm_tg_settings');
delete_option('imm_tg_state');
wp_clear_scheduled_hook('imm_tg_weekly_digest');
