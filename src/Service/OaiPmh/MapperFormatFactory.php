<?php declare(strict_types=1);

/**
 * Factory for MapperFormat harvester maps.
 *
 * Creates instances of MapperFormat configured for specific OAI-PMH formats
 * using mapping files from the Mapper module.
 *
 * @copyright Daniel Berthereau, 2024-2026
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace OaiPmhHarvester\Service\OaiPmh;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use OaiPmhHarvester\OaiPmh\HarvesterMap\MapperFormat;

class MapperFormatFactory implements AbstractFactoryInterface
{
    /**
     * Configuration for known formats using Mapper.
     *
     * Each format defines:
     * - mapping: Path to the mapping file (relative to Mapper module data/mapping/)
     * - metadata_root_xpath: XPath to extract the root element from OAI metadata
     * - namespaces: XML namespaces to register for XPath queries
     *
     * @var array
     */
    protected static $formatConfigs = [
        // EAD - Encoded Archival Description
        'ead' => [
            'mapping' => 'module:ead/ead.components.xml',
            'metadata_root_xpath' => null,
            'namespaces' => [
                'ead' => 'urn:isbn:1-931666-22-9',
            ],
        ],
        // EAD3
        'ead3' => [
            'mapping' => 'module:ead/ead.components.xml',
            'metadata_root_xpath' => null,
            'namespaces' => [
                'ead' => 'http://ead3.archivists.org/schema/',
            ],
        ],
        // LIDO - Lightweight Information Describing Objects
        'lido' => [
            'mapping' => 'module:lido/lido.mc.xml',
            'metadata_root_xpath' => null,
            'namespaces' => [
                'lido' => 'http://www.lido-schema.org',
                'gml' => 'http://www.opengis.net/gml',
                'skos' => 'http://www.w3.org/2004/02/skos/core#',
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            ],
        ],
        // LIDO-MC (French profile) - alias for lido
        'lido_mc' => [
            'mapping' => 'module:lido/lido.mc.xml',
            'metadata_root_xpath' => null,
            'namespaces' => [
                'lido' => 'http://www.lido-schema.org',
                'gml' => 'http://www.opengis.net/gml',
                'skos' => 'http://www.w3.org/2004/02/skos/core#',
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            ],
        ],
    ];

    /**
     * Determine if we can create a service with the given name.
     *
     * @param ContainerInterface $container
     * @param string $requestedName Format name (e.g., 'ead', 'lido')
     * @return bool
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        // Check if Mapper module is available.
        if (!$container->has('Mapper\Mapper')) {
            return false;
        }

        return isset(self::$formatConfigs[$requestedName]);
    }

    /**
     * Create a MapperFormat instance for the requested format.
     *
     * @param ContainerInterface $container
     * @param string $requestedName Format name
     * @param array|null $options
     * @return MapperFormat
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = self::$formatConfigs[$requestedName];

        $mapper = $container->get('Mapper\Mapper');
        $logger = $container->get('Omeka\Logger');

        return new MapperFormat(
            $mapper,
            $logger,
            $config['mapping'],
            $requestedName,
            $config['metadata_root_xpath'] ?? null,
            $config['namespaces'] ?? []
        );
    }

    /**
     * Get the list of available format names.
     *
     * @return array
     */
    public static function getAvailableFormats(): array
    {
        return array_keys(self::$formatConfigs);
    }

    /**
     * Register a new format configuration.
     *
     * This allows modules to add their own formats dynamically.
     *
     * @param string $name Format name
     * @param array $config Format configuration
     */
    public static function registerFormat(string $name, array $config): void
    {
        self::$formatConfigs[$name] = $config;
    }
}
