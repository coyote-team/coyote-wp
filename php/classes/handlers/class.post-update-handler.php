<?php

namespace Coyote\Handlers;

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.db.php');
require_once coyote_plugin_file('classes/class.image-resource.php');
require_once coyote_plugin_file('classes/helpers/class.content-helper.php');

use WP_Post;

use Coyote\DB;
use Coyote\Helpers\ContentHelper;
use Coyote\Logger;
use Coyote\ImageResource;

class PostUpdateHandler {

    private $postID;

    public function __construct() {}

    public function run(array $data, array $postArr) {
        if($postArr['post_type'] == 'revision') {
            return $data;
        }

        $this->postID = $postArr['ID'];
        $content = $this->process(wp_unslash($data['post_content']));
        $data['post_content'] = wp_slash($content);

        return $data;
    }

    private function process(string $postContent) {
        Logger::log("Processing update on post " . $this->postID);

        $helper = new ContentHelper($postContent);
        $images = $helper->get_images_with_attributes();
        $resources = array();

        foreach ($images as $image) {
            if ($image["data-coyote-id"] !== null) {
                // already linked
                continue;
            }

            $resource = new ImageResource($image);

            $alt = $resource->coyote_description !== null ? $resource->coyote_description : "";
            $element = $helper->set_coyote_id_and_alt($image["element"], $resource->coyote_resource_id, $alt);

            array_push($resources, $resource);
        }

        if (!$helper->content_is_modified) {
            Logger::log("No modifications made, done.");
            return $helper->get_content();
        }

        //if the post update succeeded, then associate the resources with the post
        DB::associate_resources_with_post($resources, $this->postID);

        return $helper->get_content();
    }
}
