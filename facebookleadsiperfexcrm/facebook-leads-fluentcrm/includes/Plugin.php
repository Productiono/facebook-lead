<?php

declare(strict_types=1);

namespace FacebookLeadsFluentCRM;

use FacebookLeadsFluentCRM\Admin\SettingsPage;
use FacebookLeadsFluentCRM\Rest\WebhookController;
use FacebookLeadsFluentCRM\Services\FluentCrmService;
use FacebookLeadsFluentCRM\Services\LeadMapper;
use FacebookLeadsFluentCRM\Services\Logger;
use FacebookLeadsFluentCRM\Services\Settings;

final class Plugin {

	private string $file;

	private Settings $settings;

	private Logger $logger;

	private function __construct(string $file) {
		$this->file     = $file;
		$this->settings = new Settings();
		$this->logger   = new Logger($this->settings);
	}

	public static function boot(string $file): self {
		$plugin = new self($file);
		$plugin->register();

		return $plugin;
	}

	public function activate(): void {
		$this->settings->ensure_defaults();
		$this->logger->ensure_storage();
	}

	private function register(): void {
		register_activation_hook($this->file, [$this, 'activate']);

		add_action(
			'plugins_loaded',
			function (): void {
				$this->settings->ensure_defaults();
			}
		);

		add_action(
			'init',
			function (): void {
				$this->register_admin();
				$this->register_routes();
			}
		);
	}

	private function register_admin(): void {
		$page = new SettingsPage($this->settings, $this->logger);
		$page->register();
	}

	private function register_routes(): void {
		$service = new FluentCrmService($this->logger);
		$mapper  = new LeadMapper($this->settings);

		$controller = new WebhookController($this->settings, $service, $mapper, $this->logger);
		$controller->register();
	}
}
