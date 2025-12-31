<?php

namespace FLFBL;

if (!defined('ABSPATH')) {
    exit;
}

class Facebook_Client
{
    private Settings $settings;
    private Logger $logger;
    private string $graph_version = 'v20.0';

    public function __construct(Settings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function exchange_token(string $user_token): ?string
    {
        $app_id = $this->settings->get('app_id');
        $app_secret = $this->settings->get('app_secret');
        if (!$app_id || !$app_secret) {
            return null;
        }
        $url = add_query_arg(
            [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $app_id,
                'client_secret' => $app_secret,
                'fb_exchange_token' => $user_token,
            ],
            'https://graph.facebook.com/' . $this->graph_version . '/oauth/access_token'
        );
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            $this->logger->log('Token exchange failed', ['error' => $response->get_error_message()]);
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['access_token'])) {
            return $body['access_token'];
        }
        $this->logger->log('Token exchange error', ['response' => $body]);
        return null;
    }

    public function fetch_pages(string $token): array
    {
        $pages = [];
        $url = add_query_arg(
            [
                'fields' => 'id,name,access_token',
                'limit' => 50,
                'access_token' => $token,
            ],
            'https://graph.facebook.com/' . $this->graph_version . '/me/accounts'
        );
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            $this->logger->log('Page fetch failed', ['error' => $response->get_error_message()]);
            return $pages;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['data']) && is_array($body['data'])) {
            foreach ($body['data'] as $page) {
                if (!empty($page['id']) && !empty($page['name']) && !empty($page['access_token'])) {
                    $pages[] = [
                        'id' => (string) $page['id'],
                        'name' => $page['name'],
                        'access_token' => $page['access_token'],
                        'subscribed' => false,
                    ];
                }
            }
        } else {
            $this->logger->log('Page fetch error', ['response' => $body]);
        }
        return $pages;
    }

    public function subscribe_page(string $page_id, string $page_token): bool
    {
        $url = 'https://graph.facebook.com/' . $this->graph_version . '/' . rawurlencode($page_id) . '/subscribed_apps';
        $response = wp_remote_post($url, ['body' => ['access_token' => $page_token]]);
        if (is_wp_error($response)) {
            $this->logger->log('Page subscribe failed', ['page' => $page_id, 'error' => $response->get_error_message()]);
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['success'])) {
            return true;
        }
        $this->logger->log('Page subscribe error', ['page' => $page_id, 'response' => $body]);
        return false;
    }

    public function unsubscribe_page(string $page_id, string $page_token): bool
    {
        $url = 'https://graph.facebook.com/' . $this->graph_version . '/' . rawurlencode($page_id) . '/subscribed_apps';
        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'body' => ['access_token' => $page_token],
        ]);
        if (is_wp_error($response)) {
            $this->logger->log('Page unsubscribe failed', ['page' => $page_id, 'error' => $response->get_error_message()]);
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['success'])) {
            return true;
        }
        $this->logger->log('Page unsubscribe error', ['page' => $page_id, 'response' => $body]);
        return false;
    }

    public function fetch_lead(string $lead_id, ?string $token = null): ?array
    {
        $access_token = $token ?: $this->settings->get('long_lived_token');
        if (!$access_token) {
            $this->logger->log('Lead fetch skipped, missing access token', ['lead' => $lead_id]);
            return null;
        }
        $url = add_query_arg(
            [
                'access_token' => $access_token,
            ],
            'https://graph.facebook.com/' . $this->graph_version . '/' . rawurlencode($lead_id)
        );
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            $this->logger->log('Lead fetch failed', ['lead' => $lead_id, 'error' => $response->get_error_message()]);
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['field_data'])) {
            return $body;
        }
        $this->logger->log('Lead fetch error', ['lead' => $lead_id, 'response' => $body]);
        return null;
    }
}
