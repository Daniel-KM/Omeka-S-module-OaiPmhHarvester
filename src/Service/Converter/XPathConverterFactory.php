<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service\Converter;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Converter\XPathConverter;

class XPathConverterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $apiManager = $services->get('Omeka\ApiManager');
        $viewHelperManager = $services->get('ViewHelperManager');
        $logger = $services->get('Omeka\Logger');

        $converter = new XPathConverter($apiManager, $viewHelperManager, $logger);

        return $converter;
    }
}
