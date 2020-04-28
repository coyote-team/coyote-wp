<?php

namespace Coyote;

class DB {

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
