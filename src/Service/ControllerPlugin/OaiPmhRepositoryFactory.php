<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Mvc\Controller\Plugin\OaiPmhRepository;

class OaiPmhRepositoryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $baseUri = $config['file_store']['local']['base_uri'] ?: '';

        if (empty($this->baseUri)) {
            $helpers = $services->get('ViewHelperManager');
            $serverUrlHelper = $helpers->get('ServerUrl');
            $basePathHelper = $helpers->get('BasePath');
            $baseUri = $serverUrlHelper($basePathHelper('files'));
        }

        return new OaiPmhRepository(
            $services->get(\OaiPmhHarvester\OaiPmh\HarvesterMap\Manager::class),
            $services->get('OaiPmhHarvester\Client'),
            $services->get('Omeka\Logger'),
            $services->get('MvcTranslator'),
            $basePath,
            $baseUri
        );
    }
}
