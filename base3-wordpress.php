<?php declare(strict_types=1);
/**
 * Plugin Name: BASE3 WordPress
 * Description: Embeds the complete BASE3 runtime in WordPress.
 * Version: 0.3.0
 * Requires PHP: 8.2
 * Text Domain: base3-wordpress
 * License: GPL-3.0-or-later
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/includes/Runtime.php';
\Base3WordPressBridge\Runtime::register(__FILE__);
