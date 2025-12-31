<?php

namespace FLFBL;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private Settings $settings;
    private Facebook_Client $client;
    private Lead_Processor $processor;
    private Admin $admin;
    private Webhook_Controller $webhook;
    private Logger $logger;

    public function __construct(
        Settings $settings,
        Facebook_Client $client,
        Lead_Processor $processor,
        Admin $admin,
        Webhook_Controller $webhook,
        Logger $logger
    ) {
        $this->settings = $settings;
        $this->client = $client;
        $this->processor = $processor;
        $this->admin = $admin;
        $this->webhook = $webhook;
        $this->logger = $logger;
        add_action('init', [$this, 'init']);
    }

    public function init(): void
    {
        add_filter('plugin_action_links_' . plugin_basename(FLFBL_FILE), [$this, 'plugin_links']);
    }

    public function plugin_links(array $links): array
    {
        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=flfbl')) . '">Settings</a>';
        return $links;
    }
}
