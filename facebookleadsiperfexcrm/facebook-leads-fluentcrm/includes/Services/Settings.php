<?php

declare(strict_types=1);

namespace FacebookLeadsFluentCRM\Services;

final class Settings {

	public const OPTION_KEY = 'fb_leads_fluentcrm_settings';

	public function ensure_defaults(): void {
		$existing = get_option(self::OPTION_KEY);

		if ( is_array($existing) ) {
			return;
		}

		$secret = wp_generate_password(32, false, false);

		update_option(
			self::OPTION_KEY,
			[
				'webhook_secret'  => $secret,
				'default_lists'   => [],
				'default_tags'    => [],
				'field_mappings'  => [],
				'logging_enabled' => true,
			]
		);
	}

	public function get_settings(): array {
		$data = get_option(self::OPTION_KEY, []);

		if ( is_array($data) ) {
			return $data;
		}

		return [];
	}

	public function get(string $key, mixed $default = null): mixed {
		$settings = $this->get_settings();

		return $settings[ $key ] ?? $default;
	}

	public function update(array $values): void {
		$defaults = $this->get_settings();
		$clean    = $this->sanitize($values);

		update_option(self::OPTION_KEY, array_merge($defaults, $clean));
	}

	public function sanitize(array $values): array {
		$data = [];

		if ( isset($values['webhook_secret']) ) {
			$data['webhook_secret'] = sanitize_text_field((string) $values['webhook_secret']);
		}

		if ( isset($values['logging_enabled']) ) {
			$data['logging_enabled'] = (bool) $values['logging_enabled'];
		} else {
			$data['logging_enabled'] = false;
		}

		if ( isset($values['default_lists']) ) {
			$data['default_lists'] = $this->sanitize_ids($values['default_lists']);
		}

		if ( isset($values['default_tags']) ) {
			$data['default_tags'] = $this->sanitize_ids($values['default_tags']);
		}

		if ( isset($values['field_mappings']) ) {
			$data['field_mappings'] = $this->sanitize_mappings($values['field_mappings']);
		}

		return $data;
	}

	public function sanitize_ids(string|array $value): array {
		if ( is_array($value) ) {
			$raw = $value;
		} else {
			$raw = array_filter(array_map('trim', explode(',', $value)));
		}

		$ids = [];

		foreach ( $raw as $id ) {
			$int = absint((string) $id);
			if ( $int > 0 ) {
				$ids[] = $int;
			}
		}

		return array_values(array_unique($ids));
	}

	public function sanitize_mappings(string|array $value): array {
		if ( is_string($value) ) {
			$lines = preg_split('/\r\n|\r|\n/', $value);
			$maps  = [];

			foreach ( $lines as $line ) {
				if ( trim($line) === '' ) {
					continue;
				}

				$parts = explode('=', $line, 2);
				if ( count($parts) === 2 ) {
					$source = sanitize_text_field(trim($parts[0]));
					$target = sanitize_text_field(trim($parts[1]));

					if ( $source !== '' && $target !== '' ) {
						$maps[ $source ] = $target;
					}
				}
			}

			return $maps;
		}

		$maps = [];

		foreach ( $value as $source => $target ) {
			$clean_source = sanitize_text_field((string) $source);
			$clean_target = sanitize_text_field((string) $target);

			if ( $clean_source !== '' && $clean_target !== '' ) {
				$maps[ $clean_source ] = $clean_target;
			}
		}

		return $maps;
	}
}
