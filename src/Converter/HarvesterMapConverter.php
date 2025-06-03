<?php

namespace OaiPmhHarvester\Converter;

use Generator;
use OaiPmhHarvester\OaiPmh\OaiRecord;
use OaiPmhHarvester\OaiPmh\HarvesterMap\HarvesterMapInterface;
use Omeka\Settings\Settings;

class HarvesterMapConverter implements ConverterInterface
{
    protected string $harvesterMapName;
    protected HarvesterMapInterface $harvesterMap;

    public function __construct(string $harvesterMapName, HarvesterMapInterface $harvesterMap)
    {
        $this->harvesterMapName = $harvesterMapName;
        $this->harvesterMap = $harvesterMap;
    }

    public function getLabel(): string
    {
        return sprintf('Default %s converter (not configurable)', $this->harvesterMapName); // @translate
    }

    public function convert(OaiRecord $record, array $settings = []): Generator
    {
        $simpleXmlElement = simplexml_import_dom($record->getDOMElement());
        $data = $this->harvesterMap->mapRecord($simpleXmlElement);

        yield from $data;
    }
}
