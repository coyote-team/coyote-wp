<?php

namespace Coyote;

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.plugin.php');
require_once coyote_plugin_file('classes/class.wp-async-request.php');
require_once coyote_plugin_file('classes/helpers/class.post-process-helper.php');
require_once coyote_plugin_file('classes/helpers/class.content-helper.php');

use \WP_Async_Request;

use Coyote\Logger;
use Coyote\Plugin;
use Coyote\Helpers\PostProcessHelper;
use Coyote\Helpers\ContentHelper;

class ProcessPostsAsyncRequest extends WP_Async_Request {
    protected $action = 'coyote_process_existing_posts';

    private function processPosts($posts) {
        $update_progress = function ($progress) {
            set_transient('coyote_process_posts_progress', $progress);
        };

        $update_progress(0);

        $post_images = array();

        $uris = array_reduce($posts, function($carry, $post) use (&$post_images) {
            $helper = new ContentHelper($post->post_content);
            $images = $helper->get_images_with_attributes();

            if ($images === null) {
                return $carry;
            }

            $post_images[$post->ID] = $images;

            foreach ($images as $image) {
                if (!array_key_exists($image['src'], $carry)) {
                    $carry[$image['src']] = true;
                }
            }

            return $carry;
        }, array());

        $uris = array_keys($uris);
        $resources = Plugin::get_api_client()->getResourcesBySourceUris($uris);

        $processed = 1;
        $total = count($posts);

        foreach ($posts as $post) {
            Logger::log("Processing post {$post->ID}, {$processed} of {$total}");
            $images = $post_images[$post->ID];

            if (count([$images])) {
                PostProcessHelper::processExistingPost($post, $images, $resources);
            }

            $processed++;

            $update_progress((int) (($processed / $total) * 100));
        }
    }

    protected function handle() {
        Logger::log('Processing existing posts');

        $posts = get_posts(array(
            'numberposts' => -1, //all
            'post_type' => array('post', 'page')
        ));

        try {
            self::processPosts($posts);
        } catch (Exception $error) {
            Logger::log("Error processing existing posts: " . $error->getMessage());
        } finally {
            delete_transient('coyote_process_posts_progress');
        }

        Logger::log('Done processing existing posts');
    }
}
