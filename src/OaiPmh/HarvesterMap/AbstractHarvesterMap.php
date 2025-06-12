<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @copyright Daniel Berthereau, 2014-2025
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
namespace OaiPmhHarvester\OaiPmh\HarvesterMap;

use Laminas\ServiceManager\ServiceLocatorInterface;
use SimpleXMLElement;

/**
 * Abstract class on which all other metadata format harvests are based.
 */
abstract class AbstractHarvesterMap implements HarvesterMapInterface
{
    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

    protected $options = [
        'o:is_public' => false,
        'o:item_sets' => [],
    ];

    public function setServiceLocator(ServiceLocatorInterface $services): HarvesterMapInterface
    {
        $this->services = $services;
        return $this;
    }

    public function setOptions(array $options): HarvesterMapInterface
    {
        $this->options = $options;
        return $this;
    }

    protected function getOption($key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function mapRecord(SimpleXMLElement $record): array
    {
        $resource = [
            '@type' => 'o:Item',
            'o:is_public' => $this->getOption('o:is_public'),
            'o:media' => [],
            'o:item_set' => $this->getOption('o:item_set'),
        ];
        $resource = $this->mapRecordSingle($record, $resource);
        return [$resource];
    }

    protected function mapRecordSingle(SimpleXMLElement $record, array $resource): array
    {
        return $resource;
    }

    /**
     * Return whether the record is deleted
     *
     * @param SimpleXMLElement $record The record object
     * @return bool
     */
    public function isDeletedRecord(\SimpleXMLElement $record): bool
    {
        return isset($record->header->attributes()->status)
            && $record->header->attributes()->status == 'deleted';
    }

    /**
     * @param \SimpleXMLElement $metadata Filtered record or sub-record.
     * @param string $term An existing term.
     * @return array Property values for he specified term.
     */
    protected function extractValues(SimpleXMLElement $metadata, string $term): array
    {
        $values = [];

        $localName = strtok($term, ':');
        $localName = strtok(':');

        // The first check avoids an xml issue "Node no longer exists".
        if (!strlen((string) $localName) || !isset($metadata->$localName)) {
            return [];
        }

        $propertyId = $this->getPropertyIds()[$term];

        $defaultValue = [
            'type' => null,
            'property_id' => $propertyId,
            'is_public' => true,
        ];

        foreach ($metadata->$localName as $xmlValue) {
            $text = trim((string) $xmlValue);
            if (!mb_strlen($text)) {
                continue;
            }

            // Extract xsi type if any.
            $attributesXsi = iterator_to_array($xmlValue->attributes('xsi', true));
            $type = isset($attributesXsi['type']) && !empty($attributesXsi['type'])
                ? trim((string) $attributesXsi['type'])
                : null;
            $type = $type && in_array(strtolower($type), ['dcterms:uri', 'uri']) ? 'uri' : 'literal';

            // Extract xml language if any.
            $attributesXml = iterator_to_array($xmlValue->attributes('xml', true));
            $language = isset($attributesXml['lang']) && !empty((string) $attributesXml['lang'])
                ? trim((string) $attributesXml['lang'])
                : null;

            $value = $defaultValue;
            $value['type'] = $type;

            switch ($type) {
                // The type can never be a resource for now.
                // case 'resource':

                case 'uri':
                    $label = (isset($attributesXml['title']) && strlen((string) $attributesXml['title']) ? trim((string) $attributesXml['title']) : null)
                        ?? (isset($attributesXml['label']) && strlen((string) $attributesXml['label']) ? trim((string) $attributesXml['label']) : null);
                    $value['@id'] = $text;
                    $value['o:label'] = $label;
                    $value['@language'] = $language;
                    break;

                case 'literal':
                default:
                    $value['@value'] = $text;
                    $value['@language'] = $language;
                    break;
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * Get all property ids by term.
     *
     * @return array Associative array of ids by term.
     *
     * @todo Use \Common\Stdlib\EasyMeta.
     */
    protected function getPropertyIds(): array
    {
        static $properties;

        if (isset($properties)) {
            return $properties;
        }

        $connection = $this->services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                'property.id AS id',
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id'
            )
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('property.id', 'asc')
            ->addGroupBy('property.id')
        ;
        return $properties
            = array_map('intval', $connection->executeQuery($qb)->fetchAllKeyValue());
    }

    protected function getLocalNamesByIdForVocabulary(string $prefix): array
    {
        static $propertiesByVocabulary = [];

        if (isset($propertiesByVocabulary[$prefix])) {
            return $propertiesByVocabulary[$prefix];
        }

        if ($prefix === 'dc') {
            $propertiesByVocabulary[$prefix] = OaiDc::DUBLIN_CORE_ELEMENTS;
            return $propertiesByVocabulary[$prefix];
        }

        $result = [];
        foreach ($this->getPropertyIds() as $term => $id) {
            if (strtok($term, ':') === $prefix) {
                $result[$id] = strtok(':');
            }
        }

        $propertiesByVocabulary[$prefix] = $result;
        return $propertiesByVocabulary[$prefix];
    }
}
