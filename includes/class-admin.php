<?php

namespace FLFBL;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Tag;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    private Settings $settings;
    private Facebook_Client $client;
    private Lead_Processor $processor;
    private Logger $logger;

    public function __construct(Settings $settings, Facebook_Client $client, Lead_Processor $processor, Logger $logger)
    {
        $this->settings = $settings;
        $this->client = $client;
        $this->processor = $processor;
        $this->logger = $logger;
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_actions']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            'Facebook Lead Ads',
            'Facebook Leads',
            'manage_options',
            'flfbl',
            [$this, 'render_page'],
            'dashicons-facebook-alt'
        );
    }

    public function register_actions(): void
    {
        add_action('admin_post_flfbl_save_credentials', [$this, 'save_credentials']);
        add_action('admin_post_flfbl_exchange_token', [$this, 'exchange_token']);
        add_action('admin_post_flfbl_save_mapping', [$this, 'save_mapping']);
        add_action('admin_post_flfbl_subscribe_page', [$this, 'subscribe_page']);
        add_action('admin_post_flfbl_unsubscribe_page', [$this, 'unsubscribe_page']);
        add_action('admin_post_flfbl_refresh_pages', [$this, 'refresh_pages']);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = $this->settings->all();
        $tags = Tag::orderBy('title', 'ASC')->get()->toArray();
        $lists = Lists::orderBy('title', 'ASC')->get()->toArray();
        $field_targets = [
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
            'full_name',
        ];
        $map = $settings['field_map'];
        $reverse = [];
        foreach ($map as $fb => $target) {
            if (!isset($reverse[$target])) {
                $reverse[$target] = $fb;
            }
        }
        settings_errors('flfbl');
        ?>
        <div class="wrap">
            <h1>Facebook Lead Ads for FluentCRM</h1>
            <h2 class="nav-tab-wrapper">
                <a href="#credentials" class="nav-tab nav-tab-active">Credentials</a>
                <a href="#field-mapping" class="nav-tab">Mapping</a>
                <a href="#pages" class="nav-tab">Pages</a>
            </h2>
            <div id="credentials" class="flfbl-section">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('flfbl_save_credentials'); ?>
                    <input type="hidden" name="action" value="flfbl_save_credentials">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="flfbl_app_id">App ID</label></th>
                            <td><input name="app_id" id="flfbl_app_id" type="text" value="<?php echo esc_attr($settings['app_id']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="flfbl_app_secret">App Secret</label></th>
                            <td><input name="app_secret" id="flfbl_app_secret" type="text" value="<?php echo esc_attr($settings['app_secret']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="flfbl_verify_token">Verify Token</label></th>
                            <td><input name="verify_token" id="flfbl_verify_token" type="text" value="<?php echo esc_attr($settings['verify_token']); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                    <?php submit_button('Save Credentials'); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('flfbl_exchange_token'); ?>
                    <input type="hidden" name="action" value="flfbl_exchange_token">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="flfbl_user_token">User Access Token</label></th>
                            <td><textarea name="user_token" id="flfbl_user_token" rows="3" class="large-text code"><?php echo esc_textarea($settings['user_token']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="flfbl_long_token">Long-lived Token</label></th>
                            <td><textarea name="long_lived_token" id="flfbl_long_token" rows="3" class="large-text code"><?php echo esc_textarea($settings['long_lived_token']); ?></textarea></td>
                        </tr>
                    </table>
                    <?php submit_button('Update Token and Fetch Pages'); ?>
                </form>
            </div>
            <div id="field-mapping" class="flfbl-section" style="display:none;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('flfbl_save_mapping'); ?>
                    <input type="hidden" name="action" value="flfbl_save_mapping">
                    <h2>Field Mapping</h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>FluentCRM Field</th>
                                <th>Facebook Field Name</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($field_targets as $target) : ?>
                            <tr>
                                <td><?php echo esc_html($target); ?></td>
                                <td>
                                    <input type="text" name="standard_map[<?php echo esc_attr($target); ?>]" value="<?php echo esc_attr($reverse[$target] ?? ''); ?>" class="regular-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <h3>Custom Field Mapping</h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Facebook Field Name</th>
                                <th>Meta Key</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $custom_map = $settings['custom_field_map'];
                        if (!$custom_map) {
                            $custom_map = ['' => ''];
                        }
                        foreach ($custom_map as $fb => $meta) :
                        ?>
                            <tr>
                                <td><input type="text" name="custom_fb[]" value="<?php echo esc_attr($fb); ?>" class="regular-text"></td>
                                <td><input type="text" name="custom_meta[]" value="<?php echo esc_attr($meta); ?>" class="regular-text"></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr>
                                <td><input type="text" name="custom_fb[]" value="" class="regular-text"></td>
                                <td><input type="text" name="custom_meta[]" value="" class="regular-text"></td>
                            </tr>
                        </tbody>
                    </table>
                    <h3>Defaults</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Status</th>
                            <td>
                                <select name="status">
                                    <?php foreach (['subscribed','pending','unsubscribed','bounced','complained'] as $status) : ?>
                                        <option value="<?php echo esc_attr($status); ?>" <?php selected($settings['status'], $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tags</th>
                            <td>
                                <select name="tag_ids[]" multiple style="min-width:250px;">
                                    <?php foreach ($tags as $tag) : ?>
                                        <option value="<?php echo esc_attr($tag['id']); ?>" <?php selected(in_array($tag['id'], $settings['tag_ids'], true)); ?>><?php echo esc_html($tag['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Lists</th>
                            <td>
                                <select name="list_ids[]" multiple style="min-width:250px;">
                                    <?php foreach ($lists as $list) : ?>
                                        <option value="<?php echo esc_attr($list['id']); ?>" <?php selected(in_array($list['id'], $settings['list_ids'], true)); ?>><?php echo esc_html($list['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Mapping'); ?>
                </form>
            </div>
            <div id="pages" class="flfbl-section" style="display:none;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('flfbl_refresh_pages'); ?>
                    <input type="hidden" name="action" value="flfbl_refresh_pages">
                    <?php submit_button('Refresh Pages'); ?>
                </form>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($settings['pages']) : ?>
                        <?php foreach ($settings['pages'] as $page) : ?>
                            <tr>
                                <td><?php echo esc_html($page['name']); ?></td>
                                <td><?php echo $page['subscribed'] ? 'Subscribed' : 'Not Subscribed'; ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php
                                        $nonce_action = $page['subscribed'] ? 'flfbl_unsubscribe_page' : 'flfbl_subscribe_page';
                                        wp_nonce_field($nonce_action);
                                        ?>
                                        <input type="hidden" name="action" value="<?php echo esc_attr($nonce_action); ?>">
                                        <input type="hidden" name="page_id" value="<?php echo esc_attr($page['id']); ?>">
                                        <?php submit_button($page['subscribed'] ? 'Unsubscribe' : 'Subscribe', 'secondary', '', false); ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3">No pages found. Save credentials and tokens, then refresh pages.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        (function() {
            const tabs = document.querySelectorAll('.nav-tab');
            const sections = document.querySelectorAll('.flfbl-section');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    tabs.forEach(function(t){ t.classList.remove('nav-tab-active'); });
                    sections.forEach(function(s){ s.style.display = 'none'; });
                    tab.classList.add('nav-tab-active');
                    document.querySelector(tab.getAttribute('href')).style.display = 'block';
                });
            });
        })();
        </script>
        <?php
    }

    public function save_credentials(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('flfbl_save_credentials')) {
            wp_die('Unauthorized');
        }
        $app_id = isset($_POST['app_id']) ? sanitize_text_field(wp_unslash($_POST['app_id'])) : '';
        $app_secret = isset($_POST['app_secret']) ? sanitize_text_field(wp_unslash($_POST['app_secret'])) : '';
        $verify_token = isset($_POST['verify_token']) ? sanitize_text_field(wp_unslash($_POST['verify_token'])) : '';
        $this->settings->update([
            'app_id' => $app_id,
            'app_secret' => $app_secret,
            'verify_token' => $verify_token,
        ]);
        add_settings_error('flfbl', 'credentials_saved', 'Credentials saved.', 'updated');
        wp_safe_redirect(admin_url('admin.php?page=flfbl#credentials'));
        exit;
    }

    public function exchange_token(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('flfbl_exchange_token')) {
            wp_die('Unauthorized');
        }
        $user_token = isset($_POST['user_token']) ? trim(wp_unslash($_POST['user_token'])) : '';
        $long_lived = isset($_POST['long_lived_token']) ? trim(wp_unslash($_POST['long_lived_token'])) : '';
        if ($user_token) {
            $exchanged = $this->client->exchange_token($user_token);
            if ($exchanged) {
                $long_lived = $exchanged;
                add_settings_error('flfbl', 'token_exchanged', 'Long-lived token generated.', 'updated');
            } else {
                add_settings_error('flfbl', 'token_failed', 'Unable to exchange token.', 'error');
            }
        }
        $this->settings->update([
            'user_token' => $user_token,
            'long_lived_token' => $long_lived,
        ]);
        if ($long_lived) {
            $pages = $this->client->fetch_pages($long_lived);
            if ($pages) {
                $stored = $this->settings->get('pages', []);
                $existing = [];
                foreach ($stored as $page) {
                    $existing[$page['id']] = $page;
                }
                foreach ($pages as $page) {
                    if (isset($existing[$page['id']])) {
                        $page['subscribed'] = $existing[$page['id']]['subscribed'];
                    }
                    $existing[$page['id']] = $page;
                }
                $this->settings->update(['pages' => array_values($existing)]);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=flfbl#credentials'));
        exit;
    }

    public function save_mapping(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('flfbl_save_mapping')) {
            wp_die('Unauthorized');
        }
        $standard = isset($_POST['standard_map']) ? (array) $_POST['standard_map'] : [];
        $field_map = [];
        foreach ($standard as $target => $fb) {
            $fb = sanitize_text_field(wp_unslash($fb));
            $target = sanitize_text_field(wp_unslash($target));
            if ($fb && $target) {
                $field_map[strtolower($fb)] = $target;
            }
        }
        $custom_fb = isset($_POST['custom_fb']) ? (array) $_POST['custom_fb'] : [];
        $custom_meta = isset($_POST['custom_meta']) ? (array) $_POST['custom_meta'] : [];
        $custom_map = [];
        foreach ($custom_fb as $index => $fb) {
            $fb_name = sanitize_text_field(wp_unslash($fb));
            $meta_key = isset($custom_meta[$index]) ? sanitize_text_field(wp_unslash($custom_meta[$index])) : '';
            if ($fb_name && $meta_key) {
                $custom_map[strtolower($fb_name)] = $meta_key;
            }
        }
        $tag_ids = isset($_POST['tag_ids']) ? array_map('intval', (array) $_POST['tag_ids']) : [];
        $list_ids = isset($_POST['list_ids']) ? array_map('intval', (array) $_POST['list_ids']) : [];
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'subscribed';
        $this->settings->update([
            'field_map' => $field_map,
            'custom_field_map' => $custom_map,
            'tag_ids' => $tag_ids,
            'list_ids' => $list_ids,
            'status' => $status,
        ]);
        add_settings_error('flfbl', 'mapping_saved', 'Mapping saved.', 'updated');
        wp_safe_redirect(admin_url('admin.php?page=flfbl#field-mapping'));
        exit;
    }

    public function subscribe_page(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('flfbl_subscribe_page')) {
            wp_die('Unauthorized');
        }
        $page_id = isset($_POST['page_id']) ? sanitize_text_field(wp_unslash($_POST['page_id'])) : '';
        $pages = $this->settings->get('pages', []);
        $page_token = '';
        foreach ($pages as $page) {
            if ($page['id'] === $page_id) {
                $page_token = $page['access_token'];
                break;
            }
        }
        if ($page_token && $this->client->subscribe_page($page_id, $page_token)) {
            foreach ($pages as &$page) {
                if ($page['id'] === $page_id) {
                    $page['subscribed'] = true;
                }
            }
            $this->settings->update(['pages' => $pages]);
            add_settings_error('flfbl', 'subscribed', 'Page subscribed.', 'updated');
        } else {
            add_settings_error('flfbl', 'subscribe_failed', 'Subscription failed.', 'error');
        }
        wp_safe_redirect(admin_url('admin.php?page=flfbl#pages'));
        exit;
    }

    public function unsubscribe_page(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('flfbl_unsubscribe_page')) {
            wp_die('Unauthorized');
        }
        $page_id = isset($_POST['page_id']) ? sanitize_text_field(wp_unslash($_POST['page_id'])) : '';
        $pages = $this->settings->get('pages', []);
        $page_token = '';
        foreach ($pages as $page) {
            if ($page['id'] === $page_id) {
                $page_token = $page['access_token'];
                break;
            }
        }
        if ($page_token && $this->client->unsubscribe_page($page_id, $page_token)) {
            foreach ($pages as &$page) {
                if ($page['id'] === $page_id) {
                    $page['subscribed'] = false;
                }
            }
            $this->settings->update(['pages' => $pages]);
            add_settings_error('flfbl', 'unsubscribed', 'Page unsubscribed.', 'updated');
        } else {
            add_settings_error('flfbl', 'unsubscribe_failed', 'Unsubscribe failed.', 'error');
        }
        wp_safe_redirect(admin_url('admin.php?page=flfbl#pages'));
        exit;
    }

    public function refresh_pages(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('flfbl_refresh_pages')) {
            wp_die('Unauthorized');
        }
        $token = $this->settings->get('long_lived_token');
        if (!$token) {
            add_settings_error('flfbl', 'no_token', 'Set a long-lived token first.', 'error');
            wp_safe_redirect(admin_url('admin.php?page=flfbl#pages'));
            exit;
        }
        $pages = $this->client->fetch_pages($token);
        if ($pages) {
            $stored = $this->settings->get('pages', []);
            $existing = [];
            foreach ($stored as $page) {
                $existing[$page['id']] = $page;
            }
            foreach ($pages as $page) {
                if (isset($existing[$page['id']])) {
                    $page['subscribed'] = $existing[$page['id']]['subscribed'];
                }
                $existing[$page['id']] = $page;
            }
            $this->settings->update(['pages' => array_values($existing)]);
            add_settings_error('flfbl', 'pages_refreshed', 'Pages refreshed.', 'updated');
        } else {
            add_settings_error('flfbl', 'pages_failed', 'Unable to fetch pages.', 'error');
        }
        wp_safe_redirect(admin_url('admin.php?page=flfbl#pages'));
        exit;
    }
}
