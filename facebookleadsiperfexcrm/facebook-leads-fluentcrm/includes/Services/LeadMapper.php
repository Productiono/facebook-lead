<?php

declare(strict_types=1);

namespace FacebookLeadsFluentCRM\Services;

final class LeadMapper {

	private Settings $settings;

	public function __construct(Settings $settings) {
		$this->settings = $settings;
	}

	public function map(array $payload): array {
		$fields   = $this->normalize_fields($payload);
		$mappings = $this->settings->get('field_mappings', []);

		$data = [];

		foreach ( $mappings as $source => $target ) {
			if ( isset($fields[ $source ]) ) {
				$data[ $target ] = $this->stringify($fields[ $source ]);
			}
		}

		$defaults = [
			'email',
			'first_name',
			'last_name',
			'phone',
		];

		foreach ( $defaults as $key ) {
			if ( isset($fields[ $key ]) && ! isset($data[ $key ]) ) {
				$data[ $key ] = $this->stringify($fields[ $key ]);
			}
		}

		return $data;
	}

	private function normalize_fields(array $payload): array {
		$result = [];

		if ( isset($payload['field_data']) && is_array($payload['field_data']) ) {
			foreach ( $payload['field_data'] as $item ) {
				if ( isset($item['name']) && isset($item['values'][0]) ) {
					$result[ (string) $item['name'] ] = $item['values'][0];
				}
			}
		}

		foreach ( $payload as $key => $value ) {
			if ( is_string($key) && ! isset($result[ $key ]) ) {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	private function stringify(mixed $value): string {
		if ( is_array($value) ) {
			return implode(', ', array_map('sanitize_text_field', array_map('strval', $value)));
		}

		return sanitize_text_field((string) $value);
	}
}
