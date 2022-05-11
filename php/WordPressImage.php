<?php

namespace Coyote;

use Coyote\ContentHelper\Image;

class WordPressImage
{
    private string $caption;
    private ?string $wordPressAttachmentUrl;
    private ?int $attachmentId;
    private ?string $hostUri;
    private Image $image;

    private const AFTER_REGEX = '/([^>]*?)(?=\[\/caption])/smi';
    private const IMG_ATTACHMENT_REGEX = '/wp-image-(\d+)/smi';

    public function __construct(Image $image)
    {
        $matches = array();
        $this->image = $image;
        $this->caption = '';
        $this->wordPressAttachmentUrl = null;
        $this->attachmentId = null;

        if (preg_match(self::AFTER_REGEX, $image->content_after, $matches) === 1) {
            $this->caption = $matches[0];
        }

        if (preg_match(self::IMG_ATTACHMENT_REGEX, $image->class, $matches) === 1) {
            $this->attachmentId = intval($matches[1]);
            $attachment_url = wp_get_attachment_url($this->attachmentId);

            if ($attachment_url !== false) {
                $this->wordPressAttachmentUrl = $attachment_url;
            }
        }
    }

    public function setHostUri(string $value): void
    {
        $this->hostUri = $value;
    }

    public function getHostUri(): string
    {
        return $this->hostUri;
    }

    public function getSrc(): string
    {
        return $this->image->src;
    }

    public function setCaption(string $value): void
    {
        $this->caption = $value;
    }

    public function getCaption(): string
    {
        return $this->caption;
    }

    public function getAlt(): string
    {
        return $this->image->alt ?? '';
    }

    public function getClass(): string
    {
        return $this->image->class;
    }

    public function getWordPressAttachmentUrl(): ?string
    {
        return $this->wordPressAttachmentUrl;
    }

    public function getAttachmentId(): ?int
    {
        return $this->attachmentId;
    }

    public function getUrl(): string
    {
        $url = $this->getWordPressAttachmentUrl();
        
        if(is_null($url)){
            return $this->getSrc();
        }
        
        $parts = wp_parse_url($url);

        if ($parts === false) {
            return $this->getSrc();
        }

        return '//' . $parts['host'] . esc_url($parts['path']);
    }
}