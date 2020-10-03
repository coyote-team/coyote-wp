<?php

/**
 * Coyote Plugin
 * @package Coyote\Plugin
 */

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;
use Coyote\Helpers\ContentHelper;

class Plugin {
    private $is_installed = false;
    private $is_admin = false;
    private $has_filters_enabled = false;
    private $has_updates_enabled = false;

    private $file;
    private $version;

    public $is_standalone = false;

    public $config = [
        'CoyoteApiVersion'         => "1",
        'CoyoteApiToken'           => null,
        'CoyoteApiEndpoint'        => "",
        'CoyoteApiMetum'           => 'Alt',
        'CoyoteApiOrganizationId'  => null,
        'CoyoteApiResourceGroupId' => null,
        'ProcessTypes'          => ['page', 'post', 'attachment'],
        'ProcessStatuses'       => ['publish'],
    ];

    public $is_configured = false;

    /**
     * Plugin constructor.
     * @param string $file
     * @param string $version
     * @param bool $is_admin
     */
    public function __construct(string $file, string $version, bool $is_admin = false) {
        if(get_option('coyote_plugin_is_installed', null) !== null) {
            $this->is_installed = true;
        }

        $this->file = $file;
        $this->version = $version;
        $this->is_admin = $is_admin;
        $this->is_standalone = get_option('coyote_is_standalone', false);

        $this->setup();
    }

    private function load_config() {
        $_config = $this->config;

        $_config['CoyoteApiVersion']         = get_option('coyote_api_version',           $_config['CoyoteApiVersion']);
        $_config['CoyoteApiToken']           = get_option('coyote_api_token',             $_config['CoyoteApiToken']);
        $_config['CoyoteApiEndpoint']        = get_option('coyote_api_endpoint',          $_config['CoyoteApiEndpoint']);
        $_config['CoyoteApiMetum']           = get_option('coyote_api_metum',             $_config['CoyoteApiMetum']);
        $_config['CoyoteApiOrganizationId']  = intval(get_option('coyote_api_organization_id',   $_config['CoyoteApiOrganizationId']));
        $_config['CoyoteApiResourceGroupId'] = intval(get_option('coyote_api_resource_group_id', $_config['CoyoteApiResourceGroupId']));

        $_config['ProcessTypes']    = get_option('coyote_post_types',    $_config['ProcessTypes']);
        $_config['ProcessStatuses'] = get_option('coyote_post_statuses', $_config['ProcessStatuses']);

        if (get_option('coyote_api_profile')) {
            $this->is_configured = true;
        }

        $this->config = $_config;
    }

