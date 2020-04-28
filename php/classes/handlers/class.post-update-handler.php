<?php

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.db.php');
require_once coyote_plugin_file('classes/class.image-resource.php');
require_once coyote_plugin_file('classes/helpers/class.post-process-helper.php');

use WP_Post;

use Coyote\DB;
use Coyote\Helpers\PostProcessHelper;
use Coyote\Logger;
use Coyote\ImageResource;

class PostUpdateHandler {

    public static function run(array $data, array $postArr) {
        if($postArr['post_type'] == 'revision') {
            return $data;
        }

        $postID = $postArr['ID'];
        $processed = PostProcessHelper::processPostContent(wp_unslash($data['post_content']), $postID);
        $data['post_content'] = wp_slash($processed->content);

        return $data;
    }

}
