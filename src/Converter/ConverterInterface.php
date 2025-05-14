<?php

namespace OaiPmhHarvester\Converter;

use Generator;
use OaiPmhHarvester\OaiPmh\OaiRecord;

interface ConverterInterface
{
    public function getLabel(): string;

    /**
     * Converts an OAI record into one or more items.
     *
     * @return a generator that must yield an array that can be passed directly
     *         as 2nd parameter of \Omeka\Api\Manager::create
     */
    public function convert(OaiRecord $record, array $settings = []): Generator;
}
