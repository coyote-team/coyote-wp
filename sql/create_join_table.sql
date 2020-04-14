CREATE TABLE wp_pac_coyote_resource_post_jt (
    coyote_resource_id BIGINT(20) UNSIGNED NOT NULL,
    wp_post_id BIGINT(20) UNSIGNED NOT NULL,
    FOREIGN KEY (coyote_resource_id) REFERENCES wp_pac_coyote_image_resource (coyote_resource_id),
    FOREIGN KEY (wp_post_id) REFERENCES wp_pac_posts(ID) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
