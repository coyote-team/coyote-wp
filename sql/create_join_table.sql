CREATE TABLE %resource_post_join_table_name% (
    coyote_resource_id BIGINT(20) UNSIGNED NOT NULL,
    wp_post_id BIGINT(20) UNSIGNED NOT NULL,
    FOREIGN KEY (coyote_resource_id) REFERENCES %image_resource_table_name%(coyote_resource_id),
    FOREIGN KEY (wp_post_id) REFERENCES %wp_post_table_name%(ID) ON DELETE CASCADE,
    PRIMARY KEY (coyote_resource_id, wp_post_id)
) %charset_collate%;
