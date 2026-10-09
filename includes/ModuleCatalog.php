<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Api\IModuleRegistry;
use RuntimeException;

/** Finds BASE3 modules in the bridge and in every active native WordPress plugin. */
final class ModuleCatalog implements IModuleRegistry {

	private ?array $modules = null;

	public function __construct(private readonly string $modulesRoot) {}

	public function getModuleNames(): array {
		return array_keys($this->all());
	}

	public function getModulePath(string $name): ?string {
		return $this->all()[$name]['root'] ?? null;
	}

	public function requireModulePath(string $name): string {
		return $this->getModulePath($name)
			?? throw new RuntimeException('Required BASE3 module missing: ' . $name);
	}

	/** @return array<string,array{root:string,namespace:string,version:string,dependencies:array}> */
	public function all(): array {
		if ($this->modules !== null) return $this->modules;

		$modules = [];
		$names = [];
		$namespaces = [];
		foreach (WordPressModuleRoots::get($this->modulesRoot) as $modulesRoot) {
			foreach (new \DirectoryIterator($modulesRoot) as $entry) {
				if ($entry->isDot() || !$entry->isDir() || $entry->isLink()) continue;
				$root = $entry->getRealPath();
				if ($root === false) continue;
				$file = $root . '/base3.json';
				if (!is_file($file)) {
					// Other WordPress plugins may use modules/ for unrelated code.
					// Only a BASE3 manifest declares that directory as a BASE3 module.
					if ($modulesRoot === realpath($this->modulesRoot)) {
						throw new RuntimeException('BASE3 module directory has no base3.json manifest: ' . $root);
					}
					continue;
				}

				try {
					$manifest = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
				} catch (\JsonException $error) {
					throw new RuntimeException('Invalid BASE3 manifest: ' . $file, 0, $error);
				}
				if (!is_array($manifest) || ($manifest['manifestVersion'] ?? null) !== 1) {
					throw new RuntimeException('Invalid BASE3 manifest version: ' . $file);
				}
				foreach (['name', 'namespace', 'version'] as $field) {
					if (!isset($manifest[$field]) || !is_string($manifest[$field]) || trim($manifest[$field]) === '') {
						throw new RuntimeException('Missing BASE3 manifest field ' . $field . ': ' . $file);
					}
				}

				$name = trim($manifest['name']);
				$namespace = trim($manifest['namespace'], '\\ ');
				if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace)) {
					throw new RuntimeException('Invalid BASE3 namespace in ' . $file);
				}
				if (isset($names[strtolower($name)])) {
					throw new RuntimeException('Duplicate BASE3 module name ' . $name . ': ' . $names[strtolower($name)] . ' and ' . $file);
				}
				if (isset($namespaces[strtolower($namespace)])) {
					throw new RuntimeException('Duplicate BASE3 namespace ' . $namespace . ': ' . $namespaces[strtolower($namespace)] . ' and ' . $file);
				}
				$deps = $manifest['dependencies'] ?? [];
				if (!is_array($deps)) throw new RuntimeException('Invalid BASE3 module dependencies: ' . $file);
				foreach ($deps as $dependency => $constraint) {
					if (!is_string($dependency) || trim($dependency) === '' || !is_string($constraint) || trim($constraint) === '') {
						throw new RuntimeException('Invalid BASE3 dependency declaration: ' . $file);
					}
				}
				$modules[$name] = ['root' => $root, 'namespace' => $namespace, 'version' => $manifest['version'], 'dependencies' => $deps];
				$names[strtolower($name)] = $file;
				$namespaces[strtolower($namespace)] = $file;
			}
		}
		if (!isset($modules['Base3Framework'])) throw new RuntimeException('BASE3 Framework module missing.');
		ksort($modules, SORT_NATURAL | SORT_FLAG_CASE);
		$this->assertDependencies($modules);
		return $this->modules = $modules;
	}


	private function assertDependencies(array $modules): void {
		foreach ($modules as $name => $module) {
			foreach ($module['dependencies'] as $required => $constraint) {
				if (!isset($modules[$required])) throw new RuntimeException("BASE3 module {$name} requires missing module {$required} ({$constraint}).");
				if (!$this->versionMatches($modules[$required]['version'], $constraint)) {
					throw new RuntimeException("BASE3 module {$name} requires {$required} {$constraint}; installed version: " . $modules[$required]['version']);
				}
			}
		}
	}

	/** Supports the >=, >, <=, <, =, ^, ~ and multi-term constraints used by BASE3 manifests. */
	private function versionMatches(string $version, string $constraint): bool {
		foreach (preg_split('/\s+/', trim($constraint)) ?: [] as $term) {
			if ($term === '') continue;
			if ($term[0] === '^' || $term[0] === '~') {
				$base = substr($term, 1);
				$parts = array_map('intval', explode('.', $base));
				$major = $parts[0] ?? 0;
				$minor = $parts[1] ?? 0;
				$patch = $parts[2] ?? 0;
				$upper = $term[0] === '~'
					? (count($parts) < 3 ? ($major + 1) . '.0.0' : $major . '.' . ($minor + 1) . '.0')
					: ($major > 0 ? ($major + 1) . '.0.0' : ($minor > 0 ? '0.' . ($minor + 1) . '.0' : '0.0.' . ($patch + 1)));
				if (!version_compare($version, $base, '>=') || !version_compare($version, $upper, '<')) return false;
				continue;
			}
			if (!preg_match('/^(>=|<=|>|<|==|=|!=)?([0-9]+(?:\.[0-9]+)*(?:-[a-zA-Z0-9.-]+)?)$/', $term, $parts)) {
				throw new RuntimeException('Unsupported BASE3 dependency constraint: ' . $constraint);
			}
			if (!version_compare($version, $parts[2], $parts[1] ?: '>=')) return false;
		}
		return true;
	}

	/** Modules and PHP classes always enter the same global class map after installation or removal. */
	public function fingerprint(): string {
		$entries = [];
		foreach ($this->all() as $name => $module) {
			$entries[] = $name . ':' . $module['root'] . ':' . hash_file('sha256', $module['root'] . '/base3.json');
			$src = $module['root'] . '/src';
			if (!is_dir($src)) continue;
			$phpFiles = [];
			foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$phpFiles[] = $file->getPathname();
				}
			}
			sort($phpFiles, SORT_STRING);
			foreach ($phpFiles as $phpFile) {
				$entries[] = substr($phpFile, strlen($src)) . ':' . hash_file('sha256', $phpFile);
			}
		}
		return hash('sha256', implode("\n", $entries));
	}
}
