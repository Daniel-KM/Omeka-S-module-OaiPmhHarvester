<?php declare(strict_types=1);

namespace OaiPmhHarvester\OaiPmh\HarvesterMap;

use Omeka\ServiceManager\AbstractPluginManager;

class Manager extends AbstractPluginManager
{
    /**
     * Keep oai dc / dcterms first.
     *
     * @var array
     */
    protected $sortedNames = [
        'oai_dc',
        'oai_dcterms',
    ];

    protected $autoAddInvokableClass = false;

    protected $instanceOf = HarvesterMapInterface::class;

    public function __construct($configOrContainerInstance = null, array $v3config = [])
    {
        parent::__construct($configOrContainerInstance, $v3config);
        $this->addInitializer(function ($serviceLocator, $instance): void {
            $instance->setServiceLocator($serviceLocator);
        });
    }
}
