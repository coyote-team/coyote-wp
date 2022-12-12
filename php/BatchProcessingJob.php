<?php

namespace Coyote;

use Coyote\ContentHelper\Image;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\Traits\Logger;

class BatchProcessingJob
{
    use Logger;

    private string $id;
    private string $resourceGroupId;
    private int $offset;
    private int $size;
    private int $total;
    private bool $processUnpublished;

    private array $validStatuses;
    private array $validTypes;

    private const MAX_BATCH_SIZE = 200;
    private const MIN_BATCH_SIZE = 10;
    private const DECREASE_BATCH_SIZE_STEP = 2;

    public function __construct(string $id, array $types, int $size, string $resourceGroupId, bool $processUnpublished) {
        $this->id = $id;
        $this->resourceGroupId = $resourceGroupId;

        if ($size > self::MAX_BATCH_SIZE) {
            $size = self::MAX_BATCH_SIZE;
        } elseif ($size < self::MIN_BATCH_SIZE) {
            $size = self::MIN_BATCH_SIZE;
        }

        $this->size = $size;
        $this->offset = 0;
        $this->validStatuses = ['inherit', 'publish'];
        $this->validTypes = $types;
        $this->processUnpublished = $processUnpublished;

        if ($processUnpublished) {
            $this->addValidStatuses(['pending', 'draft', 'private']);
        }

        $this->total = $this->getTotalPostCount();
    }

    private function getTotalPostCount(): int
    {
        return array_reduce($this->validTypes, function ($carry, $type) {
            $counts = wp_count_posts($type);

            foreach ($this->validStatuses as $status) {
                if (property_exists($counts, $status)) {
                    $carry += $counts->$status;
                }
            }

            return $carry;
        }, 0);
    }

    private function addValidStatuses(array $statuses): void
    {
        $this->validStatuses = array_unique(array_merge($this->validStatuses, $statuses));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    private function increaseOffset(int $n): void
    {
        $this->offset += $n;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getProgress(): int
    {
        return intval(round(($this->offset / $this->total) * 100));
    }

    public function processNextBatch(): array
    {
        $posts = get_posts(array(
            'order' => 'ASC',
            'order_by' => 'ID',
            'offset' => $this->getOffset(),
            'numberposts' => $this->getSize(),
            'post_type' => $this->validTypes,
            'post_status' => $this->validStatuses,
            'post_parent' => null,
        ));

        $resources = $this->createResources($posts);

        $response['size'] = $this->getSize();
        $response['posts'] = count($posts);
        $response['total'] = $this->total;
        $response['offset'] = $this->getOffset();
        $this->increaseOffset(count($posts));

        $response['resources'] = count($resources);

        $progress = $this->getProgress();

        $response['progress'] = $progress;
        $response['status'] = $this->isFinished() ? 'finished' : 'running';

        return $response;
    }

    private function addAttachmentResourceToPayload(\WP_Post $post, CreateResourcesPayload $payload): CreateResourcesPayload
    {
        // attachment with mime type image, get alt and caption differently
        $alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);

        if ($post->post_status === 'inherit' && $post->post_parent) {
            // child of a page
            $parentPost = get_post($post->post_parent);

            // only process images in published posts
            if ($parentPost && $parentPost->post_status !== 'publish' && !$this->processUnpublished) {
                return $payload;
            }

            $host_uri = get_permalink($parentPost);
        } else {
            $host_uri = get_permalink($post);
        }

        $attachmentUrl = WordpressHelper::getAttachmentURL($post->ID);

        if (is_null($attachmentUrl)) {
            return $payload;
        }

        $image = new WordPressImage(
            new Image($attachmentUrl, $alt, '')
        );
        $image->setHostUri($host_uri);
        $image->setCaption($post->post_excerpt);

        $payload->addResource(new CreateResourcePayload(
            $image->getCaption() ?? $image->getUrl(),
            $image->getUrl(),
            $this->resourceGroupId,
            $host_uri
        ));

        return $payload;
    }

    private function isPostImageAttachment(\WP_Post $post): bool
    {
        return $post->post_type === 'attachment' && strpos($post->post_mime_type, 'image/') === 0;
    }

    public function isFinished(): bool
    {
        return $this->offset === $this->total;
    }

    /**
     * @param \WP_Post[] $posts
     * @return array|Model\ResourceModel[]
     * @throws \Exception
     */
    private function createResources(array $posts): array
    {
        $payload = new CreateResourcesPayload();

        foreach ($posts as $post) {
            if ($this->isPostImageAttachment($post)) {
                $payload = self::addAttachmentResourceToPayload($post, $payload);
                continue;
            }

            $helper = new ContentHelper($post->post_content);
            $images = $helper->getImages();
            $hostURI = get_permalink($post);

            foreach ($images as $contentImage) {
                $image = new WordPressImage($contentImage);
                $image->setHostUri($hostURI);
                $payload->addResource(new CreateResourcePayload(
                    $image->getCaption() ?? $image->getUrl(),
                    $image->getUrl(),
                    $this->resourceGroupId,
                    $hostURI
                ));
            }
        }

        if (count($payload->resources) === 0) {
            return [];
        }

        $results = WordPressCoyoteApiClient::createResources($payload);

        if (is_null($results)) {
            self::logWarning('Null response while creating resources', ['payload', $payload]);
            return [];
        }

        return $results;
    }

    public function decreaseBatchSize(): void
    {
        if ($this->size <= self::MIN_BATCH_SIZE) {
            return;
        }

        if ($this->size - self::DECREASE_BATCH_SIZE_STEP < self::MIN_BATCH_SIZE) {
            $this->size = self::MIN_BATCH_SIZE;
            return;
        }

        $this->size -= self::DECREASE_BATCH_SIZE_STEP;
    }
}