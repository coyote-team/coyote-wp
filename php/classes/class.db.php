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

    public static function updateResourceAlt($id, $alt): bool
    {
        global $wpdb;
        return $wpdb->update(
            COYOTE_IMAGE_TABLE_NAME,
            ['coyote_description' => $alt],
            ['coyote_resource_id' => $id],
            ['%s', '%d']
        );
    }

    public static function insertRecord(
        string $hash,
        string $src,
        string $alt,
        int $resourceId,
        string $resourceAlt
    ): ?ResourceRecord {
        global $wpdb;

        $record = [
            'source_uri_sha1' => $hash,
            'source_uri' => $src,
            'coyote_resource_id' => $resourceId,
            'original_description' => $alt,
            'coyote_description' => $resourceAlt
        ];

        $data_types = ["%s", "%s", "%d", "%s", "%s"];
        $wpdb->insert(COYOTE_IMAGE_TABLE_NAME, $record, $data_types);

        return self::getRecordByHash($hash);
    }

    public static function getRecordByHash(string $hash): ?ResourceRecord
    {
        global $wpdb;

        $prepared_query = $wpdb->prepare(
            "SELECT * FROM " . COYOTE_IMAGE_TABLE_NAME . " WHERE source_uri_sha1 = %s",
            $hash
        );

        $row = $wpdb->get_row($prepared_query);

        if (is_null($row)) {
            return null;
        }

        return self::mapTableRowToResourceRecord($row);
    }

    private static function mapTableRowToResourceRecord(\stdClass $record): ResourceRecord
    {
        return new ResourceRecord(
            $record['source_uri_sha1'],
            $record['source_uri'],
            $record['coyote_resource_id'],
            $record['original_description'],
            $record['coyote_description']
        );
    }
}
