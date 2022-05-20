<?php

namespace Coyote;

use Coyote\ContentHelper\Image;
use Coyote\DB\ResourceRecord;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use WP_Post;

class WordPressHelper
{
    public static function getSrcAndImageData(WP_Post $post): array
    {
        $helper = new ContentHelper($post->post_content);
        $images = $helper->getImages();

        $imageMap = [];

        foreach ($images as $image) {
            $image = new WordPressImage($image);
            $key = $image->getAttachmentId() ?? $image->getSrc();
            $hash = sha1($image->getUrl());
            $resource = DB::getRecordByHash($hash);

            if (is_null($resource)) {
                continue;
            }

            $imageMap[$key] = [
                'coyoteId' => $resource->getResourceId(),
                'alt' => esc_html($resource->getCoyoteDescription())
            ];
        }

        return $imageMap;
    }

    private static function createPayload(WordPressImage $image): CreateResourcePayload
    {
        $payload = new CreateResourcePayload(
            $image->getCaption() ?? $image->getUrl(),
            $image->getUrl(),
            PluginConfiguration::getApiResourceGroupId(),
            $image->getHostUri()
        );

        $alt = $image->getAlt();

        if ($alt !== '') {
            $payload->addRepresentation($alt, PluginConfiguration::METUM);
        }

        return $payload;
    }

    public static function getResourceForWordPressImage(
        WordPressImage $image,
        bool $fetchFromApiIfMissing = true
    ): ?ResourceRecord {
        $record = DB::getRecordByHash(sha1($image->getUrl()));

        if (!is_null($record)) {
            return $record;
        }

        if (!$fetchFromApiIfMissing) {
            return null;
        }

        $resource = WordPressCoyoteApiClient::createResource(self::mapWordPressImageToCreateResourcePayload($image));

        if (is_null($resource)) {
            return null;
        }

        $representation = $resource->getTopRepresentationByMetum(PluginConfiguration::METUM);

        $representation = is_null($representation) ? '' : $representation->getText();

        return DB::insertRecord(
            sha1($resource->getSourceUri()),
            $resource->getSourceUri(),
            $image->getAlt(),
            $resource->getId(),
            $representation,
        );
    }

    public static function mapWordPressImageToCreateResourcePayload(WordPressImage $image): CreateResourcePayload
    {
        return new CreateResourcePayload(
            $image->getCaption() ?? $image->getUrl(),
            $image->getUrl(),
            PluginConfiguration::getApiResourceGroupId(),
            $image->getHostUri()
        );
    }

    public static function setImageAlts(WP_Post $post, bool $fetchFromApiIfMissing = true): string
    {
        $helper = new ContentHelper($post->post_content);
        /** @var Image[] $images */
        $images = $helper->getImages();
        $permalink = get_permalink($post->ID);

        $imageMap = [];
        $missingImages = [];
        $payload = new CreateResourcesPayload();

        foreach ($images as $image) {
            $image = new WordPressImage($image);
            $src = $image->getSrc();
            $url = $image->getUrl();
            $hash = sha1($url);
            $resource = DB::getRecordByHash($hash);

            if (!is_null($resource)) {
                $imageMap[$src] = $resource->getCoyoteDescription();
                continue;
            }

            if ($fetchFromApiIfMissing) {
                $missingImages[$url] = ['alt' => $image->getAlt(),'src' => $src];

                /*  Resources require a hostUri where available  */
                $image->setHostUri($permalink);
                $payload->addResource(self::createPayload($image));
            }
        }

        if ($fetchFromApiIfMissing && count($missingImages) > 0) {
            $imageMap = self::fetchImagesFromApi($imageMap, $missingImages, $payload);
        }

        return $helper->setImageAlts($imageMap);
    }

    private static function fetchImagesFromApi(
        array $imageMap,
        array $missingImages,
        CreateResourcesPayload $payload
    ): array {
        $response = WordPressCoyoteApiClient::createResources($payload);

        if (is_null($response)) {
            return $imageMap;
        }

        foreach ($response as $resourceModel) {
            $uri = $resourceModel->getSourceUri();
            $imageSrc = $missingImages[$uri]['src'];

            $representation = $resourceModel->getTopRepresentationByMetum(PluginConfiguration::METUM);
            if (is_null($representation)) {
                DB::InsertRecord(sha1($imageSrc), $imageSrc, $missingImages[$uri]['alt'], $resourceModel->getId(), '');
                continue;
            }

            DB::InsertRecord(
                sha1($imageSrc),
                $imageSrc,
                $missingImages[$uri]['alt'],
                $resourceModel->getId(),
                $representation->getText()
            );
            $imageMap[$uri] = $representation->getText();
        }

        return $imageMap;
    }

    public static function getMediaTemplateData(): string
    {
        global $post;

        if (empty($post)) {
            return '';
        }

        if (empty($post->post_type)) {
            return '';
        }

        $prefix = implode('/', [
            PluginConfiguration::getApiEndPoint(),
            'organizations',
            PluginConfiguration::getApiOrganizationId()
        ]);

        $mapping = WordPressHelper::getSrcAndImageData($post);
        $json_mapping = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<js
<script>
    window.coyote = {};
    window.coyote.classic_editor = {
        postId: "$post->ID",
        prefix: "$prefix",
        mapping: $json_mapping
    };
</script>
js;
    }
}
