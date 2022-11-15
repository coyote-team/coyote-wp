<?php

namespace Coyote\WordPressPlugin;

use Coyote\PluginConfiguration;
use Coyote\Traits\Logger;
use Coyote\WordPressHelper;
use Coyote\WordPressImage;
use Coyote\ContentHelper\Image;
use WP_Post;

if (!defined('WPINC')) {
    exit;
}

class Filters
{
    use Logger;

    // add setting quicklink to plugin listing entry
    public static function addActionLinks($links): array
    {
        $url = admin_url('options-general.php?page=coyote');
        $text = __('Settings');

        $settingsLinks = [
            "<a href=\"$url\">$text</a>"
        ];

        return array_merge($links, $settingsLinks);
    }

    public static function addTinyMcePlugin()
    {
        add_filter('mce_external_plugins', function ($plugins) {
            $plugins['coyote'] = coyoteAssetURL('tinymce_plugin.js');
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

        $shouldFilter = (is_single() || is_page()) && in_the_loop() && is_main_query();

        if ($shouldFilter) {
            return $postContent;
        }

        return WordPressHelper::setImageAlts($post->ID, $postContent, PluginConfiguration::isEnabled());
    }

    // used in the media template
    public static function filterAttachmentForJavaScript($response, $attachment, $meta)
    {
        if ($response['type'] !== 'image') {
            return $response;
        }

        $url = WordPressHelper::getAttachmentUrl($attachment->ID);

        if (is_null($url)) {
            return $response;
        }

        $image = new WordPressImage(new Image(
            $url,
            $response['alt'],
            ''
        ));

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

    /**
     * @param array $attr
     * @param WP_Post $attachment
     * @param int[]|string $size
     * @return array
     */
    public static function filterAttachmentImageAttributes(array $attr, WP_Post $attachment, $size): array
    {
        // get a coyote resource for this attachment. If not found, try to create it unless
        // running in standalone mode.

        $url = WordPressHelper::getAttachmentURL($attachment->ID);

        if (is_null($url)) {
            return $attr;
        }

        $image = new WordPressImage(new Image(
            $url,
            '',
            ''
        ));

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
