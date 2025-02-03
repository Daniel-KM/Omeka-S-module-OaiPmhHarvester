<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\JobRepresentation;

class HarvestRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        $undoJob = $this->undoJob();
        if ($undoJob) {
            $undoJob = $undoJob->getReference();
        }

        $itemSet = $this->itemSet();
        if ($itemSet) {
            $itemSet = $itemSet->getReference();
        }

        return [
            'o:job' => $this->job()->getReference(),
            'o:undo_job' => $undoJob,
            'o-oai-pmh:message' => $this->message(),
            'o-oai-pmh:endpoint' => $this->endpoint(),
            'o-oai-pmh:entity_name' => $this->entityName(),
            'o:item_set' => $itemSet(),
            'o-oai-pmh:metadata_prefix' => $this->metadataPrefix(),
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
     * Get the count of the currently imported resources.
     */
    public function totalImported(): int
    {
        $response = $this->getServiceLocator()->get('Omeka\ApiManager')
            ->search('oaipmhharvester_entities', [
                'job_id' => $this->job()->id(),
                'limit' => 0,
            ]);
        return $response->getTotalResults();
    }
}
