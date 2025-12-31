<?php

namespace FLFBL;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class Webhook_Controller
{
    private Settings $settings;
    private Lead_Processor $processor;
    private Logger $logger;

    public function __construct(Settings $settings, Lead_Processor $processor, Logger $logger)
    {
        $this->settings = $settings;
        $this->processor = $processor;
        $this->logger = $logger;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'flfbl/v1',
            '/webhook',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'verify'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'receive'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function verify(WP_REST_Request $request): WP_REST_Response
    {
        $challenge = $request->get_param('hub_challenge');
        $token = $request->get_param('hub_verify_token');
        if ($challenge && $token && $token === $this->settings->get('verify_token')) {
            return new WP_REST_Response($challenge, 200);
        }
        return new WP_REST_Response('Invalid token', 403);
    }

    public function receive(WP_REST_Request $request): WP_REST_Response
    {
        $raw_body = $request->get_body();
        if (!$this->is_valid_signature($request, $raw_body)) {
            return new WP_REST_Response(['status' => 'forbidden'], 403);
        }
        $payload = json_decode($raw_body, true);
        if (!is_array($payload)) {
            $this->logger->log('Webhook invalid payload', ['body' => $raw_body]);
            return new WP_REST_Response(['status' => 'ignored'], 400);
        }
        $this->processor->handle_webhook($payload);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    private function is_valid_signature(WP_REST_Request $request, string $raw_body): bool
    {
        $app_secret = $this->settings->get('app_secret');
        $signature = $request->get_header('x-hub-signature-256');
        $algorithm = 'sha256';
        if (!$signature) {
            $signature = $request->get_header('x-hub-signature');
            $algorithm = 'sha1';
        }
        if (!$app_secret || !$raw_body || !$signature) {
            $this->logger->log('Webhook missing signature data');
            return false;
        }
        $expected = $algorithm . '=' . hash_hmac($algorithm, $raw_body, $app_secret);
        if (!hash_equals($expected, $signature)) {
            $this->logger->log('Webhook signature mismatch', ['received' => $signature]);
            return false;
        }
        return true;
    }
}
