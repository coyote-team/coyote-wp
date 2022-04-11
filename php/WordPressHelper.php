<?php

namespace Coyote;

use \Coyote\ContentHelper;
use \Coyote\ContentHelper\Image;
use \Coyote\WordPressImage;

class WordPressHelper{

    public static function getSrcAndImageData(\WP_Post $post): array
    {   
        $helper = new ContentHelper($post->post_content);
        $images = $helper->getImages();

        $imageMap = [];

        foreach($images as $image){
            $image = new WordPressImage($image);
            $key = $image->getAttachmentId() ?? $image->getSrc();
            $hash = sha1($image->getUrl());
            $image = DB::get_image_by_hash($hash);

            $imageMap[$key] = [
                'coyoteId' => $image->coyote_resource_id,
                'alt' => esc_html($image->coyote_description)
            ];
        }

        return $imageMap;
    }

    private static function createPayload(WordPressImage $image): array
    {
        return [
            ['caption' => $image->getCaption(), 
            'src' => $image->getUrl(),
            'host_uri' => $image->getHostUri(),
            'alt' => $image->getAlt()]];
    }

    public static function setImageAlts(\WP_Post $post,bool $fetchFromApiIfMissing = true): string
    {
        global $coyote_plugin;
        $helper = new ContentHelper($post->post_content);
        $images = $helper->getImages();
        $permalink = get_permalink($post->ID);
        $client = $coyote_plugin->api_client();

        $imageMap = [];

        foreach($images as $image){
            $image = new WordPressImage($image);
            $src = $image->getSrc();
            $url = $image->getUrl();
            $hash = sha1($url);
            $alt = DB::get_coyote_alt_by_hash($hash);
            if(is_null($alt) && $fetchFromApiIfMissing){
                $image->setHostUri($permalink);
                try {
                    $response = $client->batch_create(self::createPayload($image));
                } catch (\Exception $e){
                    continue;
                }
                $resource = $response[$url];
                $alt = $resource['alt'];
                DB::insert_image($hash, $src, $image->getAlt(), $resource['id'], $alt);
            }
            $imageMap[$src] = $alt;
        }

        return $helper->setImageAlts($imageMap);
    }

    
}