<?php

/**
 * Various Hooks and Filters
 * @category class
 * @package Coyote\HooksAndFilters
 * @since 1.0
 */

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\ContentHelper;
use Coyote\DB\ResourceRecord;
use Coyote\Model\ResourceModel;

class HooksAndFilters {
    private $plugin;

    public function __construct(\Coyote\Plugin $plugin) {
        $this->plugin = $plugin;
    }

    public function run() {
        $plugin = $this->plugin;

        add_action('coyote_check_standalone_hook', [$this, 'check_standalone']);

        // add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename($plugin->file), [$this, 'add_action_links']);

        // display any errors
        add_action('admin_notices', [$this, 'display_admin_notices']);

        // api client action handlers
        add_action('coyote_api_client_error', [$this, 'on_api_client_error']);
        add_action('coyote_api_client_success', [$this, 'on_api_client_success']);

        if ($plugin->has_filters_enabled && $plugin->is_configured) {
            Logger::log('Filters enabled.');

            add_filter('the_content', [$this, 'filter_post_content'], 10, 1);
//            add_filter('the_editor_content', [$this, 'filter_post_content'], 10, 1);
            add_filter('wp_prepare_attachment_for_js', [$this, 'filter_attachment_for_js'], 10, 3);
            add_filter('wp_get_attachment_image_attributes', [$this, 'filter_attachment_image_attributes'], 10, 3);

//            add_filter('rest_prepare_post', [$this, 'filter_gutenberg_content'], 10, 3);
//            add_filter('rest_prepare_page', [$this, 'filter_gutenberg_content'], 10, 3);

            if (!$plugin->is_standalone) {
                // handle updates to posts made by the front-end
                add_filter('wp_insert_post_data', ['Coyote\Handlers\PostUpdateHandler', 'run'], 10, 2);

                // allow custom resource management link in tinymce
                add_action('admin_init', [$this, 'add_tinymce_plugin']);

                // load custom admin functionality scripts to patch alt fields
                add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
            }
        } else {
            Logger::log('Filters disabled.');
        }

        if (!$plugin->is_standalone) {
            add_action('wp_ajax_coyote_load_process_batch', array('Coyote\Batching', 'load_process_batch'));
            add_action('wp_ajax_nopriv_coyote_load_process_batch', array('Coyote\Batching', 'load_process_batch'));

            add_action('wp_ajax_coyote_set_batch_job', array('Coyote\Batching', 'ajax_set_batch_job'));
            add_action('wp_ajax_nopriv_coyote_set_batch_job', array('Coyote\Batching', 'ajax_set_batch_job'));

            add_action('wp_ajax_coyote_clear_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));
            add_action('wp_ajax_nopriv_coyote_clear_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));

            add_action('wp_ajax_coyote_cancel_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));
            add_action('wp_ajax_nopriv_coyote_cancel_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));

            add_action('wp_ajax_coyote_verify_resource_group', array('Coyote\Controllers\SettingsController', 'ajax_verify_resource_group'));
        }

        add_filter('cron_schedules', function ($schedules) {
            $schedules['five_minutes'] = [
                'interval' => 300,
                'display'  => esc_html__('Every Five Minutes')
            ];
            return $schedules;
        });

        if ($plugin->is_standalone && $plugin->is_standalone_error) {
            Logger::log('checking hook');
            if (!wp_next_scheduled('coyote_check_standalone_hook')) {
                // setting standalone recovery wp-cron hook

                Logger::log('Setting standalone recovery wp-cron hook');
                wp_schedule_event(time(), 'five_minutes', 'coyote_check_standalone_hook');
            } else {
                Logger::log('Standalone recovery hook already scheduled');
            }
        }
    }

    public function admin_enqueue_scripts(): void
    {
        global $post;
        global $coyote_plugin;

        if (is_null($post)) {
            return;
        }

        // FIXME [JKVA] is this used within the media manager?
        if ($post->post_type !== 'attachment') {
            return;
        }

        $image = new WordPressImage(new ContentHelper\Image(
            coyote_attachment_url($post->ID),
            '',
            ''
        ));

        /** @var ResourceRecord|null $resource */
        $resource = WordpressHelper::getResourceForWordPressImage($image, PluginConfiguration::isEnabled());

        if (is_null($resource)) {
            return;
        }

        $link = implode('/', [
            $coyote_plugin->config['CoyoteApiEndpoint'],
            'organizations',
            $coyote_plugin->config['CoyoteApiOrganizationId'],
            'resources',
            $resource->getResourceId()
        ]);

        $alt = esc_html($resource->getCoyoteDescription());

        echo <<<js
<script>
    window.coyote = {};
    window.coyote.post_data = {
        management_link: "{$link}",
        alt: "{$alt}",
    };
</script>
js;

        wp_enqueue_script(
            'coyote_hook_alt_js',
            coyote_asset_url('hook_alt_fields.js'),
            false
        );
    }

    public function check_standalone() {
        Logger::log('check_standalone hook firing');

        if ($this->plugin->is_standalone &&
            $this->plugin->is_standalone_error
        ) {
            $profile = WordPressCoyoteApiClient::getProfile();

            if (!is_null($profile)) {
                // if we can obtain the profile, disable standalone mode
                // and clear the scheduled event
                Logger::log('Recovering from standalone mode');
                $this->on_api_client_success();
            } else {
                Logger::log('Unable to recover from standalone mode');
            }
        }
    }

    public function display_admin_notices() {
        $error_count = intval(get_transient('coyote_api_error_count'));

        if (!$this->plugin->is_standalone && $error_count >= 10) {
            update_option('coyote_is_standalone', true);
            update_option('coyote_error_standalone', true);

            $this->plugin->is_standalone = true;
            $this->plugin->is_standalone_error = true;

            $message = __("The Coyote API client has thrown 10 consecutive errors, the Coyote plugin has switched to standalone mode.", COYOTE_I18N_NS);

            echo sprintf("<div class=\"notice notice-error\">
                    <p>%s</p>
                </div>", $message);
        }
    }

    public function on_api_client_error($message) {
        Logger::log("Coyote API error: ${message}");

        $error_count = get_transient('coyote_api_error_count');

        if ($error_count === false) {
            $error_count = 1;
        } else {
            $error_count = intval($error_count) + 1;
        }

        Logger::log("Updating API error count to ${error_count}");

        set_transient('coyote_api_error_count', $error_count);
    }

    public function on_api_client_success() {
        if ($this->plugin->is_standalone && $this->plugin->is_standalone_error) {
            // plugin is in standalone because of api errors, a success can recover.
            // we don't recover in case of manual standalone.
            update_option('coyote_is_standalone', false);
            update_option('coyote_error_standalone', false);

            // clear the cron recovery attempt logic
            Logger::log('Unscheduling standalone check');
            wp_clear_scheduled_hook('coyote_check_standalone_hook');

            $this->plugin->is_standalone = false;
            $this->plugin->is_standalone_error = false;
        }

        // clear any existing api error count
        delete_transient('coyote_api_error_count');
    }

    public function filter_attachment_image_attributes($attr, $attachment, $size) {
        // get a coyote resource for this attachment. If not found, try to create it unless
        // running in standalone mode.

        $image = new WordPressImage(new ContentHelper\Image(
            coyote_attachment_url($attachment->ID),
            '',
            ''
        ));

        $resource = WordPressHelper::getResourceForWordPressImage($image, PluginConfiguration::isEnabled());

        if (!is_null($resource)) {
            $attr['alt'] = $resource->getCoyoteDescription();
        }

        return $attr;
    }

    // used in the media template
    public function filter_attachment_for_js($response, $attachment, $meta) {
        if ($response['type'] !== 'image') {
            return $response;
        }

        $image = new WordPressImage(new ContentHelper\Image(
            coyote_attachment_url($attachment->ID),
            $response['alt'],
            ''
        ));

        $image->setCaption($response['caption'] ?? '');

        $resource = WordPressHelper::getResourceForWordPressImage($image, PluginConfiguration::isEnabled());

        if (!$resource) {
            return $response;
        }

        $response['alt'] = $resource->getCoyoteDescription();
        $response['coyoteManagementUrl'] = implode('/', [
            $this->plugin->config['CoyoteApiEndpoint'],
            'organizations',
            $this->plugin->config['CoyoteApiOrganizationId'],
            'resources',
            $resource->getResourceId()
        ]);

        return $response;
    }

    public function filter_gutenberg_content($response, $post, $request) {
        if (in_array('content', $response->data)) {
	        $response->data['content']['raw'] = $this->filter_post_content($response->data['content']['raw']);
        }

	    return $response;
    }

    public function filter_post_content($post_content) {
        global $post;

        if ($post->post_type === 'attachment') {
            Logger::log("Attachment post already processed, skipping");
            return $post_content;
        }

        return WordPressHelper::setImageAlts($post, PluginConfiguration::isEnabled());
    }

    public function add_tinymce_plugin() {
        add_filter('mce_external_plugins', function($plugins) {
            $plugins['coyote'] = coyote_asset_url('tinymce_plugin.js');
            return $plugins;
        });
    }

    // add setting quicklink to plugin listing entry
    public function add_action_links($links) {
        $settings_links = array(
            '<a href="' . admin_url('options-general.php?page=coyote_fields') . '"> ' . __('Settings') . '</a>',
        );

        return array_merge($links, $settings_links);
    }
}
