<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Api\IAssetResolver;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Usermanager\Permission;
use RuntimeException;
use Throwable;
use UiFoundation\Api\ISettingsTab;

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
		$display = Runtime::container()->get(IClassMap::class)->getInstanceByInterfaceName(IDisplay::class, 'settingsdisplay');
		if (!$display instanceof IDisplay) {
			throw new RuntimeException('BASE3 ClientStack SettingsDisplay is not installed.');
		}
		echo '<div class="wrap"><h1>BASE3 Werkzeuge</h1>';
		echo $display->getOutput('html', false);
		echo '</div>';
	}

	/** Checks native administration and the feature's existing visibility decision. */
	public static function canDispatch(string $displayName, bool $ajax): bool {
		if ($displayName === 'tabcontroldisplay') return false;
		$tabs = self::settingsTabsForDisplay($displayName);
		if ($tabs === null) return false;
		$protected = $displayName === 'settingsdisplay';
		foreach ($tabs as $tab) {
			if ($tab->getParentTabName() !== 'gamessettingstab') $protected = true;
		}
		if (!$protected) return true;
		try {
			$authorization = Runtime::container()->get(IAuthorizationService::class);
			if (!$authorization->decide(new AuthorizationRequest(
				$authorization->getDefaultAuthority(), Permission::for('system', 'admin')
			))->isAllowed()) return false;
			if ($ajax && check_ajax_referer('base3_admin_display', '_wpnonce', false) === false) return false;
			if ($displayName === 'settingsdisplay') return true;
			foreach ($tabs as $tab) {
				if ($tab->getParentTabName() !== 'gamessettingstab' && $tab->isVisible()) return true;
			}
			return false;
		} catch (Throwable) {
			return false;
		}
	}

	/** Restricts settings compositions in shortcode and Gutenberg blocks. */
	public static function isAdministrationDisplay(string $displayName): bool {
		if ($displayName === 'settingsdisplay' || $displayName === 'tabcontroldisplay') return true;
		$tabs = self::settingsTabsForDisplay($displayName);
		if ($tabs === null) return true;
		foreach ($tabs as $tab) {
			if ($tab->getParentTabName() !== 'gamessettingstab') return true;
		}
		return false;
	}

	/** @return list<ISettingsTab>|null Null when discovery is unavailable. */
	private static function settingsTabsForDisplay(string $displayName): ?array {
		try {
			$matches = [];
			foreach (Runtime::container()->get(IClassMap::class)->getInstancesByInterface(ISettingsTab::class) as $tab) {
				if ($tab instanceof ISettingsTab && $tab->getDisplayName() === $displayName) {
					$matches[] = $tab;
				}
			}
			return $matches;
		} catch (Throwable) {
			return null;
		}
	}

	private static function enqueueToolAssets(): void {
		Runtime::boot();
		$resolver = Runtime::container()->get(IAssetResolver::class);
		$assetRoot = 'plugin/ClientStack/assets/';
		wp_enqueue_script('base3-clientstack-assetloader', $resolver->resolve($assetRoot . 'assetloader/assetloader.min.js'), [], null, false);
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-widget');
		wp_enqueue_script('jquery-ui-mouse');
		wp_enqueue_script('jquery-ui-draggable');
		wp_enqueue_script('jquery-ui-droppable');
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_script('jquery-ui-resizable');
		wp_enqueue_style('base3-clientstack-jquery-ui', $resolver->resolve($assetRoot . 'jqueryui/jquery-ui.css'), [], null);
	}
}
