<?php

declare(strict_types=1);

namespace FacebookLeadsFluentCRM\Admin;

use FacebookLeadsFluentCRM\Services\Logger;
use FacebookLeadsFluentCRM\Services\Settings;

final class SettingsPage {

	private Settings $settings;

	private Logger $logger;

	public function __construct(Settings $settings, Logger $logger) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function register(): void {
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_init', [$this, 'register_settings']);
	}

	public function add_menu(): void {
		add_menu_page(
			'Facebook Leads FluentCRM',
			'Facebook Leads',
			'manage_options',
			'facebook-leads-fluentcrm',
			[$this, 'render'],
			'dashicons-groups'
		);
	}

	public function register_settings(): void {
		register_setting(
			'facebook-leads-fluentcrm',
			Settings::OPTION_KEY,
			[
				'sanitize_callback' => [$this, 'sanitize'],
			]
		);

		add_settings_section(
			'facebook-leads-fluentcrm-main',
			'Configuration',
			'__return_empty_string',
			'facebook-leads-fluentcrm'
		);

		add_settings_field(
			'webhook_secret',
			'Webhook Secret',
			[$this, 'render_text_field'],
			'facebook-leads-fluentcrm',
			'facebook-leads-fluentcrm-main',
			[
				'name'  => 'webhook_secret',
				'type'  => 'text',
				'style' => 'width: 350px;',
			]
		);

		add_settings_field(
			'default_lists',
			'List IDs',
			[$this, 'render_text_field'],
			'facebook-leads-fluentcrm',
			'facebook-leads-fluentcrm-main',
			[
				'name'  => 'default_lists',
				'type'  => 'text',
				'style' => 'width: 350px;',
			]
		);

		add_settings_field(
			'default_tags',
			'Tag IDs',
			[$this, 'render_text_field'],
			'facebook-leads-fluentcrm',
			'facebook-leads-fluentcrm-main',
			[
				'name'  => 'default_tags',
				'type'  => 'text',
				'style' => 'width: 350px;',
			]
		);

		add_settings_field(
			'field_mappings',
			'Field Mappings',
			[$this, 'render_mappings'],
			'facebook-leads-fluentcrm',
			'facebook-leads-fluentcrm-main'
		);

		add_settings_field(
			'logging_enabled',
			'Enable Logging',
			[$this, 'render_checkbox'],
			'facebook-leads-fluentcrm',
			'facebook-leads-fluentcrm-main',
			[
				'name' => 'logging_enabled',
			]
		);
	}

	public function sanitize(array|string $values): array {
		if ( ! is_array($values) ) {
			return $this->settings->get_settings();
		}

		$this->logger->ensure_storage();

		return $this->settings->sanitize($values);
	}

	public function render(): void {
		if ( ! current_user_can('manage_options') ) {
			return;
		}

		$settings = $this->settings->get_settings();
		?>
		<div class="wrap">
			<h1>Facebook Leads FluentCRM</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields('facebook-leads-fluentcrm');
				do_settings_sections('facebook-leads-fluentcrm');
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_text_field(array $args): void {
		$settings = $this->settings->get_settings();
		$name     = $args['name'];
		$value    = $settings[ $name ] ?? '';
		$type     = $args['type'] ?? 'text';
		$style    = $args['style'] ?? '';

		printf(
			'<input type="%1$s" name="%2$s[%3$s]" value="%4$s" style="%5$s" />',
			esc_attr($type),
			esc_attr(Settings::OPTION_KEY),
			esc_attr($name),
			esc_attr(is_array($value) ? implode(',', $value) : (string) $value),
			esc_attr($style)
		);
	}

	public function render_checkbox(array $args): void {
		$settings = $this->settings->get_settings();
		$name     = $args['name'];
		$value    = (bool) ($settings[ $name ] ?? false);

		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
			esc_attr(Settings::OPTION_KEY),
			esc_attr($name),
			checked(true, $value, false),
			esc_html__('Enable', 'facebook-leads-fluentcrm')
		);
	}

	public function render_mappings(): void {
		$settings = $this->settings->get_settings();
		$mappings = $settings['field_mappings'] ?? [];

		$value = '';

		foreach ( $mappings as $source => $target ) {
			$value .= $source . '=' . $target . PHP_EOL;
		}

		printf(
			'<textarea name="%1$s[field_mappings]" rows="8" cols="60" style="width: 480px;">%2$s</textarea>',
			esc_attr(Settings::OPTION_KEY),
			esc_textarea(trim($value))
		);
	}
}
