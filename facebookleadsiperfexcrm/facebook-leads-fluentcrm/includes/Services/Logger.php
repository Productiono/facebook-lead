<?php

declare(strict_types=1);

namespace FacebookLeadsFluentCRM\Services;

final class Logger {

	private Settings $settings;

	private string $path;

	public function __construct(Settings $settings) {
		$this->settings = $settings;

		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit($upload_dir['basedir']) . 'facebook-leads-fluentcrm';

		$this->path = $dir . '/events.log';
	}

	public function ensure_storage(): void {
		$dir = dirname($this->path);

		if ( ! wp_mkdir_p($dir) ) {
			return;
		}

		if ( ! file_exists($this->path) ) {
			touch($this->path);
		}
	}

	public function log(string $message, array $context = []): void {
		if ( (bool) $this->settings->get('logging_enabled', true) === false ) {
			return;
		}

		$this->ensure_storage();

		$entry = [
			'timestamp' => gmdate('c'),
			'message'   => $message,
			'context'   => $context,
		];

		$content = wp_json_encode($entry, JSON_UNESCAPED_SLASHES);

		if ( $content !== false ) {
			file_put_contents($this->path, $content . PHP_EOL, FILE_APPEND | LOCK_EX);
		}
	}
}
