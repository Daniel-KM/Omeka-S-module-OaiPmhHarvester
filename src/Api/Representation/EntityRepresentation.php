<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\JobRepresentation;

class EntityRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'o:job' => $this->job()->getReference(),
            'o-oai-pmh:entity_id' => $this->entityId(),
            'o-oai-pmh:entity_name' => $this->entityName(),
            'o-oai-pmh:identifier' => $this->identifier(),
            'o:created' => [
                '@value' => $this->getDateTime($this->created()),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ],
        ];
    }

    public function getJsonLdType()
    {
        return 'o:OaiPmhHarvesterEntity';
    }

    public function job(): JobRepresentation
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }

    public function entityId(): int
    {
        return $this->resource->getEntityId();
    }

    public function entityName(): string
    {
        return $this->resource->getEntityName();
    }

    public function identifier(): string
    {
        return $this->resource->getIdentifier();
    }

    public function created(): DateTime
    {
        return $this->resource->getCreated();
    }
}
