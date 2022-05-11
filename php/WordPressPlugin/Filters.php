<?php

namespace Coyote\WordPressPlugin;

use Coyote\Logger;
use Coyote\PluginConfiguration;
use Coyote\WordPressHelper;
use Coyote\WordPressImage;
use Coyote\ContentHelper\Image;

class Filters
{
    // add setting quicklink to plugin listing entry
    public function addActionLinks($links): array {
        $url = admin_url('options-general.php?page=coyote_fields');
        $text = __('Settings');

        $settings_links = [
            "<a href=\"{$url}\">{$text}</a>"
        ];

        return array_merge($links, $settings_links);
    }

    public function addTinyMcePlugin() {
        add_filter('mce_external_plugins', function($plugins) {
            $plugins['coyote'] = coyote_asset_url('tinymce_plugin.js');
            return $plugins;
        });
    }

    public function filterPostContent($postContent) {
        global $post;

        if ($post->post_type === 'attachment') {
            Logger::log("Attachment post already processed, skipping");
            return $postContent;
        }

        return WordPressHelper::setImageAlts($post, PluginConfiguration::isEnabled());
    }

    // used in the media template
    public function filterAttachmentForJavaScript($response, $attachment, $meta) {
        if ($response['type'] !== 'image') {
            return $response;
        }

        $image = new WordPressImage(new Image(
            coyote_attachment_url($attachment->ID),
            $response['alt'],
            ''
        ));

        $image->setCaption($response['caption'] ?? '');

        $resource = WordPressHelper::getResourceForWordPressImage($image, PluginConfiguration::isEnabled());

        if (!$resource) {
            return $response;
        }

        $response['alt'] = $resource->getCoyoteDescription();
        $response['coyoteManagementUrl'] = implode('/', [
            PluginConfiguration::getApiEndPoint(),
            'organizations',
            PluginConfiguration::getApiOrganizationId(),
            'resources',
            $resource->getResourceId()
        ]);

        return $response;
    }

    public function filterAttachmentImageAttributes(array $attr, \WP_Post $attachment, int $size): array {
        // get a coyote resource for this attachment. If not found, try to create it unless
        // running in standalone mode.

        $image = new WordPressImage(new Image(
            coyote_attachment_url($attachment->ID),
            '',
            ''
        ));

        $resource = WordPressHelper::getResourceForWordPressImage($image, PluginConfiguration::isEnabled());

        if (!is_null($resource)) {
            $attr['alt'] = $resource->getCoyoteDescription();
        }

        return $attr;
    }

    public function addCronSchedule($schedules) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display'  => esc_html__('Every Five Minutes')
        ];

        return $schedules;
    }
}