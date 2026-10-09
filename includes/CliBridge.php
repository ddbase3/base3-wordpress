<?php declare(strict_types=1);

namespace Base3WordPressBridge;

final class CliBridge {

	/** Print module metadata and the current BASE3 classmap status. */
	public function status(): void {
		Runtime::boot();
		foreach (Runtime::catalog()->all() as $name => $module) {
			\WP_CLI::log($name . ' ' . $module['version'] . ' ' . $module['root']);
		}
	}

	/** Regenerate the BASE3 class map after updating PHP sources without changing a module manifest. */
	public function rebuild(): void {
		Runtime::boot();
		Runtime::container()->get(\Base3\Api\IClassMap::class)->generate(true);
		\WP_CLI::success('BASE3 class map and constructor cache rebuilt.');
	}

	/** Execute the existing BASE3 worker cycle. */
	public function worker(): void {
		Runtime::runWorker();
		\WP_CLI::success('BASE3 worker cycle finished.');
	}
}
