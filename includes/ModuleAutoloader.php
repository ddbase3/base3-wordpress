<?php declare(strict_types=1);

namespace Base3WordPressBridge;

final class ModuleAutoloader {

	private static array $prefixes = [];
	private static bool $registered = false;

	public static function register(ModuleCatalog $catalog): void {
		if (self::$registered) return;
		foreach ($catalog->all() as $module) {
			$src = $module['root'] . '/src/';
			if (is_dir($src)) self::$prefixes[rtrim($module['namespace'], '\\') . '\\'] = $src;
		}
		uksort(self::$prefixes, static fn($a, $b) => strlen($b) <=> strlen($a));
		spl_autoload_register([self::class, 'load'], true, true);
		self::$registered = true;
	}

	public static function load(string $class): void {
		foreach (self::$prefixes as $prefix => $root) {
			if (!str_starts_with($class, $prefix)) continue;
			$file = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) require_once $file;
			return;
		}
	}
}
