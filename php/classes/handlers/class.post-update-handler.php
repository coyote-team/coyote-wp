<?php

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/helpers/class.post-process-helper.php');

use Coyote\Helpers\PostProcessHelper;
use Coyote\Logger;

class PostUpdateHandler {

    public static function run(array $data, array $postArr) {
        if ($postArr['post_type'] == 'revision') {
            return $data;
        }

        if (get_transient('coyote_process_posts_progress') !== false) {
            Logger::log("Firing PostUpdateHandler while processing existing posts!");
            return;
        }

        $postID = $postArr['ID'];
        $processed = PostProcessHelper::processPostContent(wp_unslash($data['post_content']), $postID);
        $data['post_content'] = wp_slash($processed->content);

        return $data;
    }

}
