<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Converter\Manager;
use Omeka\Service\Exception\ConfigException;

class ConverterManagerFactory implements FactoryInterface
{
    /**
     * Create the oai metadata format manager service.
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        if (!isset($config['oaipmhharvester_converters'])) {
            throw new ConfigException('Missing OAI-PMH Harvester converters configuration');
        }
        return new Manager($services, $config['oaipmhharvester_converters']);
    }
}
