<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Api\IContainer;
use Base3\Hook\Api\IHookManager;
use RuntimeException;

final class Runtime {

	private static ?string $pluginFile = null;
	private static ?ModuleCatalog $catalog = null;
	private static ?IContainer $container = null;
	private static ?IHookManager $hookManager = null;
	private static bool $booting = false;
	private static bool $finished = false;

	public static function register(string $pluginFile): void {
		if (self::$pluginFile !== null) throw new RuntimeException('BASE3 bridge registered twice.');
		self::$pluginFile = $pluginFile;
		require_once __DIR__ . '/RestBridge.php';
		require_once __DIR__ . '/AdminBridge.php';
		require_once __DIR__ . '/BlockBridge.php';
		require_once __DIR__ . '/AjaxBridge.php';
		add_action('init', [self::class, 'boot'], 20);
		add_action('init', [BlockBridge::class, 'register'], 30);
		add_action('rest_api_init', [RestBridge::class, 'register']);
		add_action('admin_menu', [AdminBridge::class, 'register']);
		add_action('shutdown', [self::class, 'finish'], 99);
		add_shortcode('base3', [BlockBridge::class, 'shortcode']);
		add_action('base3_worker_tick', [self::class, 'runWorker']);
		add_action('admin_init', [AjaxBridge::class, 'prepareRequest'], 0);
		add_action('wp_ajax_base3_dispatch', [AjaxBridge::class, 'dispatch']);
		add_action('wp_ajax_base3_filemanager', [AjaxBridge::class, 'fileManager']);
		add_action('wp_ajax_nopriv_base3_dispatch', [AjaxBridge::class, 'dispatch']);
		register_activation_hook($pluginFile, [self::class, 'activate']);
		register_deactivation_hook($pluginFile, [self::class, 'deactivate']);
		if (defined('WP_CLI') && WP_CLI) {
			require_once __DIR__ . '/CliBridge.php';
			\WP_CLI::add_command('base3', CliBridge::class);
		}
	}

	public static function boot(): void {
		if (self::$container !== null) return;
		if (self::$booting) throw new RuntimeException('Recursive BASE3 bootstrap is not allowed.');
		self::$booting = true;
		try {
			self::prepareEnvironment();
			require_once __DIR__ . '/WordPressBootstrap.php';
			(new WordPressBootstrap(self::catalog()))->run();
		}
		finally {
			self::$booting = false;
		}
	}

	public static function setBootResult(IContainer $container, IHookManager $hooks): void {
		if (self::$container !== null) throw new RuntimeException('BASE3 container already set.');
		self::$container = $container;
		self::$hookManager = $hooks;
	}

	public static function container(): IContainer {
		return self::$container ?? throw new RuntimeException('BASE3 has not yet booted. Use WordPress init priority > 20.');
	}

	public static function catalog(): ModuleCatalog {
		return self::$catalog ?? throw new RuntimeException('BASE3 module catalog is not initialized.');
	}

	public static function pluginFile(): string {
		return self::$pluginFile ?? throw new RuntimeException('BASE3 WordPress plugin not registered.');
	}

	public static function finish(): void {
		if (self::$finished || self::$hookManager === null) return;
		self::$finished = true;
		self::$hookManager->dispatch('bootstrap.finish');
	}

	public static function activate(): void {
		if (!wp_next_scheduled('base3_worker_tick')) {
			wp_schedule_event(time() + 60, 'hourly', 'base3_worker_tick');
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook('base3_worker_tick');
	}

	public static function runWorker(): void {
		self::boot();
		$container = self::container();
		$workers = $container->get('workers');
		if (!is_array($workers)) return;
		$logger = $container->get(\Base3\Logger\Api\ILogger::class);
		if (!$logger instanceof \Base3\Logger\Api\ILogger) {
			throw new RuntimeException('BASE3 worker requires ILogger.');
		}
		$runner = new \Base3\Worker\MasterWorker($logger, $workers);
		ob_start();
		try {
			$runner->getOutput('txt', true);
		} finally {
			ob_end_clean();
		}
	}

	private static function prepareEnvironment(): void {
		$root = dirname(self::pluginFile());
		$modules = $root . '/modules/';
		require_once __DIR__ . '/WordPressModuleRoots.php';
		$framework = WordPressModuleRoots::framework($modules);
		$data = defined('BASE3_WORDPRESS_DATA_DIR')
			? rtrim((string) BASE3_WORDPRESS_DATA_DIR, '/\\') . '/'
			: rtrim(WP_CONTENT_DIR, '/\\') . '/base3-private/';
		foreach ([$data, $data . 'cnf/', $data . 'cache/', $data . 'tmp/', $data . 'userfiles/'] as $dir) {
			if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
				throw new RuntimeException('BASE3 writable data directory unavailable: ' . $dir);
			}
		}
		// Required for Apache, which is used by the development installation.
		$deny = $data . '.htaccess';
		if (!is_file($deny) && file_put_contents($deny, "Require all denied\nDeny from all\n") === false) {
			throw new RuntimeException('Cannot protect BASE3 private runtime directory: ' . $deny);
		}
		if (!is_file($data . 'index.php')) file_put_contents($data . 'index.php', '<?php http_response_code(403); exit;');
		$definitions = [
			'DIR_ROOT' => $framework,
			'DIR_FRAMEWORK' => $framework,
			'DIR_PLUGIN' => $modules,
			'DIR_BASE3' => $modules,
			'DIR_SRC' => $framework . 'src/',
			'DIR_TEST' => $framework . 'test/',
			'DIR_CNF' => $data . 'cnf/',
			'DIR_TMP' => $data . 'cache/',
			'DIR_LOCAL' => $data,
			'DIR_USERFILES' => $data . 'userfiles/',
			'DIR_TPL' => $framework . 'tpl/'
		];
		foreach ($definitions as $name => $value) {
			if (defined($name) && constant($name) !== $value) {
				throw new RuntimeException('BASE3 directory constant conflicts with host: ' . $name);
			}
			if (!defined($name)) define($name, $value);
		}
		if (getenv('DEBUG') === false) putenv('DEBUG=0');
		require_once $framework . 'src/Core/Autoloader.php';
		\Base3\Core\Autoloader::register();
		require_once __DIR__ . '/ModuleCatalog.php';
		self::$catalog = new ModuleCatalog($modules);
		self::$catalog->all();
		require_once __DIR__ . '/ModuleAutoloader.php';
		ModuleAutoloader::register(self::$catalog);
		require_once __DIR__ . '/WordPressClassMap.php';
	}
}
