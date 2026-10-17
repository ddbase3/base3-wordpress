<?php declare(strict_types=1);

namespace Base3WordPressBridge;

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Api\IClassMap;
use Base3\Api\IDisplay;
use Base3\Api\IOutput;

final class AjaxBridge {

	private const DISPLAY_DATA_PARAMETER = 'base3_display_data';
	private const MAX_DISPLAY_DATA_LENGTH = 1048576;

	/** WordPress owns the transport action; BASE3 keeps POST action for its output handler. */
	public static function prepareRequest(): void {
		if (!wp_doing_ajax() || ($_GET['action'] ?? null) !== 'base3_dispatch') return;
		$_REQUEST['action'] = 'base3_dispatch';
	}

	public static function dispatch(): void {
		Runtime::boot();
		// The URL identifies the output; POST may use the same field names for its own data.
		$name = isset($_GET['name']) && is_scalar($_GET['name']) ? (string) wp_unslash($_GET['name']) : '';
		$out = isset($_GET['out']) && is_scalar($_GET['out']) ? (string) wp_unslash($_GET['out']) : 'html';
		if (!preg_match('/^[a-z0-9_\-]+$/i', $name) || !preg_match('/^[a-z0-9]+$/i', $out)) {
			wp_die('Invalid BASE3 request.', '', ['response' => 400]);
		}

		$map = Runtime::container()->get(IClassMap::class);
		// IDisplay extends IOutput, so resolve the more specific contract first.
		$display = $map->getInstanceByInterfaceName(IDisplay::class, $name);
		$output = $display instanceof IDisplay
			? $display
			: $map->getInstanceByInterfaceName(IOutput::class, $name);
		if (!$output instanceof IOutput && !$display instanceof IDisplay) {
			wp_die('BASE3 output or display not found.', '', ['response' => 404]);
		}

		if ($display instanceof IDisplay && array_key_exists(self::DISPLAY_DATA_PARAMETER, $_REQUEST)) {
			$encoded = $_REQUEST[self::DISPLAY_DATA_PARAMETER];
			if (!is_string($encoded)) wp_die('Invalid BASE3 display data.', '', ['response' => 400]);
			$decoded = self::decodeDisplayData((string) wp_unslash($encoded));
			if (!$decoded['valid']) wp_die('Invalid BASE3 display data.', '', ['response' => 400]);
			$display->setData($decoded['value']);
		}

		Runtime::container()->get(IAccesscontrol::class)->authenticate();
		$types = ['json' => 'application/json', 'xml' => 'application/xml', 'txt' => 'text/plain', 'css' => 'text/css', 'js' => 'application/javascript'];
		header('Content-Type: ' . ($types[$out] ?? 'text/html') . '; charset=utf-8');
		header('X-Content-Type-Options: nosniff');
		echo $output instanceof IOutput ? $output->getOutput($out, true) : $display->getOutput($out, true);
		Runtime::finish();
		wp_die('', '', ['response' => http_response_code() ?: 200]);
	}

	/** @return array{valid:bool,value:mixed} */
	private static function decodeDisplayData(string $data): array {
		$data = trim($data);
		if ($data === '' || strlen($data) > self::MAX_DISPLAY_DATA_LENGTH || !preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $data)) {
			return ['valid' => false, 'value' => null];
		}
		$padded = str_pad($data, strlen($data) + ((4 - strlen($data) % 4) % 4), '=', STR_PAD_RIGHT);
		$json = base64_decode(strtr($padded, '-_', '+/'), true);
		if (!is_string($json)) return ['valid' => false, 'value' => null];
		try {
			return ['valid' => true, 'value' => json_decode($json, true, 512, JSON_THROW_ON_ERROR)];
		} catch (\JsonException) {
			return ['valid' => false, 'value' => null];
		}
	}
}
