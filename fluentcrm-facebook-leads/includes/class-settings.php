<?php

namespace FLFBL;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    private string $option_key = 'flfbl_settings';

    public function defaults(): array
    {
        return [
            'app_id' => '',
            'app_secret' => '',
            'verify_token' => '',
            'user_token' => '',
            'long_lived_token' => '',
            'pages' => [],
            'field_map' => [
                'full_name' => 'full_name',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'name' => 'full_name',
                'email' => 'email',
                'phone_number' => 'phone',
                'phone' => 'phone',
                'mobile' => 'phone',
                'city' => 'city',
                'state' => 'state',
                'zip' => 'postal_code',
                'postal_code' => 'postal_code',
                'country' => 'country',
                'address' => 'address_line_1',
                'address_line_1' => 'address_line_1',
                'company_name' => 'company',
                'company' => 'company',
                'job_title' => 'job_title',
            ],
            'custom_field_map' => [],
            'tag_ids' => [],
            'list_ids' => [],
            'status' => 'subscribed',
        ];
    }

    public function all(): array
    {
        $stored = get_option($this->option_key, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_replace_recursive($this->defaults(), $stored);
    }

    public function get(string $key, $default = null)
    {
        $settings = $this->all();
        return $settings[$key] ?? $default;
    }

    public function update(array $values): array
    {
        $settings = $this->all();
        foreach ($values as $key => $value) {
            $settings[$key] = $value;
        }
        update_option($this->option_key, $settings);
        return $settings;
    }

    public function bootstrap(): void
    {
        $this->update($this->all());
    }
}
