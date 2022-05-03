<?php

namespace Coyote\ApiHelper;

use Coyote\ApiModel\MembershipApiModel;
use Coyote\ApiModel\OrganizationApiModel;
use Coyote\ApiModel\ResourceRepresentationApiModel;
use RuntimeException;
use stdClass;

class ResourceRelatedModelInstanceFactory
{
    /** @return ResourceRepresentationApiModel|OrganizationApiModel|MembershipApiModel */
    public function __invoke(stdClass $data)
    {
        switch ($data->type) {
            case OrganizationApiModel::TYPE:
                return new OrganizationApiModel();
            case ResourceRepresentationApiModel::TYPE:
                return new ResourceRepresentationApiModel();
            case MembershipApiModel::TYPE:
                return new MembershipApiModel();
            default:
                throw new RuntimeException("Unable to create resource related model for type $data->type");
        }
    }
}