    private function setup() {
        // $wpdb becomes available here
        global $wpdb;
        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');

        register_activation_hook($this->file, [$this, 'activate']);
        register_deactivation_hook($this->file, [$this, 'deactivate']);
        register_uninstall_hook($this->file, ['Coyote\Plugin', 'uninstall']);

        $this->has_filters_enabled = get_option('coyote_filters_enabled', false);

        // only load updates option if we're not in standalone mode
        if (!$this->is_standalone) {
            $this->has_updates_enabled = get_option('coyote_updates_enabled', false);
        }

        $this->load_config();

        if (!$this->is_installed) {
            return;
        }

        // add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename($this->file), [$this, 'add_action_links']);

        // display any errors
        add_action('admin_notices', [$this, 'display_admin_notices']);

        if (!$this->is_standalone) {
            // api client action handlers
            add_action('coyote_api_client_error', [$this, 'on_api_client_error']);
            add_action('coyote_api_client_success', [$this, 'on_api_client_success']);
        }

        if ($this->has_filters_enabled && $this->is_configured) {
            Logger::log('Filters enabled.');

            add_filter('the_content', [$this, 'filter_post_content'], 10, 1);
            add_filter('the_editor_content', [$this, 'filter_post_content'], 10, 1);
            add_filter('wp_prepare_attachment_for_js', [$this, 'filter_attachment_for_js'], 10, 3);
            add_filter('wp_get_attachment_image_attributes', [$this, 'filter_attachment_image_attributes'], 10, 3);

            add_filter('rest_prepare_post', [$this, 'filter_gutenberg_content'], 10, 3);
            add_filter('rest_prepare_page', [$this, 'filter_gutenberg_content'], 10, 3);

            if (!$this->is_standalone) {
                // handle updates to posts made by the front-end
                add_filter('wp_insert_post_data', ['Coyote\Handlers\PostUpdateHandler', 'run'], 10, 2);

                // allow custom resource management link in tinymce
                add_action('admin_init', [$this, 'add_tinymce_plugin']);
            }
        } else {
            Logger::log('Filters disabled.');
        }

        if ($this->is_admin) {
            (new SettingsController());
        }

        if (!$this->is_configured) {
            return;
        }

        if ($this->has_updates_enabled) {
            Logger::log('Updates enabled.');
            // allow remote updates
            (new RestApiController($this->version, 1, $this->config['CoyoteApiOrganizationId'], $this->config['CoyoteApiMetum']));
        } else {
            Logger::log('Updates disabled.');
        }

        if (!$this->is_standalone) {
            add_action('wp_ajax_coyote_load_process_batch', array('Coyote\Batching', 'load_process_batch'));
            add_action('wp_ajax_nopriv_coyote_load_process_batch', array('Coyote\Batching', 'load_process_batch'));

            add_action('wp_ajax_coyote_set_batch_job', array('Coyote\Batching', 'ajax_set_batch_job'));
            add_action('wp_ajax_nopriv_coyote_set_batch_job', array('Coyote\Batching', 'ajax_set_batch_job'));

            add_action('wp_ajax_coyote_clear_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));
            add_action('wp_ajax_nopriv_coyote_clear_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));

            add_action('wp_ajax_coyote_cancel_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));
            add_action('wp_ajax_nopriv_coyote_cancel_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));
        }
    }

    public function display_admin_notices() {
        $error_count = intval(get_transient('coyote_api_error_count'));

        if ($error_count >= 10) {
            delete_transient('coyote_api_error_count');
            update_option('coyote_is_standalone', true);

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

    public function on_api_client_success($message) {
        // clear any existing api error count
        delete_transient('coyote_api_error_count');
    }

    public function filter_attachment_image_attributes($attr, $attachment, $size) {
        // get a coyote resource for this attachment. If not found, try to create it unless
        // running in standalone mode.
        $url = wp_get_attachment_url($attachment->ID);

        $data = CoyoteResource::get_coyote_id_and_alt([
            'src'       => $url,
            'alt'       => '',
            'caption'   => '',
            'element'   => null,
            'host_uri'  => null
        ], !$this->is_standalone);

        if ($data) {
            $attr['alt'] = $data['alt'];
        }

        return $attr;
    }

    // used in the media template
    public function filter_attachment_for_js($response, $attachment, $meta) {
        if ($response['type'] !== 'image') {
            return $response;
        }

        // get a coyote resource for this attachment. If not found, try to create it unless
        // running in standalone mode.
        $url = wp_get_attachment_url($attachment->ID);

        $data = CoyoteResource::get_coyote_id_and_alt([
            'src'       => $url,
            'alt'       => $response['alt'],
            'caption'   => $response['caption'],
            'element'   => null,
            'host_uri'  => null
        ], !$this->is_standalone);

        if (!$data) {
            return $response;
        }

        $response['alt'] = $data['alt'];
        $response['coyoteManagementUrl'] = implode('/', [
            $this->config['CoyoteApiEndpoint'], 'organizations', $this->config['CoyoteApiOrganizationId'], 'resources', $data['id']
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
        }

        return $post_content;

        $helper = new ContentHelper($post_content);
        return $helper->replace_image_alts();
    }

    public function classic_editor_data() {
        global $post;

        if (empty($post)) {
            return '';
        }

        if (empty($post->post_type)) {
            return '';
        }

        $prefix = implode('/', [$this->config['CoyoteApiEndpoint'], 'organizations', $this->config['CoyoteApiOrganizationId']]);
        $helper = new ContentHelper($post->post_content);
        $mapping = $helper->get_src_and_coyote_id();
        $json_mapping = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<js
<script>
    window.coyote = {};
    window.coyote.classic_editor = {
        postId: "{$post->ID}",
        prefix: "{$prefix}",
        mapping: $json_mapping
    };
</script>
js;
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

    private function replace_sql_variables(string $sql) {
        global $wpdb;

        $search_strings = array(
            '%image_resource_table_name%',
            '%wp_post_table_name%',
            '%charset_collate%'
        );

        $replace_strings = array(
            COYOTE_IMAGE_TABLE_NAME,
            $wpdb->prefix . 'posts',
            $wpdb->get_charset_collate()
        );

        $sql = str_replace($search_strings, $replace_strings, $sql);
        return $sql;
    }

    private function run_sql_query(string $sql) {
        global $wpdb;
        $wpdb->query($sql);
    }

    private function run_plugin_sql(string $path) {
        $file_sql = file_get_contents($path);
        $sql = $this->replace_sql_variables($file_sql);
        $this->run_sql_query($sql);
    }

    public function activate() {
        if ($this->is_installed) {
            Logger::log("Plugin was active previously, not adding table");
            return;
        }

        Logger::log("Activating plugin");
        // for some weird reason you can't create multiple tables at once?
        $this->run_plugin_sql(coyote_sql_file('create_resource_table.sql'));
        $this->is_installed = true;

        add_option('coyote_plugin_is_installed', $this->is_installed);
    }

    public function deactivate() {
        Logger::log('Deactivating plugin');
    }

    public static function uninstall() {
        global $coyote_plugin;
        Logger::log("Uninstalling plugin");

        Logger::log("Deleting table");
        $coyote_plugin->run_plugin_sql(coyote_sql_file('uninstall_plugin.sql'));

        Logger::log("Deleting options");
        $options = [
            'coyote_api_version', 'coyote_api_token', 'coyote_api_endpoint', 'coyote_api_metum', 'coyote_api_organization_id',
            'coyote_api_profile',
            'coyote_filters_enabled', 'coyote_updates_enabled', 'coyote_processor_endpoint',
            'coyote_plugin_is_installed'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }
    }
}

