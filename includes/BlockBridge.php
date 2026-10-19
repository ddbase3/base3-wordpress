<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IOutput;

final class BlockBridge {

	public static function register(): void {
		wp_register_script(
			'base3-block-editor',
			plugins_url('assets/base3-block.js', Runtime::pluginFile()),
			['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
			'0.1.0',
			true
		);
		register_block_type('base3/display', [
			'api_version' => 2,
			'editor_script' => 'base3-block-editor',
			'render_callback' => [self::class, 'render'],
			'attributes' => [
				'name' => ['type' => 'string', 'default' => ''],
				'out' => ['type' => 'string', 'default' => 'html']
			]
		]);
	}

	public static function render(array $attributes = []): string {
		Runtime::boot();
		$name = (string) ($attributes['name'] ?? '');
		$out = (string) ($attributes['out'] ?? 'html');
		if (!preg_match('/^[a-z0-9_-]+$/i', $name) || !preg_match('/^[a-z0-9]+$/i', $out)) return '';
		// Admin-classified BASE3 displays are never embedded in public post content.
		$settings = Runtime::container()->get(\Base3WordPress\Api\IBase3WordPressSettings::class);
		if ($settings->getAdministrationAccessLevel($name) !== null) return '';
		$data = $attributes['data'] ?? [];
		if (is_string($data)) {
			if (trim($data) === '') {
				$data = [];
			} else {
				try {
					$data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
				} catch (\JsonException) {
					return '';
				}
			}
		}

		$map = Runtime::container()->get(IClassMap::class);
		$display = $map->getInstanceByInterfaceName(IDisplay::class, $name);
		if ($display instanceof IDisplay) {
			$display->setData($data);
			return $display->getOutput($out, false);
		}
		$output = $map->getInstanceByInterfaceName(IOutput::class, $name);
		return $output instanceof IOutput ? $output->getOutput($out, false) : '';
	}

	public static function shortcode(array|string $attributes = []): string {
		$attributes = shortcode_atts(['name' => '', 'out' => 'html', 'data' => ''], is_array($attributes) ? $attributes : [], 'base3');
		return self::render($attributes);
	}
}
