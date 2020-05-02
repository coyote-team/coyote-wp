<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

class DB {

    public static function get_edited_post_ids() {
        global $wpdb;
        $query = "SELECT DISTINCT wp_post_id as id FROM " . COYOTE_JOIN_TABLE_NAME;
        return $wpdb->get_col($query);
    }

    public static function get_resources_for_post($postId) {
        global $wpdb;

        $prepared_query = $wpdb->prepare(
            "SELECT jt.*, image.original_description FROM " . COYOTE_JOIN_TABLE_NAME . " AS jt LEFT JOIN " . COYOTE_IMAGE_TABLE_NAME . " AS image ON image.coyote_resource_id = jt.coyote_resource_id WHERE jt.wp_post_id = %d",
            $postId
        );

        return $wpdb->get_results($prepared_query);
    }

    public static function associate_resources_with_post(array $resources, int $postID) {
        global $wpdb;

        foreach ($resources as $resource) {
            $wpdb->replace(
                COYOTE_JOIN_TABLE_NAME,
                array(
                    'coyote_resource_id' => $resource->coyote_resource_id,
                    'wp_post_id' => $postID
                ), array("%d", "%d")
            );
        }
    }

    public static function insert_image($hash, $src, $alt, $resourceId, $resourceAlt) {
        global $wpdb;

        $record = array(
            'source_uri_sha1' => $hash,
            'source_uri' => $src,
            'coyote_resource_id' => (int) $resourceId,
            'original_description' => $alt,
            'coyote_description' => ($resourceAlt === null ? "" : $resourceAlt)
        );

        $data_types = array("%s", "%s", "%d", "%s", "%s");

        $wpdb->insert(COYOTE_IMAGE_TABLE_NAME, $record, $data_types);

        return self::get_image_by_hash($hash);
    }

    public static function get_image_by_hash($hash) {
        global $wpdb;
        
        $prepared_query = $wpdb->prepare(
            "SELECT * FROM " . COYOTE_IMAGE_TABLE_NAME . " WHERE source_uri_sha1 = %s",
            $hash
        );

        return $wpdb->get_row($prepared_query);
    }
}
