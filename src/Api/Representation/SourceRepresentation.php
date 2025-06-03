<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Representation;

use Laminas\Uri\Uri;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\JobRepresentation;

class SourceRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'o:name' => $this->name(),
            'o:configuration' => $this->configuration()->getReference(),
            'o:base_url' => $this->baseUrl(),
            'o:metadata_prefix' => $this->metadataPrefix(),
            'o:sets' => $this->sets(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:OaiPmhHarvesterSource';
    }

    public function adminUrl($action = null, $canonical = null)
    {
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/oaipmhharvester/source-id',
            [
                'controller' => $this->getControllerName(),
                'action' => $action,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    public function name(): string
    {
        return $this->resource->getName();
    }

    public function baseUrl(): string
    {
        return $this->resource->getBaseUrl();
    }

    public function metadataPrefix(): string
    {
        return $this->resource->getMetadataPrefix();
    }

    public function sets(): array
    {
        return $this->resource->getSets();
    }

    public function configuration(): ConfigurationRepresentation
    {
        $adapter = $this->getAdapter('oaipmhharvester_configurations');

        return $adapter->getRepresentation($this->resource->getConfiguration());
    }

    public function latestJob(): ?JobRepresentation
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $response = $api->search('jobs', [
            'oaipmhharvester_source_id' => $this->id(),
            'limit' => 1,
            'sort_by' => 'started',
            'sort_order' => 'desc',
        ]);

        $jobs = $response->getContent();
        $job = $jobs ? reset($jobs) : null;

        return $job;
    }

    public function identifyUrl(): string
    {
        $uri = new Uri($this->baseUrl());
        $uri->setQuery(['verb' => 'Identify']);

        return $uri->toString();
    }
}
