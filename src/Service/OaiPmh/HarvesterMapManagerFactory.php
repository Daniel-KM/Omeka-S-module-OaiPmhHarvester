<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service\OaiPmh;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\OaiPmh\HarvesterMap\Manager;
use Omeka\Service\Exception\ConfigException;

class HarvesterMapManagerFactory implements FactoryInterface
{
    /**
     * Create the oai metadata format manager service.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        if (empty($config['oaipmh_harvester_maps'])) {
            throw new ConfigException('Missing OAI-PMH Harvester configuration'); // @translate
        }
        return new Manager($services, $config['oaipmh_harvester_maps']);
    }
}
