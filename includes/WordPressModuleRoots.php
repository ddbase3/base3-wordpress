<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use RuntimeException;

/** The physical module-root boundary shared by early bootstrap and IModuleRegistry. */
final class WordPressModuleRoots {

	/** @return list<string> */
	public static function get(string $bridgeModulesRoot): array {
		$primary = realpath($bridgeModulesRoot);
		if ($primary === false || !is_dir($primary)) {
			throw new RuntimeException('BASE3 modules directory missing: ' . $bridgeModulesRoot);
		}
		$roots = [$primary => $primary];
		$active = function_exists('wp_get_active_and_valid_plugins') ? wp_get_active_and_valid_plugins() : [];
		if (function_exists('wp_get_active_network_plugins')) {
			$active = array_merge($active, wp_get_active_network_plugins());
		}
		foreach ($active as $pluginFile) {
			if (!is_string($pluginFile) || !is_file($pluginFile)) continue;
			$pluginDirectory = realpath(dirname($pluginFile));
			if ($pluginDirectory === false || (defined('WP_PLUGIN_DIR') && $pluginDirectory === realpath(WP_PLUGIN_DIR))) continue;
			$modulesRoot = realpath($pluginDirectory . '/modules');
			if ($modulesRoot !== false && is_dir($modulesRoot)) {
				$roots[$modulesRoot] = $modulesRoot;
			}
		}
		ksort($roots, SORT_STRING);
		return array_values($roots);
	}

	/** Locate the framework before its PHP API can be autoloaded. */
	public static function framework(string $bridgeModulesRoot): string {
		$found = null;
		foreach (self::get($bridgeModulesRoot) as $modulesRoot) {
			foreach (new \DirectoryIterator($modulesRoot) as $entry) {
				if ($entry->isDot() || !$entry->isDir() || $entry->isLink()) continue;
				$path = $entry->getRealPath();
				if ($path === false || !is_file($path . '/base3.json')) continue;
				$manifest = json_decode((string) file_get_contents($path . '/base3.json'), true);
				if (!is_array($manifest) || ($manifest['name'] ?? null) !== 'Base3Framework') continue;
				if ($found !== null) throw new RuntimeException('Multiple BASE3 Framework modules: ' . $found . ' and ' . $path);
				$found = $path;
			}
		}
		if ($found === null) throw new RuntimeException('The active WordPress plugins do not provide Base3Framework.');
		return $found . '/';
	}
}
