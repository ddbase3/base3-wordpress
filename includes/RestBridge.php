<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Api\IClassMap;

final class RestBridge {

	public static function register(): void {
		register_rest_route('base3/v1', '/status', [
			'methods' => 'GET',
			'permission_callback' => static fn() => current_user_can('manage_options'),
			'callback' => [self::class, 'status']
		]);
	}

	public static function status(): \WP_REST_Response {
		Runtime::boot();
		$c = Runtime::container();
		return new \WP_REST_Response([
			'active' => true,
			'host' => 'WordPress',
			'frameworkVersion' => Runtime::catalog()->all()['Base3Framework']['version'],
			'modules' => Runtime::catalog()->getModuleNames(),
			'classmapApps' => $c->get(IClassMap::class)->getApps()
		]);
	}
}
