<?php

namespace Coyote\Handlers;

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.image-resource.php');
require_once coyote_plugin_file('classes/helpers/class.content-helper.php');

use WP_Post;

//use Coyote\DB;
use Coyote\Helpers\ContentHelper;
use Coyote\Logger;
use Coyote\ImageResource;

class PostUpdateHandler {

    private $post;

    public function __construct() {}

    public function run(int $post_ID, WP_Post $post, bool $update) {
        if (!$update) {
            Logger::log("Not an update, skipping");
            return;
        }

        $this->post = WP_Post::get_instance($post_ID);
        $this->process();
    }

    private function process() {
        Logger::log("Processing update on post " . $this->post->ID);

        $helper = new ContentHelper($this->post->post_content);
        $images = $helper->get_images_with_alt_and_src();
        $resources = array();

        foreach ($images as $image) {
            $resource = new ImageResource($image);
            if ($resource->coyote_alt !== null) {
                $helper->replace_img_alt($image["element"], $resource->coyote_alt);
                array_push($resources, $resource);
            }
        }

        if (!$helper->content_is_modified) {
            Logger::log("No modifications made, done.");
        }

        $post->post_content = $helper->get_content();
        $post_update = wp_update_post($this->post, true);

        //if the post update succeeded, then associate the resources with the post
        DB::associate_entries_with_post($resources, $this->post);
    }
}
