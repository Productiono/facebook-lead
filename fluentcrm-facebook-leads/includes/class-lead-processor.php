<?php

namespace FLFBL;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberMeta;

if (!defined('ABSPATH')) {
    exit;
}

class Lead_Processor
{
    private Settings $settings;
    private Facebook_Client $client;
    private Logger $logger;

    public function __construct(Settings $settings, Facebook_Client $client, Logger $logger)
    {
        $this->settings = $settings;
        $this->client = $client;
        $this->logger = $logger;
    }

    public function handle_webhook(array $payload): void
    {
        if (isset($payload['hub_challenge'], $payload['hub_verify_token'])) {
            return;
        }
        if (empty($payload['entry'][0]['changes'][0]['value']['leadgen_id'])) {
            $this->logger->log('Webhook missing leadgen_id', ['payload' => $payload]);
            return;
        }
        $lead_id = (string) $payload['entry'][0]['changes'][0]['value']['leadgen_id'];
        $page_id = $payload['entry'][0]['changes'][0]['value']['page_id'] ?? null;
        $lead = $this->client->fetch_lead($lead_id, $page_id ? (string) $page_id : null);
        if (!$lead) {
            return;
        }
        $this->process_lead($lead);
    }

    public function process_lead(array $lead): void
    {
        $field_data = $this->normalize_fields($lead);
        if (!$field_data) {
            $this->logger->log('Lead missing field data', ['lead' => $lead]);
            return;
        }
        $mapped = $this->map_fields($field_data);
        if (empty($mapped['email']) && empty($mapped['phone'])) {
            $this->logger->log('Lead missing identifiers', ['fields' => $field_data]);
            return;
        }
        $subscriber = $this->find_subscriber($mapped);
        if (!$subscriber && empty($mapped['email'])) {
            $this->logger->log('Lead skipped without email for new subscriber', ['fields' => $field_data]);
            return;
        }
        $merged = $this->prepare_subscriber_payload($mapped, $subscriber);
        if ($subscriber) {
            $subscriber->fill($merged);
            $subscriber->save();
        } else {
            $subscriber = Subscriber::create($merged);
        }
        $this->sync_meta($subscriber, $field_data);
        $this->sync_lists_and_tags($subscriber);
    }

    private function normalize_fields(array $lead): array
    {
        $fields = [];
        if (!empty($lead['field_data']) && is_array($lead['field_data'])) {
            foreach ($lead['field_data'] as $field) {
                if (empty($field['name']) || empty($field['values'][0])) {
                    continue;
                }
                $fields[strtolower($field['name'])] = is_array($field['values']) ? $field['values'][0] : $field['values'];
            }
        }
        return $fields;
    }

    private function map_fields(array $fields): array
    {
        $mapped = [];
        $map = $this->settings->get('field_map', []);
        $custom_map = $this->settings->get('custom_field_map', []);
        foreach ($fields as $name => $value) {
            if (isset($map[$name])) {
                $mapped[$map[$name]] = $value;
            } elseif (isset($custom_map[$name])) {
                $mapped['custom'][$custom_map[$name]] = $value;
            }
        }
        if (empty($mapped['first_name']) && empty($mapped['last_name'])) {
            $full = $mapped['full_name'] ?? ($fields['full_name'] ?? ($fields['name'] ?? ''));
            if ($full) {
                $parts = preg_split('/\s+/', trim($full));
                $mapped['first_name'] = array_shift($parts);
                $mapped['last_name'] = $parts ? implode(' ', $parts) : '';
            }
        }
        if (!empty($mapped['full_name']) && empty($mapped['first_name']) && empty($mapped['last_name'])) {
            $parts = preg_split('/\s+/', trim($mapped['full_name']));
            $mapped['first_name'] = array_shift($parts);
            $mapped['last_name'] = $parts ? implode(' ', $parts) : '';
        }
        return $mapped;
    }

    private function find_subscriber(array $data): ?Subscriber
    {
        if (!empty($data['email'])) {
            $existing = Subscriber::where('email', $data['email'])->first();
            if ($existing) {
                return $existing;
            }
        }
        if (!empty($data['phone'])) {
            $existing = Subscriber::where('phone', $data['phone'])->first();
            if ($existing) {
                return $existing;
            }
        }
        return null;
    }

    private function prepare_subscriber_payload(array $mapped, ?Subscriber $subscriber): array
    {
        $payload = [];
        $allowed = [
            'email',
            'first_name',
            'last_name',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
            'company',
            'job_title',
        ];
        foreach ($allowed as $key) {
            if (isset($mapped[$key]) && $mapped[$key] !== '') {
                $payload[$key] = $mapped[$key];
            }
        }
        $status = $this->settings->get('status', 'subscribed');
        if ($subscriber) {
            return $payload;
        }
        $payload['status'] = $status;
        if (!empty($mapped['email'])) {
            $payload['email'] = sanitize_email($mapped['email']);
        }
        return $payload;
    }

    private function sync_lists_and_tags(Subscriber $subscriber): void
    {
        $tags = array_filter(array_map('intval', (array) $this->settings->get('tag_ids', [])));
        if ($tags) {
            $subscriber->attachTags($tags);
        }
        $lists = array_filter(array_map('intval', (array) $this->settings->get('list_ids', [])));
        if ($lists) {
            $subscriber->attachLists($lists);
        }
    }

    private function sync_meta(Subscriber $subscriber, array $fields): void
    {
        $custom_map = $this->settings->get('custom_field_map', []);
        if (!$custom_map) {
            return;
        }
        foreach ($custom_map as $fb_key => $meta_key) {
            if (!isset($fields[$fb_key])) {
                continue;
            }
            SubscriberMeta::updateOrCreate(
                [
                    'subscriber_id' => $subscriber->id,
                    'key' => $meta_key,
                ],
                [
                    'value' => $fields[$fb_key],
                ]
            );
        }
    }
}
