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
use Coyote\AsyncProcessRequest;
use Coyote\BatchProcessExistingState;
use Coyote\BatchRestoreState;
use Coyote\Handlers\PostUpdateHandler;
use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;

use Coyote\BatchProcessorState;
use Coyote\Helpers\PostHelper;

use Coyote\ImageResource;
use Coyote\Helpers\ContentHelper;

class Plugin {
    private $is_activated = false;
    private $is_admin = false;
    private $process_posts_async_request;

    private $file;
    private $version;

    public $config = [
        'CoyoteApiVersion'      => "1",
        'CoyoteApiToken'        => null,
        'CoyoteApiEndpoint'     => "",
        'CoyoteOrganizationId'  => null,
        'AsyncMethod'           => 'post'
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
        $_config['CoyoteOrganizationId'] = get_option('coyote__api_settings_organization_id', $_config['CoyoteOrganizationId']);
        $_config['AsyncMethod']          = get_option('coyote__api_settings_method', $_config['AsyncMethod']);


        if (get_option('coyote__api_profile')) {
            $this->is_configured = true;
        }

        $this->config = $_config;
    }

    private function setup() {
        // $wpdb becomes available here
        global $wpdb;
        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');
        define('COYOTE_JOIN_TABLE_NAME', $wpdb->prefix . 'coyote_resource_post_jt');

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
            $this->async_restore_request = new AsyncRestoreRequest($this->config['AsyncMethod']);
        }

        if (!$this->is_configured) {
            return;
        }

        // allow remote updates
        (new RestApiController($this->version));

        // handle updates to posts made by the front-end
//        add_filter('wp_insert_post_data', array('Coyote\Handlers\PostUpdateHandler', 'run'), 10, 2);

        add_action('plugins_loaded', array($this, 'loaded'), 10, 0);

        // only allow post processing if there is a valid api configuration
        // and there is not already a post-processing in place.
        add_action('coyote_process_existing_posts', array($this, 'process_existing_posts'), 10, 1);

        // allow asynchronous post processing to take place
        $this->async_process_request = new AsyncProcessRequest($this->config['AsyncMethod']);

        add_action('admin_init', [$this, 'add_tinymce_plugin']);

        add_action( 'wp_ajax_coyote_load_process_batch', array( $this, 'load_process_batch' ) );
        add_action( 'wp_ajax_nopriv_coyote_load_process_batch', array( $this, 'load_process_batch' ) );

        add_action( 'wp_ajax_coyote_load_restore_batch', array( $this, 'load_restore_batch' ) );
        add_action( 'wp_ajax_nopriv_coyote_load_restore_batch', array( $this, 'load_restore_batch' ) );

        add_action( 'wp_ajax_coyote_process_post', array( $this, 'process_post' ) );
        add_action( 'wp_ajax_nopriv_coyote_process_post', array( $this, 'process_post' ) );

