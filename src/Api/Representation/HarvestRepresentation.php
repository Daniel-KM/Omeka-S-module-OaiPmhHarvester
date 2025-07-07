<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\JobRepresentation;

class HarvestRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \OaiPmhHarvester\Entity\Harvest
     */
    protected $resource;

    public function getJsonLd()
    {
        $undoJob = $this->undoJob();
        $itemSet = $this->itemSet();
        $from = $this->from();
        $until = $this->until();

        return [
            'o:job' => $this->job()->getReference()->jsonSerialize(),
            'o:undo_job' => $undoJob ? $undoJob->getReference()->jsonSerialize() : null,
            'o-oai-pmh:message' => $this->message(),
            'o-oai-pmh:endpoint' => $this->endpoint(),
            'o-oai-pmh:entity_name' => $this->entityName(),
            'o:item_set' => $itemSet ? $itemSet->getReference()->jsonSerialize() : null,,
            'o-oai-pmh:metadata_prefix' => $this->metadataPrefix(),
            'o-oai-pmh:mode_harvest' => $this->modeHarvest(),
            'o-oai-pmh:mode_delete' => $this->modeDelete(),
            'o-oai-pmh:from' => $from
                ? [
                    '@value' => $this->getDateTime($from)->jsonSerialize(),
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                ] : null,
            'o-oai-pmh:until' => $until
                ? [
                    '@value' => $this->getDateTime($until)->jsonSerialize(),
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                ] : null,
            'o-oai-pmh:set_spec' => $this->getSetSpec(),
            'o-oai-pmh:set_name' => $this->getSetName(),
            'o-oai-pmh:set_description' => $this->getSetDescription(),
            'o-oai-pmh:has_err' => $this->hasErr(),
            'o-oai-pmh:stats' => $this->stats(),
            'o-oai-pmh:resumption_token' => $this->resumptionToken(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:OaiPmhHarvesterHarvest';
    }

    public function job(): JobRepresentation
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }

    public function undoJob(): ?JobRepresentation
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getUndoJob());
    }

    public function message(): ?string
    {
        return $this->resource->getMessage();
    }

    public function endpoint(): string
    {
        return $this->resource->getEndpoint();
    }

    public function entityName(): string
    {
        return $this->resource->getEntityName();
    }

    public function itemSet(): ?ItemSetRepresentation
    {
        return $this->getAdapter('item_sets')
            ->getRepresentation($this->resource->getItemSet());
    }

    public function metadataPrefix(): string
    {
        return $this->resource->getMetadataPrefix();
    }

    public function modeHarvest(): string
    {
        return $this->resource->getModeHarvest();
    }

    public function modeDelete(): string
    {
        return $this->resource->getModeDelete();
    }

    public function from(): ?DateTime
    {
        return $this->resource->getFrom();
    }

    public function until(): ?DateTime
    {
        return $this->resource->getUntil();
    }

    /**
     * Note: Use get to avoid issue with set.
     */
    public function getSetSpec(): ?string
    {
        return $this->resource->getSetSpec();
    }

    public function getSetName(): ?string
    {
        return $this->resource->getSetName();
    }

    public function getSetDescription(): ?string
    {
        return $this->resource->getSetDescription();
    }

    public function hasErr(): bool
    {
        return $this->resource->getHasErr();
    }

    public function stats(): array
    {
        return $this->resource->getStats() ?? [];
    }

    public function resumptionToken(): ?string
    {
        return $this->resource->getResumptionToken();
    }

    /**
     * Get the list of entities harvested (id / resource name).
     *
     * For now, the resource name is always "items".
     *
     * @return array Associative array with entity id as key and entity name as
     * value.
     */
    public function harvestedEntities(): array
    {
        /** @var \Omeka\Api\Manager $api */
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $entityIds = $api
            ->search(
                'oaipmhharvester_entities',
                [
                    'harvest_id' => $this->id(),
                ],
                ['returnScalar' => 'entity_id']
            )
            ->getContent();
        $entityNames = $api
            ->search(
                'oaipmhharvester_entities',
                [
                    'harvest_id' => $this->id(),
                ],
                ['returnScalar' => 'entity_name']
            )
            ->getContent();
        return array_combine($entityIds, $entityNames);
    }

    /**
     * Get the count of the currently imported resources.
     */
    public function totalImported(): int
    {
        /** @var \Omeka\Api\Manager $api */
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        return $api
            ->search('oaipmhharvester_entities', [
                'harvest_id' => $this->id(),
                'limit' => 0,
            ])
            ->getTotalResults();
    }
}
