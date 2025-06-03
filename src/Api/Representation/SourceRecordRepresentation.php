<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;

class SourceRecordRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'o:item' => $this->item()->getReference(),
            'o:source' => $this->source()->getReference(),
            'o:identifier' => $this->identifier(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:OaiPmhHarvesterSourceRecord';
    }

    public function adminUrl($action = null, $canonical = null)
    {
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/oaipmhharvester/source-record-id',
            [
                'action' => $action,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    public function item(): ItemRepresentation
    {
        $adapter = $this->getAdapter('items');

        return $adapter->getRepresentation($this->resource->getItem());
    }

    public function source(): SourceRepresentation
    {
        $adapter = $this->getAdapter('oaipmhharvester_sources');

        return $adapter->getRepresentation($this->resource->getSource());
    }

    public function identifier(): string
    {
        return $this->resource->getIdentifier();
    }
}
