<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use OaiPmhHarvester\Converter\ConverterInterface;

class ConfigurationRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'o:name' => $this->name(),
            'o:converter_name' => $this->converterName(),
            'o:settings' => $this->settings(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:OaiPmhHarvesterConfiguration';
    }

    public function getControllerName()
    {
        return 'configuration';
    }

    public function adminUrl($action = null, $canonical = null)
    {
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/oaipmhharvester/configuration-id',
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

    public function converterName(): string
    {
        return $this->resource->getConverterName();
    }

    public function settings(): array
    {
        return $this->resource->getSettings();
    }

    public function converter(): ConverterInterface
    {
        $converterManager = $this->getServiceLocator()->get('OaiPmhHarvester\ConverterManager');

        return $converterManager->get($this->converterName());
    }
}
