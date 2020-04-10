<?php

namespace Coyote;

class DB {

    public function associate_entries_with_post(array $entries, WP_Post $post) {
        global $wpdb;

        foreach ($entry as $entry) {
            $wpdb->replace(COYOTE_JOIN_TABLE_NAME, [$entry["coyote_id"], $post->ID], ["%d", "%d"]);
        }
    }

    public function batch_fetch_or_create_entry($images) {
        $entries = [];

        foreach ($images as $image) {
            $hash = sha1($image["src"]);

            $entry = self::_fetch_entry($hash);

            if ($entry === null) {
                $entry = $self::_create_entry($hash, $image);
            }

            array_push($entries, $entry);
        }

        return $entries;
    }

    private function _fetch_entry($hash) {
        global $wpdb;
        
        $prepared_query = $wpdb->prepare(
            "SELECT * FROM %s WHERE source_uri_sha1 = %s",
            COYOTE_IMAGE_TABLE_NAME, $hash
        );

        $wpdb->get_row($prepared_query);
    }

    private function _create_entry($hash, $image) {
        global $wpdb;

        $record = [
            $hash,
            $image["src"],
            null,
            $image["alt"],
            ""
        ];

        $data_types = ["%s", "%s", "%d", "%s", "%d"];

        $wpdb->insert(COYOTE_IMAGE_TABLE_NAME, $record, $data_types);

        return self::_fetch_entry($hash);
    }
}
