<?php

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/helpers/class.post-process-helper.php');
require_once coyote_plugin_file('classes/class.batch-post-processor.php');

use Coyote\BatchPostProcessorState;
use Coyote\Helpers\PostProcessHelper;
use Coyote\Logger;

class PostUpdateHandler {

    public static function run(array $data, array $postArr) {
        if ($postArr['post_type'] == 'revision') {
            return $data;
        }

        $post_id = $postArr['ID'];

        if ($state = BatchPostProcessorState::load()) {
            if ($state->current_post_id() === $post_id) {
                Logger::log("Firing PostUpdateHandler while processing existing post {$post_id}, skipping");
                return $data;
            }
        }

        $processed = PostProcessHelper::processPostContent(wp_unslash($data['post_content']), $post_id);
        $data['post_content'] = wp_slash($processed->content);

        return $data;
    }

}
