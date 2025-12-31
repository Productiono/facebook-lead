<?php

declare(strict_types=1);

namespace FacebookLeadsFluentCRM\Rest;

use FacebookLeadsFluentCRM\Services\FluentCrmService;
use FacebookLeadsFluentCRM\Services\LeadMapper;
use FacebookLeadsFluentCRM\Services\Logger;
use FacebookLeadsFluentCRM\Services\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class WebhookController {

	private Settings $settings;

	private FluentCrmService $crm;

	private LeadMapper $mapper;

	private Logger $logger;

	public function __construct(Settings $settings, FluentCrmService $crm, LeadMapper $mapper, Logger $logger) {
		$this->settings = $settings;
		$this->crm      = $crm;
		$this->mapper   = $mapper;
		$this->logger   = $logger;
	}

	public function register(): void {
		add_action(
			'rest_api_init',
			function (): void {
				register_rest_route(
					'facebook-leads-fluentcrm/v1',
					'/webhook',
					[
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => [$this, 'handle'],
						'permission_callback' => '__return_true',
					]
				);
			}
		);
	}

	public function handle(WP_REST_Request $request): WP_REST_Response|WP_Error {
		if ( $this->authorized($request) === false ) {
			return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
		}

		$payload = $request->get_json_params();

		if ( ! is_array($payload) ) {
			$this->logger->log('Invalid payload', ['body' => $request->get_body()]);

			return new WP_Error('invalid_payload', 'Invalid payload', ['status' => 400]);
		}

		$data = $this->mapper->map($payload);

		$lists = $this->settings->get('default_lists', []);
		$tags  = $this->settings->get('default_tags', []);

		try {
			$contact = $this->crm->upsert_contact($data, $lists, $tags);
		} catch ( \Throwable $e ) {
			$this->logger->log('Webhook error', ['error' => $e->getMessage(), 'payload' => $payload]);

			return new WP_Error('processing_error', 'Processing failed', ['status' => 500]);
		}

		return new WP_REST_Response(
			[
				'id'    => $contact->id,
				'email' => $contact->email,
			],
			200
		);
	}

	private function authorized(WP_REST_Request $request): bool {
		$secret   = (string) $this->settings->get('webhook_secret', '');
		$provided = (string) $request->get_param('secret');

		if ( $provided === '' ) {
			$provided = (string) $request->get_header('x-facebook-secret');
		}

		if ( $secret === '' || $provided === '' ) {
			return false;
		}

		return hash_equals($secret, $provided);
	}
}
