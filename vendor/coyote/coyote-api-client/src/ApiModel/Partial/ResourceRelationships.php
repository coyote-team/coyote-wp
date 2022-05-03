<?php

namespace Coyote\ApiModel\Partial;

class ResourceRelationships
{
    public SingleOrganizationRelationship $organization;
    public RepresentationRelationship $representations;
    public ResourceGroupRelationship $resource_groups;
}
