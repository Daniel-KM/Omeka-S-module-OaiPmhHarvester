<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service\Converter;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Converter\HarvesterMapConverter;

class HarvesterMapConverterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $harvesterMapManager = $services->get('OaiPmhHarvester\OaiPmh\HarvesterMap\Manager');

        $harvesterMap = $harvesterMapManager->get($requestedName);
        $harvesterMap->setServiceLocator($services);

        $converter = new HarvesterMapConverter($requestedName, $harvesterMap);

        return $converter;
    }
}
