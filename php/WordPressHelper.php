<?php

namespace Coyote;

use Coyote\ContentHelper\Image;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;

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

    private static function createPayload(WordPressImage $image): CreateResourcePayload
    {
        $payload = new CreateResourcePayload($image->getCaption(), $image->getUrl(), PluginConfiguration::getApiResourceGroupId(),$image->getHostUri());
        $payload->addRepresentation($image->getAlt(),PluginConfiguration::METUM);
        return $payload;
    }

    public static function setImageAlts(\WP_Post $post,bool $fetchFromApiIfMissing = true): string
    {
        $helper = new ContentHelper($post->post_content);
        /** @var Image[] $images */
        $images = $helper->getImages();
        $permalink = get_permalink($post->ID);

        $imageMap = [];
        $missingImages = [];
        $payload = new CreateResourcesPayload();

        foreach($images as $image){
            $image = new WordPressImage($image);
            $src = $image->getSrc();
            $url = $image->getUrl();
            $hash = sha1($url);
            $alt = DB::get_coyote_alt_by_hash($hash);

            if(!is_null($alt)) {
                $imageMap[$src] = $alt;
                continue;
            }

            if($fetchFromApiIfMissing){
                $missingImages[$url] = ['alt' => $image->getAlt(),'src' => $src];

                /*  Resources require a hostUri where available  */
                $image->setHostUri($permalink);
                $payload->addResource(self::createPayload($image));
            }
        }

        if ($fetchFromApiIfMissing) {
            $imageMap = self::fetchImagesFromApi($imageMap,$missingImages, $payload);
        }

        return $helper->setImageAlts($imageMap);
    }

    private static function fetchImagesFromApi(array $imageMap,array $missingImages,CreateResourcesPayload $payload): array{
        $response = WordPressCoyoteApiClient::createResources($payload);

        if (is_null($response)) {
            return $imageMap;
        }

        foreach($response as $resourceModel){
            $uri = $resourceModel->getSourceUri();
            $imageSrc = $missingImages[$uri]['src'];

            $representation = $resourceModel->getTopRepresentationByMetum(PluginConfiguration::METUM);
            if (is_null($representation)){
                DB::insert_image(sha1($imageSrc), $imageSrc, $missingImages[$uri]['alt'], $resourceModel->getId(), '');
                continue;
            }
            DB::insert_image(sha1($imageSrc), $imageSrc, $missingImages[$uri]['alt'], $resourceModel->getId(), $representation->getText());
            $imageMap[$uri] = $representation->getText();
        }

        return $imageMap;
    }

    
}