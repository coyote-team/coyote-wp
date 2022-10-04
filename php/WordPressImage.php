<?php

namespace Coyote;

if (!defined('WP_INC')) {
    exit;
}

use Coyote\ContentHelper\Image;

class WordPressImage
{
    private ?string $caption;
    private ?string $wordPressAttachmentUrl;
    private ?int $attachmentId;
    private ?string $hostUri;
    private Image $image;

    private const AFTER_REGEX = '/([^>]*?)(?=\[\/caption])/smi';
    private const IMG_ATTACHMENT_REGEX = '/wp-image-(\d+)/smi';

    public function __construct(Image $image)
    {
        $this->image = $image;
        $this->caption = null;
        $this->attachmentId = null;
        $this->hostUri = null;
        $this->caption = $this->findCaption();
        $this->wordPressAttachmentUrl = $this->findAttachmentUrl();
    }

    public function setHostUri(string $value): void
    {
        $this->hostUri = $value;
    }

    public function getHostUri(): ?string
    {
        return $this->hostUri;
    }

    public function getSrc(): string
    {
        return $this->image->getSrc();
    }

    public function setCaption(string $value): void
    {
        if (strlen($value) === 0) {
            return;
        }

        $this->caption = $value;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function getAlt(): string
    {
        return $this->image->getAlt() ?? '';
    }

    public function getClass(): string
    {
        return $this->image->getClass();
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

        if (is_null($url)) {
            return $this->getSrc();
        }

        $parts = wp_parse_url($url);

        if ($parts === false) {
            return $this->getSrc();
        }

        return '//' . $parts['host'] . esc_url($parts['path']);
    }

    private function findCaption(): ?string
    {
        $caption = $this->image->getFigureCaption();

        if (!is_null($caption)) {
            return $caption;
        }

        $matches = [];

        if (preg_match(self::AFTER_REGEX, $this->image->getContentAfter(), $matches) === 1) {
            if (strlen($matches[0]) > 0) {
                return $matches[0];
            }
        }

        return null;
    }

    private function findAttachmentUrl(): ?string
    {
        $matches = [];

        if (preg_match(self::IMG_ATTACHMENT_REGEX, $this->image->getClass(), $matches) !== 1) {
            return null;
        }

        $this->attachmentId = intval($matches[1]);
        $attachmentUrl = wp_get_attachment_url($this->attachmentId);

        if ($attachmentUrl === false) {
            return null;
        }

        return $attachmentUrl;
    }
}
