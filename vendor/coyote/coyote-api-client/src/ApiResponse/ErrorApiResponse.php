<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\Partial\ApiMetaData;
use Coyote\ApiModel\Partial\ErrorMessage;

class ErrorApiResponse
{
    /** @var ErrorMessage[] */
    public array $errors;
    public ApiMetaData $jsonapi;
}
