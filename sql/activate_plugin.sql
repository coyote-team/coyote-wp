CREATE TABLE %image_resource_table_name% (
    source_uri_sha1 VARCHAR(40) NOT NULL,
    source_uri MEDIUMTEXT NOT NULL,
    coyote_resource_id BIGINT(20) UNSIGNED DEFAULT NULL,
    original_description TEXT DEFAULT '' NOT NULL,
    coyote_description TEXT DEFAULT '' NOT NULL,
    PRIMARY KEY (source_uri_sha1)
) %charset_collate%;

CREATE TABLE %resource_post_join_table_name% (
    coyote_resource_id BIGINT(20) UNSIGNED NOT NULL,
    wp_post_id BIGINT(20) UNSIGNED NOT NULL,
    FOREIGN KEY (coyote_resource_id) REFERENCES %image_resource_table_name% (coyote_resource_id),
    FOREIGN KEY (wp_post_id) REFERENCES %wp_post_table_name%(ID) ON DELETE CASCADE
) %charset_collate%;
