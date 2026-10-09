<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Api\IBootstrap;
use Base3\Api\IClassMap;
use Base3\Api\IComponentResolver;
use Base3\Api\IContainer;
use Base3\Api\IModuleRegistry;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Base3\Api\ISystemService;
use Base3\Core\ComponentResolver;
use Base3WordPress\Adapter\WordPressRequest;
use Base3\Core\ServiceLocator;
use Base3\Hook\Api\IHookListener;
use Base3\Hook\Api\IHookManager;
use Base3\Hook\HookManager;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Migration\Api\IMigrationRunner;
use Base3\Migration\No\NoMigrationRunner;
use Base3\ServiceSelector\Api\IServiceSelector;
use Base3\ServiceSelector\Standard\StandardServiceSelector;
use Base3WordPress\Adapter\WordPressSystemService;
use Base3WordPress\Adapter\WordPressLinkTargetService;

final class WordPressBootstrap implements IBootstrap {

	public function __construct(private readonly ModuleCatalog $catalog) {}

	public function run(): void {
		$container = new ServiceLocator();
		ServiceLocator::useInstance($container);
		$container
			->set('servicelocator', $container, IContainer::SHARED)
			->set(IContainer::class, 'servicelocator', IContainer::ALIAS)
			->set(ISystemService::class, new WordPressSystemService(), IContainer::SHARED)
			->set(IModuleRegistry::class, $this->catalog, IContainer::SHARED)
			->set(IRequest::class, WordPressRequest::fromGlobals(), IContainer::SHARED)
			->set(IHookManager::class, fn() => new HookManager(), IContainer::SHARED)
			->set(IClassMap::class, fn($c) => new WordPressClassMap($c, $this->catalog), IContainer::SHARED)
			->set('classmap', IClassMap::class, IContainer::ALIAS)
			->set(IComponentResolver::class, fn($c) => new ComponentResolver($c, $c->get(IClassMap::class)), IContainer::SHARED)
			->set(IMigrationRunner::class, fn() => new NoMigrationRunner(), IContainer::SHARED)
			->set(IServiceSelector::class, fn($c) => new StandardServiceSelector($c), IContainer::SHARED)
			->set('middlewares', [])
			->set(ILinkTargetService::class, fn() => new WordPressLinkTargetService(), IContainer::SHARED);

		$classMap = $container->get(IClassMap::class);
		$this->ensureCurrentClassMap($classMap);
		$hookManager = $container->get(IHookManager::class);
		foreach ($classMap->getInstancesByInterface(IHookListener::class) as $listener) {
			$hookManager->addHookListener($listener);
		}
		$hookManager->dispatch('bootstrap.init');

		// Host adapters must be installed before consumer plugins perform composition.
		$host = $classMap->getInstanceByInterfaceName(IPlugin::class, 'base3wordpressplugin');
		if (!$host instanceof IPlugin) throw new \RuntimeException('Base3WordPress host module is unavailable.');
		$host->init();

		$project = $classMap->getInstanceByInterfaceName(IPlugin::class, 'base3wordpresslabplugin');
		if (!$project instanceof IPlugin) throw new \RuntimeException('Base3WordPressLab project module is unavailable.');
		$project->init();

		foreach ($classMap->getInstancesByInterface(IPlugin::class) as $plugin) {
			if ($plugin instanceof \Base3WordPress\Base3WordPressPlugin || $plugin instanceof \Base3WordPressLab\Base3WordPressLabPlugin) continue;
			if (!$plugin instanceof IPlugin) throw new \RuntimeException('BASE3 class map produced an invalid plugin instance.');
			$plugin->init();
		}
		$hookManager->dispatch('bootstrap.start');
		$container->get(IMigrationRunner::class)->migrate();
		$hookManager->dispatch('bootstrap.migrated');
		Runtime::setBootResult($container, $hookManager);
	}

	private function ensureCurrentClassMap(IClassMap $classMap): void {
		$fingerprint = $this->catalog->fingerprint();
		$stampFile = DIR_TMP . 'module-fingerprint.txt';
		$old = is_file($stampFile) ? trim((string) file_get_contents($stampFile)) : '';
		if ($old !== $fingerprint || !is_file(DIR_TMP . 'classmap.php') || !is_file(DIR_TMP . 'ctorcache.php')) {
			$classMap->generate(true);
			if (file_put_contents($stampFile, $fingerprint, LOCK_EX) === false) {
				throw new \RuntimeException('Failed to write BASE3 module fingerprint.');
			}
		}
	}
}
