<?php

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.db.php');
require_once coyote_plugin_file('classes/helpers/class.content-helper.php');

use Coyote\Logger;
use Coyote\DB;
use Coyote\Helpers\ContentHelper;

class ResourceUpdateHandler {
    public static function run($id, $alt) {
        Logger::log("Updating: [id] {$id}, [alt] {$alt}");

        $update = DB::update_resource_alt($id, $alt);

        if ($update === false) {
            // db error
            Logger::log("Resource alt update error");
            return false;
        }

        if ($update === 0) {
            Logger::log("No resources to update?");
            // no updates? That's ok, but leave posts alone
            return true;
        }

        try {
            return self::update_posts_with_resource($id, $alt);
        } catch (Exception $error) {
            Logger::Log("Error updating post: " . $error->get_error_message());
            return false;
        }
    }

    public static function update_posts_with_resource($id, $alt) {
        $post_ids = DB::get_post_ids_using_resource_id($id);

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);

            if (!$post) {
                throw new Exception("Unable to load post {$post_id}");
            }
            
            // get post
            $helper = new ContentHelper($post->post_content);
            $images = $helper->get_images_with_attributes();

            foreach ($images as $image) {
                if ($image['data-coyote-id'] !== $id) {
                    continue;
                }

                $helper->replace_img_alt($image['element'], $alt);
                Logger::log("Updated {$image['src']} alt with \"{$alt}\" in post {$post->ID}");
            }

            $post->post_content = $helper->get_content();
            $result = wp_update_post($post, true);

            if (is_wp_error($result)) {
                throw $result;
            }

            wp_save_post_revision($post_id);
        }

        return true;
    }
}


