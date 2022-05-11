<?php

/**
 * Coyote DB Utility Functions
 * @category class
 * @package Coyote\DB
 * @since 1.0
 */

namespace Coyote;

// Exit if accessed directly.
use Coyote\DB\ResourceRecord;

if (!defined( 'ABSPATH')) {
    exit;
}

class DB {
    public static function clearResourceTable(): int
    {
        global $wpdb;
        $table = COYOTE_IMAGE_TABLE_NAME;
        return $wpdb->query("delete from {$table}");
    }

    public static function update_resource_alt($id, $alt) {
        global $wpdb;
        return $wpdb->update(
            COYOTE_IMAGE_TABLE_NAME,
            array('coyote_description' => $alt),
            array('coyote_resource_id' => $id),
            array('%s', '%d')
        );
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

    public static function getRecordByHash(string $hash): ?ResourceRecord
    {
        $record = self::get_image_by_hash($hash);

        if (is_null($record)) {
            return null;
        }

        return new ResourceRecord(
            $record['source_uri_sha1'],
            $record['source_uri'],
            $record['coyote_resource_id'],
            $record['original_description'],
            $record['coyote_description']
        );
    }

    public static function insertRecord(
        string $hash,
        string $src,
        string $alt,
        int $resourceId,
        string $resourceAlt): ResourceRecord
    {
        $record = self::insert_image($hash, $src, $alt, $resourceId, $resourceAlt);

        return new ResourceRecord(
            $record['source_uri_sha1'],
            $record['source_uri'],
            $record['coyote_resource_id'],
            $record['original_description'],
            $record['coyote_description']
        );

    }
}
