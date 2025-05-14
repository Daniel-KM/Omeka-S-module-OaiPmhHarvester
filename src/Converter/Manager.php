<?php declare(strict_types=1);

namespace OaiPmhHarvester\Converter;

use Omeka\ServiceManager\AbstractPluginManager;

class Manager extends AbstractPluginManager
{
    protected $autoAddInvokableClass = false;

    protected $instanceOf = ConverterInterface::class;
}
