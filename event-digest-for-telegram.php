<?php
/**
 * Plugin Name:       Event Digest for Telegram
 * Plugin URI:        https://github.com/mblmg/event-digest-for-telegram
 * Description:       Posts a weekly preview of upcoming events to a Telegram group, topic or channel: the events of the coming days plus everything newly added to the programme. Uses the events configured in "Add to Calendar from Custom Fields".
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  add-to-calendar-from-custom-fields
 * Author:            i-make.media
 * Author URI:        https://i-make.media/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       event-digest-for-telegram
 * Domain Path:       /languages
 * Update URI:        https://github.com/mblmg/event-digest-for-telegram
 */

defined('ABSPATH') || exit;

define('IMM_TG_VERSION', '1.0.0');
define('IMM_TG_FILE', __FILE__);
define('IMM_TG_DIR', plugin_dir_path(__FILE__));

require_once IMM_TG_DIR . 'includes/class-settings.php';
require_once IMM_TG_DIR . 'includes/class-telegram.php';
require_once IMM_TG_DIR . 'includes/class-digest.php';
require_once IMM_TG_DIR . 'includes/class-scheduler.php';
require_once IMM_TG_DIR . 'includes/class-admin.php';
require_once IMM_TG_DIR . 'includes/class-updater.php';

// Bundled translations (German).
add_action('init', function () {
    load_plugin_textdomain('event-digest-for-telegram', false, dirname(plugin_basename(IMM_TG_FILE)) . '/languages');
}, 1);

IMM_TG_Scheduler::init();
IMM_TG_Admin::init();
IMM_TG_Updater::init();

register_deactivation_hook(__FILE__, [IMM_TG_Scheduler::class, 'unschedule']);
