<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

class DB {
    public static function clear_resource_table() {
        global $wpdb;
        $table = COYOTE_IMAGE_TABLE_NAME;
        return $wpdb->query("delete from {$table}");
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

    public static function get_coyote_alt_by_hash($hash) {
        $row = self::get_image_by_hash($hash);

        if ($row) {
            return $row->coyote_description;
        }

        return null;
    }

    public static function get_coyote_id_by_hash($hash) {
        $row = self::get_image_by_hash($hash);

        if ($row) {
            return $row->coyote_resource_id;
        }

        return null;
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