        add_action( 'wp_ajax_coyote_restore_post', array( $this, 'restore_post' ) );
        add_action( 'wp_ajax_nopriv_coyote_restore_post', array( $this, 'restore_post' ) );
    }

    public function load_process_batch() {
        // Don't lock up other requests while processing
        session_write_close();

//        check_ajax_referer('coyote_process_existing_posts', 'nonce');

        $batch_size = $_GET['size'];

        echo json_encode($this->get_process_batch($batch_size));

        wp_die();
    }

    public function load_restore_batch() {
        // Don't lock up other requests while processing
        session_write_close();

//        check_ajax_referer('coyote_process_existing_posts', 'nonce');

        $batch_size = $_GET['size'];

        echo json_encode($this->get_restore_batch($batch_size));

        wp_die();
    }


    public function restore_post($post) {
        session_write_close();

        $post_id = $_GET['post_id'];
        Logger::log("Restoring post {$post_id}");

        set_transient('post_restore_' . $post_id, true);

        $post = get_post($post_id);

        $resources = DB::get_resources_for_post($post->ID);
        $helper = new ContentHelper($post->post_content);

        foreach ($resources as $resource) {
            $helper->restore_resource($resource->coyote_resource_id, $resource->original_description);
        }

        $post->post_content = $helper->get_content();

        wp_update_post($post);
        wp_save_post_revision($post->ID);

        delete_transient('post_restore_' . $post_id);

        wp_die();
    }

    public function process_post() {
        session_write_close();

        $post_id = $_GET['post_id'];
        Logger::log("Processing post {$post_id}");

        set_transient('post_process_' . $post_id, true);

        $post = get_post($post_id);

        $helper = new ContentHelper($post->post_content);
        $images = $helper->get_images();

        $associated = array();

        $resources = get_transient('process_batch_resources');

        foreach ($images as $image) {
            if ($image['coyote_id'] !== null) {
                continue;
            }

            if ($resource = $resources[$image['src']]) {
                if (!$resource['id']) {
                    Logger::log("Resource for {$image['src']} has no id? Skipping");
                    continue;
                }
                $alt = $resource['alt'] === null ? '' : $resource['alt'];
                $helper->set_coyote_id_and_alt($image['element'], $resource['id'], $alt);
                Logger::log("Associated {$resource['id']} with image {$image['src']} in post {$post->ID}");
                array_push($associated, $resource['id']);
            } else {
                Logger::log("Couldn't find resource for {$image['src']}?");
                continue;
            }
        }

        if (!$helper->content_is_modified) {
            Logger::log("No modifications made, done.");
            return;
        }

        $post->post_content = $helper->get_content();
        $result = wp_update_post($post, true);

        delete_transient('post_process_' . $post_id);

        if (is_wp_error($result)) {
            throw $result;
        } else {
            wp_save_post_revision($post->ID);
            //if the post update succeeded, then associate the resources with the post
            DB::associate_resource_ids_with_post($associated, $post->ID);
        }

        wp_die();
    }

    private function get_restore_batch($size) {
        $post_types = ['page', 'post'];

        $offset = get_transient('restore_batch_offset');

        $response = [];

        $post_ids = DB::get_edited_post_ids();

        if ($offset === false) {
            $offset = 0;
            $response['total'] = count($post_ids);
        }

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $offset,
            'numberposts' => $size,
            'post_type'   => $post_types,
            'post_status' => 'publish',
            'post_in'     => $post_ids
        ));

        $ids = wp_list_pluck($batch, 'ID');

        $response['ids'] = $ids;

        if (count($batch) === 0) {
            // no more posts
            delete_transient('restore_batch_offset');
        } else {
            set_transient('restore_batch_offset', $offset + count($batch));
        }

        return $response;
    }


    private function get_process_batch($size) {
        $post_types = ['page', 'post'];

        $offset = get_transient('process_batch_offset');

        $response = [];

        if ($offset === false) {
            $offset = 0;

            $total_posts = array_reduce($post_types, function($carry, $type) {
                return $carry + wp_count_posts($type)->publish;
            }, 0);

            $response['total'] = $total_posts;
        }

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $offset,
            'numberposts' => $size,
            'post_type'   => $post_types,
            'post_status' => 'publish'
        ));

        $resources = $this->fetch_resources($batch);

        set_transient('process_batch_resources', $resources);

        $ids = wp_list_pluck($batch, 'ID');

        $response['ids'] = $ids;

        if (count($batch) === 0) {
            // no more posts
            delete_transient('process_batch_offset');
        } else {
            set_transient('process_batch_offset', $offset + count($batch));
        }

        return $response;
    }

    private function fetch_resources($posts) {
        $all_images = array();

        foreach ($posts as $post) {
            $helper = new ContentHelper($post->post_content);
            $images = $helper->get_images();
            foreach ($images as $image) {
                $all_images[$image['src']] = $image;
            }
        }

        $resources = ImageResource::resources_from_images(array_values($all_images));

        return array_reduce($resources, function($carry, $resource) {
            $carry[$resource->image['src']] = array(
                'id'  => $resource->coyote_resource_id,
                'alt' => $resource->coyote_description
            );

            return $carry;
        }, array());
    }


    public function loaded() {
        // The loading order for this is important, otherwise required WP functions aren't available
        if (BatchProcessExistingState::has_stale_state()) {
            do_action('coyote_process_existing_posts');
        }

        if (BatchRestoreState::has_stale_state()) {
            do_action('coyote_restore_posts');
        }
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
            '%resource_post_join_table_name%',
            '%wp_post_table_name%',
            '%charset_collate%'
        );
        
        $replace_strings = array(
            COYOTE_IMAGE_TABLE_NAME,
            COYOTE_JOIN_TABLE_NAME,
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

    public function process_existing_posts($default_batch_size = 5) {
        $batch_size = isset($_POST['batchSize']) ? $_POST['batchSize'] : $default_batch_size;

        $batch_size = intval($batch_size);

        // minimum batch size
        if ($batch_size < 1) { $batch_size = 1; }

        // maximum batch size, to keep from running out of memory
        else if ($batch_size > 500) { $batch_size = 500; }

        if ($this->config['AsyncMethod'] === 'get') {
            $this->async_process_request->query_args = ['batch_size' => $batch_size];
        } else {
            $this->async_process_request->data(array('batch_size' => $batch_size));
        }

        $this->async_process_request->dispatch();
    }

    public function activate() {
        if ($this->is_activated) {
            Logger::log("Plugin already active");
            return;
        }

        Logger::log("Activating plugin");
        // for some weird reason you can't create multiple tables at once?
        $this->run_plugin_sql(coyote_sql_file('create_resource_table.sql'));
        $this->run_plugin_sql(coyote_sql_file('create_join_table.sql'));

        $this->is_activated = true;
        add_option('coyote_plugin_is_activated', $this->is_activated);
    }

    public function deactivate() {
        Logger::log("Deactivating plugin");

        // don't trigger update filters when removing coyote ids
        remove_filter('wp_insert_post_data', array('Coyote\Handlers\PostUpdateHandler', 'run'), 10);

        $this->run_plugin_sql(coyote_sql_file('deactivate_plugin.sql'));
        delete_option('coyote_plugin_is_activated');
    }
}

