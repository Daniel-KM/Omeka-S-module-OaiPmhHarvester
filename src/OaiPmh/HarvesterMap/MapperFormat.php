<?php declare(strict_types=1);

/**
 * Generic harvester map that uses the Mapper module for XML conversion.
 *
 * This class allows OaiPmhHarvester to use any mapping file from the Mapper
 * module, enabling support for formats like EAD, LIDO-MC, METS, etc.
 *
 * @copyright Daniel Berthereau, 2024-2026
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace OaiPmhHarvester\OaiPmh\HarvesterMap;

use Laminas\Log\Logger;
use Mapper\Stdlib\Mapper;
use SimpleXMLElement;

class MapperFormat extends AbstractHarvesterMap
{
    /**
     * @var \Mapper\Stdlib\Mapper
     */
    protected $mapper;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * Mapping file reference (path or module:path).
     *
     * @var string
     */
    protected $mappingReference;

    /**
     * Metadata prefix for this format.
     *
     * @var string
     */
    protected $metadataPrefix;

    /**
     * XPath to the metadata root element within the OAI record.
     *
     * If null, the whole record/metadata element is used.
     *
     * @var string|null
     */
    protected $metadataRootXpath;

    /**
     * Namespaces to register for XPath queries.
     *
     * @var array
     */
    protected $namespaces = [];

    /**
     * Whether the mapping was successfully loaded.
     *
     * @var bool
     */
    protected $mappingLoaded = false;

    public function __construct(
        Mapper $mapper,
        Logger $logger,
        string $mappingReference,
        string $metadataPrefix,
        ?string $metadataRootXpath = null,
        array $namespaces = []
    ) {
        $this->mapper = $mapper;
        $this->logger = $logger;
        $this->mappingReference = $mappingReference;
        $this->metadataPrefix = $metadataPrefix;
        $this->metadataRootXpath = $metadataRootXpath;
        $this->namespaces = $namespaces;
    }

    public function setOptions(array $options): HarvesterMapInterface
    {
        parent::setOptions($options);

        // Allow overriding the mapping reference via options.
        if (!empty($options['mapping_reference'])) {
            $this->mappingReference = $options['mapping_reference'];
            $this->mappingLoaded = false;
        }

        return $this;
    }

    /**
     * Load the mapping configuration.
     */
    protected function loadMapping(): bool
    {
        if ($this->mappingLoaded) {
            return true;
        }

        if (empty($this->mappingReference)) {
            $this->logger->err(
                'No mapping reference configured for format "{format}".', // @translate
                ['format' => $this->metadataPrefix]
            );
            return false;
        }

        try {
            $this->mapper->setMapping($this->metadataPrefix, $this->mappingReference);
            $mapping = $this->mapper->getMapping();

            if (!$mapping || !empty($mapping['has_error'])) {
                $this->logger->err(
                    'Failed to load mapping "{mapping}" for format "{format}".', // @translate
                    ['mapping' => $this->mappingReference, 'format' => $this->metadataPrefix]
                );
                return false;
            }

            $this->mappingLoaded = true;
            return true;
        } catch (\Exception $e) {
            $this->logger->err(
                'Error loading mapping "{mapping}" for format "{format}": {error}', // @translate
                ['mapping' => $this->mappingReference, 'format' => $this->metadataPrefix, 'error' => $e->getMessage()]
            );
            return false;
        }
    }

    public function mapRecord(SimpleXMLElement $record): array
    {
        if (!$this->loadMapping()) {
            return [];
        }

        // Get the metadata element from the OAI record.
        $metadata = $record->metadata;
        if (!$metadata || !$metadata->count()) {
            $this->logger->warn(
                'OAI record has no metadata element.' // @translate
            );
            return [];
        }

        // Register namespaces for XPath.
        foreach ($this->namespaces as $prefix => $uri) {
            $metadata->registerXPathNamespace($prefix, $uri);
        }

        // If a root XPath is specified, extract the root element.
        $dataToMap = $metadata;
        if ($this->metadataRootXpath) {
            $roots = $metadata->xpath($this->metadataRootXpath);
            if ($roots && count($roots)) {
                $dataToMap = $roots[0];
            }
        }

        // Use Mapper to convert the XML.
        $result = $this->mapper->convert($dataToMap);

        if (empty($result)) {
            return [];
        }

        // Mapper returns different structures depending on the mapping.
        // Normalize to ensure we always return an array of resources.
        $resources = $this->normalizeMapperResult($result);

        // Apply default options to each resource.
        foreach ($resources as &$resource) {
            $resource = $this->applyDefaults($resource);
        }

        return $resources;
    }

    /**
     * Normalize the result from Mapper to an array of resources.
     */
    protected function normalizeMapperResult(array $result): array
    {
        // If the result has a '@type' key, it's a single resource.
        if (isset($result['@type']) || isset($result['o:id'])) {
            return [$result];
        }

        // If the result is an indexed array of resources.
        if (isset($result[0])) {
            return $result;
        }

        // If the result looks like a flat property mapping, wrap it.
        if ($this->looksLikeResourceData($result)) {
            return [$result];
        }

        // Default: assume it's a single resource.
        return [$result];
    }

    /**
     * Check if the array looks like resource data (has property terms as keys).
     */
    protected function looksLikeResourceData(array $data): bool
    {
        foreach (array_keys($data) as $key) {
            // Check for common Omeka keys or property terms.
            if (strpos($key, ':') !== false
                || in_array($key, ['@type', 'o:id', 'o:is_public', 'o:media', 'o:item_set', 'o:resource_template', 'o:resource_class'])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Apply default options to a resource.
     */
    protected function applyDefaults(array $resource): array
    {
        // Set resource type if not specified.
        if (!isset($resource['@type'])) {
            $resource['@type'] = 'o:Item';
        }

        // Set visibility.
        if (!isset($resource['o:is_public'])) {
            $resource['o:is_public'] = $this->getOption('o:is_public', true);
        }

        // Initialize media array if not set.
        if (!isset($resource['o:media'])) {
            $resource['o:media'] = [];
        }

        // Set item sets.
        $itemSets = $this->getOption('o:item_set', []);
        if ($itemSets && !isset($resource['o:item_set'])) {
            $resource['o:item_set'] = $itemSets;
        }

        return $resource;
    }

    /**
     * Get the metadata prefix for this format.
     */
    public function getMetadataPrefix(): string
    {
        return $this->metadataPrefix;
    }

    /**
     * Get the mapping reference.
     */
    public function getMappingReference(): string
    {
        return $this->mappingReference;
    }
}
