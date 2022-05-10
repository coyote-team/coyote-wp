<?php

namespace Coyote\DB;

class ResourceRecord
{
    private string $sha1;
    private string $uri;
    private int $resourceId;
    private string $originalDescription;
    private string $coyoteDescription;

    public function __construct(
        string $sha1,
        string $uri,
        int $resourceId,
        string $originalDesc,
        string $coyoteDesc
    ) {
        $this->sha1 = $sha1;
        $this->uri = $uri;
        $this->resourceId = $resourceId;
        $this->originalDescription = $originalDesc;
        $this->coyoteDescription = $coyoteDesc;
    }

    /**
     * @return string
     */
    public function getSha1(): string
    {
        return $this->sha1;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return int
     */
    public function getResourceId(): int
    {
        return $this->resourceId;
    }

    /**
     * @return string
     */
    public function getOriginalDescription(): string
    {
        return $this->originalDescription;
    }

    /**
     * @return string
     */
    public function getCoyoteDescription(): string
    {
        return $this->coyoteDescription;
    }


}