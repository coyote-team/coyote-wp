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

use Coyote\Logger;
use Coyote\Batching;
use Coyote\Handlers\PostUpdateHandler;
use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;
use Coyote\Helpers\ContentHelper;

class Plugin {
    private $is_activated = false;
    private $is_admin = false;

    private $file;
    private $version;

    public $config = [
        'CoyoteApiVersion'      => "1",
        'CoyoteApiToken'        => null,
        'CoyoteApiEndpoint'     => "",
        'CoyoteApiMetum'        => 'Alt (short)',
        'CoyoteOrganizationId'  => null
    ];

    public $instance_domain = 'https://coyote.staging.pics';

    public $is_configured = false;

    public function __construct(string $file, string $version, bool $is_admin = false) {
        if(get_option('coyote_plugin_is_activated', null) !== null) {
            $this->is_activated = true;
        }

        $this->file = $file;
        $this->version = $version;
        $this->is_admin = $is_admin;

        $this->setup();
    }

    private function load_config() {
        $_config = $this->config;

        $_config['CoyoteApiVersion']     = get_option('coyote__api_settings_version', $_config['CoyoteApiVersion']);
        $_config['CoyoteApiToken']       = get_option('coyote__api_settings_token', $_config['CoyoteApiToken']);
        $_config['CoyoteApiEndpoint']    = get_option('coyote__api_settings_endpoint', $_config['CoyoteApiEndpoint']);
        $_config['CoyoteApiMetum']       = get_option('coyote__api_settings_metum', $_config['CoyoteApiMetum']);
        $_config['CoyoteOrganizationId'] = get_option('coyote__api_settings_organization_id', $_config['CoyoteOrganizationId']);

        if (get_option('coyote__api_profile')) {
            $this->is_configured = true;
        }

        $this->config = $_config;
    }

    private function setup() {
        // $wpdb becomes available here
        global $wpdb;
        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');

        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));

        $this->load_config();

        if (!$this->is_activated) {
            return;
        }

        // add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename($this->file), array($this, 'add_action_links'));

        if ($this->is_admin) {
            (new SettingsController($this->version));
        }

        if (!$this->is_configured) {
            return;
        }

        // allow remote updates
        (new RestApiController($this->version));

        // handle updates to posts made by the front-end
        add_filter('wp_insert_post_data', array('Coyote\Handlers\PostUpdateHandler', 'run'), 10, 2);

        // allow custom resource management link in tinymce
        add_action('admin_init', [$this, 'add_tinymce_plugin']);

        // allow asynchronous post processing to take place

        add_action('wp_ajax_coyote_load_process_batch', array('Coyote\Batching', 'load_process_batch'));
        add_action('wp_ajax_nopriv_coyote_load_process_batch', array('Coyote\Batching', 'load_process_batch'));

        add_action('wp_ajax_coyote_set_batch_job', array('Coyote\Batching', 'ajax_set_batch_job'));
        add_action('wp_ajax_nopriv_coyote_set_batch_job', array('Coyote\Batching', 'ajax_set_batch_job'));

        add_action('wp_ajax_coyote_clear_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));
        add_action('wp_ajax_nopriv_coyote_clear_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));

        add_action('wp_ajax_coyote_cancel_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));
        add_action('wp_ajax_nopriv_coyote_cancel_batch_job', array('Coyote\Batching', 'ajax_clear_batch_job'));

        add_filter('the_content', [$this, 'filter_post_content'], 1);
        add_filter('the_editor_content', [$this, 'filter_editor_post_content'], 1);
    }

    public function filter_editor_post_content($post_content) {
        return $this->filter_post_content($post_content);
    }

    public function filter_post_content($post_content) {
        $helper = new ContentHelper($post_content);
        return $helper->replace_image_alts();
    }

    public function classic_editor_data() {
        global $post;
        $prefix = $this->config['CoyoteApiEndpoint'] . implode('/', ['organizations', $this->config['CoyoteOrganizationId']]);
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
        add_filter( 'mce_external_plugins', function($plugins) {
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
        if ($this->is_activated) {
            Logger::log("Plugin already active");
            return;
        }

        Logger::log("Activating plugin");
        // for some weird reason you can't create multiple tables at once?
        $this->run_plugin_sql(coyote_sql_file('create_resource_table.sql'));
        $this->is_activated = true;
        add_option('coyote_plugin_is_activated', $this->is_activated);
    }

    public function deactivate() {
        Logger::log("Deactivating plugin");
        $this->run_plugin_sql(coyote_sql_file('deactivate_plugin.sql'));
        delete_option('coyote_plugin_is_activated');
    }
}

