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
            'fb-leads/v1',
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
        $mode = $request->get_param('hub.mode');
        $token = $request->get_param('hub.verify_token');
        $challenge = $request->get_param('hub.challenge');
        if ($mode === 'subscribe' && $token === $this->settings->get('verify_token')) {
            status_header(200);
            header('Content-Type: text/plain; charset=' . get_option('blog_charset'));
            echo $challenge;
            exit;
        }
        return new WP_REST_Response(null, 403);
    }

    public function receive(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!$payload) {
            $this->logger->log('Webhook empty payload');
            return new WP_REST_Response(['status' => 'ignored'], 400);
        }
        $this->processor->handle_webhook($payload);
        return new WP_REST_Response(['status' => 'ok'], 200);
    }
}
