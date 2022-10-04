<?php

namespace Coyote\WordPressPlugin;

if (!defined('WP_INC')) {
    exit;
}

use Coyote\PluginConfiguration;
use Coyote\Traits\Logger;
use Coyote\WordPressHelper;
use Coyote\WordPressImage;
use Coyote\ContentHelper\Image;
use WP_Post;

class Filters
{
    use Logger;

    // add setting quicklink to plugin listing entry
    public static function addActionLinks($links): array
    {
        $url = admin_url('options-general.php?page=coyote_fields');
        $text = __('Settings');

        $settingsLinks = [
            "<a href=\"$url\">$text</a>"
        ];

        return array_merge($links, $settingsLinks);
    }

    public static function addTinyMcePlugin()
    {
        add_filter('mce_external_plugins', function ($plugins) {
            $plugins['coyote'] = coyote_asset_url('tinymce_plugin.js');
            return $plugins;
        });
    }

    public static function filterPostContent($postContent)
    {
        global $post;

        if ($post->post_type === 'attachment') {
            self::logDebug("Attachment post already processed, skipping");
            return $postContent;
        }

        return WordPressHelper::setImageAlts($post, PluginConfiguration::isEnabled());
    }

    // used in the media template
    public static function filterAttachmentForJavaScript($response, $attachment, $meta)
    {
        if ($response['type'] !== 'image') {
            return $response;
        }

        $url = WordpressHelper::getAttachmentUrl($attachment->ID);

        if (is_null($url)) {
            return $response;
        }

        $image = new WordPressImage(new Image($url, $response['alt'], ''));
        $image->setCaption($response['caption']);

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

    public static function filterAttachmentImageAttributes(array $attr, WP_Post $attachment, int $size): array
    {
        // get a coyote resource for this attachment. If not found, try to create it unless
        // running in standalone mode.

        $url = WordpressHelper::getAttachmentUrl($attachment->ID);

        if (is_null($url)) {
            return $attr;
        }

        $image = new WordPressImage(new Image($url, '', ''));

        $resource = WordPressHelper::getResourceForWordPressImage($image, PluginConfiguration::isEnabled());

        if (!is_null($resource)) {
            $attr['alt'] = $resource->getCoyoteDescription();
        }

        return $attr;
    }

    public static function addCronSchedule($schedules)
    {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => esc_html__('Every Five Minutes')
        ];

        return $schedules;
    }
}
