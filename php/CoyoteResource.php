<?php



class CoyoteResource{

    private string $sourceUriSha1;
    private string $sourceUri;
    private int $coyoteResourceId;
    private string $originalDescription;
    private string $coyoteDescription;
    private array $hostUris;

    public function getSourceUriSha1(){
        return $this->sourceUriSha1;
    }

    public function setSourceUriSha1(string $value){
        $this->sourceUriSha1 = $value;
    }

    public function getSourceUri(){
        return $this->sourceUri;
    }

    public function setSourceUri(string $value){
        $this->sourceUri = $value;
    }

    public function getCoyoteResourceId(){
        return $this->coyoteResourceId;
    }

    public function setCoyoteResourceId(int $value){
        $this->coyoteResourceId = $value;
    }

    public function getOriginalDescription(){
        return $this->originalDescription;
    }

    public function setOriginalDescription(string $value){
        $this->originalDescription = $value;
    }

    public function getCoyoteDescription(){
        return $this->coyoteDescription;
    }

    public function setCoyoteDescription(string $value){
        $this->coyoteDescription = $value;
    }

    public function getHostUris(){
        return $this->hostUris;
    }

    public function setHostUris(array $values){
        $this->hostUris = $values;
    }
}