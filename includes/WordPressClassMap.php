<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Core\PluginClassMap;
use RuntimeException;

/** Builds the normal BASE3 class map from module manifest namespaces, not directory names. */
final class WordPressClassMap extends PluginClassMap {

	public function __construct(\Base3\Api\IContainer $container, private readonly ModuleCatalog $catalog) {
		parent::__construct($container);
	}

	protected function getScanTargets(): array {
		return [['basedir' => DIR_SRC, 'subdir' => '', 'subns' => 'Base3']];
	}

	public function generate($regenerate = false): void {
		if (!$regenerate && is_file($this->classMapFile) && filesize($this->classMapFile) > 0) return;
		if (!is_writable(DIR_TMP)) throw new RuntimeException('BASE3 classmap cache directory is not writable: ' . DIR_TMP);
		$this->map = [];
		foreach ($this->catalog->all() as $name => $module) {
			$src = $module['root'] . '/src';
			if (!is_dir($src)) continue;
			$classes = [];
			$this->scanModuleClasses($classes, $src, $module['namespace']);
			$this->fillClassMap($name, $classes);
		}
		$this->writeClassMap();
		$this->generateConstructorCache();
	}

	private function scanModuleClasses(array &$classes, string $src, string $namespace, string $relative = ''): void {
		$directory = $relative === '' ? $src : $src . '/' . $relative;
		foreach ($this->getEntries($directory) as $entry) {
			$path = $directory . '/' . $entry;
			if (is_dir($path)) {
				$this->scanModuleClasses($classes, $src, $namespace, ltrim($relative . '/' . $entry, '/'));
				continue;
			}
			if (substr($entry, -4) !== '.php' || substr_count($entry, '.') !== 1) continue;
			if ($namespace === 'Base3' && $relative === 'Core' && $entry === 'Autoloader.php') continue;
			$classname = $namespace . '\\' . ($relative === '' ? '' : str_replace('/', '\\', $relative) . '\\') . substr($entry, 0, -4);
			// Classmaps index only concrete classes. Other declarations are loaded through PSR-4 when needed.
			if (!class_exists($classname)) continue;
			$reflection = new \ReflectionClass($classname);
			if ($reflection->isAbstract()) continue;
			$classes[] = ['file' => $path, 'class' => $classname, 'interfaces' => class_implements($classname)];
		}
	}
}
