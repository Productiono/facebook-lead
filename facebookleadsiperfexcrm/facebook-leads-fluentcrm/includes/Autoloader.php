<?php

declare(strict_types=1);

namespace FacebookLeadsFluentCRM;

final class Autoloader {

	public static function register(): void {
		spl_autoload_register(
			static function (string $class): void {
				$prefix = __NAMESPACE__ . '\\';

				if ( str_starts_with($class, $prefix) === false ) {
					return;
				}

				$relative = substr($class, strlen($prefix));
				$relative = str_replace('\\', '/', $relative);
				$file     = __DIR__ . '/' . $relative . '.php';

				if ( file_exists($file) ) {
					require_once $file;
				}
			}
		);
	}
}
