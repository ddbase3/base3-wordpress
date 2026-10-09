<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3WordPress\Api\IBase3WordPressSettings;
use RuntimeException;

final class AdminBridge {

	public static function register(): void {
		add_menu_page('BASE3', 'BASE3', 'manage_options', 'base3-status', [self::class, 'render'], 'dashicons-admin-generic', 82);
		add_submenu_page('base3-status', 'BASE3', 'BASE3', 'manage_options', 'base3-status', [self::class, 'render']);
		$toolsHook = add_submenu_page('base3-status', 'BASE3 Werkzeuge', 'Werkzeuge', 'manage_options', 'base3-tools', [self::class, 'renderTools']);
		if ($toolsHook !== false) {
			add_action('admin_enqueue_scripts', static function(string $hook) use ($toolsHook): void {
				if ($hook !== $toolsHook) return;
				self::enqueueToolAssets();
			});
		}
	}

	public static function render(): void {
		if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
		Runtime::boot();
		$modules = Runtime::catalog()->all();
		echo '<div class="wrap"><h1>BASE3 Framework</h1><p>WordPress integration is active.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Module</th><th>Version</th></tr></thead><tbody>';
		foreach ($modules as $name => $module) {
			echo '<tr><td>' . esc_html($name) . '</td><td>' . esc_html($module['version']) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function renderTools(): void {
		if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
		Runtime::boot();
		$container = Runtime::container();
		$settings = $container->get(IBase3WordPressSettings::class);
		if (!$settings instanceof IBase3WordPressSettings) {
			throw new RuntimeException('BASE3 WordPress administration settings service is unavailable.');
		}

		$display = $container->get(IClassMap::class)->getInstanceByInterfaceName(IDisplay::class, 'tabcontroldisplay');
		if (!$display instanceof IDisplay) {
			throw new RuntimeException('BASE3 ClientStack TabControlDisplay is not installed.');
		}

		$active = isset($_GET['view']) && is_scalar($_GET['view'])
			? trim((string) wp_unslash($_GET['view']))
			: '';
		$display->setData([
			'tabs' => $settings->getAdministrationConfig(),
			'active' => $active,
			'empty_message' => 'Keine BASE3-Werkzeuge installiert.'
		]);

		echo '<div class="wrap"><h1>BASE3 Werkzeuge</h1>';
		echo $display->getOutput('html', false);
		echo '</div>';
	}

	private static function enqueueToolAssets(): void {
		Runtime::boot();
		$resolver = Runtime::container()->get(IAssetResolver::class);
		$assetRoot = 'plugin/ClientStack/assets/';
		wp_enqueue_script(
			'base3-clientstack-assetloader',
			$resolver->resolve($assetRoot . 'assetloader/assetloader.min.js'),
			[],
			null,
			false
		);
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-widget');
		wp_enqueue_script('jquery-ui-mouse');
		wp_enqueue_script('jquery-ui-draggable');
		wp_enqueue_script('jquery-ui-droppable');
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_script('jquery-ui-resizable');
		wp_enqueue_style(
			'base3-clientstack-jquery-ui',
			$resolver->resolve($assetRoot . 'jqueryui/jquery-ui.css'),
			[],
			null
		);
	}
}
