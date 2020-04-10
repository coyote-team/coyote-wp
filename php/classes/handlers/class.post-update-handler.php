<?php

use WP_Post;
use Coyote\DB;
use Coyote\Helpers\ContentHelper;
use Coyote\Logger;
use Coyote\ImageResource;

namespace Coyote\Handlers;

class PostUpdateHandler {

    private $log;
    private $post;

    public function __construct() {
        $this->log = new Logger();
    }

    public function run(int $post_ID, WP_Post $post, bool $update) {
        if (!$update) {
            $this->log("Not an update, skipping");
            return;
        }

        $this->post = WP_Post::get_instance($post_ID);
        $this->process();
    }

    private function process() {
        $helper = new ContentHelper($this->post->post_content);
        $images = $helper->get_images_with_alt_and_src();
        $resources = ImageResource::from_images($images);

        foreach ($resources as $resource) {
            // entry has resource id, original alt, coyote alt
            // regex-replace the original parsed-out element
            // update the post content
            $helper->set_alt_on_resource($resource);
        }

        $post->post_content = $helper->get_updated_content();
        $post_update = wp_update_post($this->post, true);

        // if the post update succeeded, then...
        
        DB::associate_entries_with_post($entries, $this->post);
    }
}
