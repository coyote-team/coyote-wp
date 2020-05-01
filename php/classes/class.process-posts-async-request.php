<?php

namespace Coyote;

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.wp-async-request.php');
require_once coyote_plugin_file('classes/helpers/class.post-process-helper.php');

use \WP_Async_Request;

use Coyote\Logger;
use Coyote\Helpers\PostProcessHelper;

class ProcessPostsAsyncRequest extends WP_Async_Request {
    protected $action = 'coyote_process_existing_posts';

    protected function handle() {
        Logger::log('Processing existing posts');

        $update_progress = function ($progress) {
            set_transient('coyote_process_posts_progress', $progress);
        };

        $update_progress(0);

        $posts = get_posts(array(
            'numberposts' => -1, //all
            'post_type' => array('post', 'page')
        ));

        $processed = 0;
        $total = count($posts);

        $update_progress((int) (($processed / $total) * 100));

        foreach ($posts as $post) {
            // simulate a post update
            Logger::log("Processing post {$post->ID}, {$processed} of {$total}");
            PostProcessHelper::processExistingPost($post);

            $processed++;
            $update_progress((int) (($processed / $total) * 100));
        }

        Logger::log('Done processing existing posts');

        delete_transient('coyote_process_posts_progress');
    }
}
