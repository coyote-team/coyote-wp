CREATE TABLE %image_resource_table_name% (
    source_uri_sha1 VARCHAR(40) NOT NULL,
    source_uri MEDIUMTEXT NOT NULL,
    coyote_resource_id BIGINT(20) UNSIGNED NOT NULL,
    original_description TEXT DEFAULT '' NOT NULL,
    coyote_description TEXT DEFAULT '' NOT NULL,
    PRIMARY KEY (source_uri_sha1),
    INDEX (coyote_resource_id)
) %charset_collate%;


