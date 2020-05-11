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

    public static function get_resources_for_post($post_id) {
        global $wpdb;

        $prepared_query = $wpdb->prepare(
            "SELECT jt.*, image.original_description FROM " . COYOTE_JOIN_TABLE_NAME . " AS jt LEFT JOIN " . COYOTE_IMAGE_TABLE_NAME . " AS image ON image.coyote_resource_id = jt.coyote_resource_id WHERE jt.wp_post_id = %d",
            $post_id
        );

        return $wpdb->get_results($prepared_query);
    }

    public static function get_post_ids_using_resource_id($resource_id) {
        global $wpdb;

        $prepared_query = $wpdb->prepare(
            "SELECT DISTINCT wp_post_id as id FROM " . COYOTE_JOIN_TABLE_NAME . " WHERE coyote_resource_id = %d",
            $resource_id
        );

        return $wpdb->get_col($prepared_query);
    }

    public static function associate_resource_ids_with_post(array $resource_ids, int $post_id) {
        global $wpdb;

        foreach ($resource_ids as $id) {
            $wpdb->replace(
                COYOTE_JOIN_TABLE_NAME,
                array(
                    'coyote_resource_id' => $id,
                    'wp_post_id' => $post_id
                ), array("%d", "%d")
            );
        }
    }

    public static function update_resource_alt($id, $alt) {
        global $wpdb;

        return $wpdb->update(COYOTE_IMAGE_TABLE_NAME, array('coyote_description' => $alt), array('coyote_resource_id' => $id), array('%s', '%d'));
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
