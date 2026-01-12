<?php declare(strict_types=1);

namespace OaiPmhHarvesterTest\OaiPmh\HarvesterMap;

use OaiPmhHarvester\OaiPmh\HarvesterMap\MapperFormat;
use OaiPmhHarvester\Service\OaiPmh\MapperFormatFactory;
use Omeka\Test\AbstractHttpControllerTestCase;
use SimpleXMLElement;

/**
 * Tests for MapperFormat harvester map.
 *
 * These tests verify that LIDO records are correctly mapped to Omeka resources
 * using the Mapper module integration.
 */
class MapperFormatTest extends AbstractHttpControllerTestCase
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    protected $services;

    /**
     * @var MapperFormat
     */
    protected $lidoFormat;

    /**
     * Path to test fixtures.
     */
    protected $fixturesPath;

    /**
     * Get the service locator from the application.
     */
    protected function getServiceLocator()
    {
        return $this->getApplication()->getServiceManager();
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getServiceLocator();
        $this->fixturesPath = dirname(__DIR__, 3) . '/fixtures';

        if (!$this->hasMapperModule()) {
            $this->markTestSkipped('Mapper module not available.');
        }

        $factory = new MapperFormatFactory();
        $this->lidoFormat = ($factory)($this->services, 'lido');
    }

    /**
     * Check if Mapper module is available for testing.
     */
    protected function hasMapperModule(): bool
    {
        return $this->services->has('Mapper\Mapper');
    }

    /**
     * Load an OAI-PMH record from a fixture file.
     */
    protected function loadOaiRecord(string $filename): SimpleXMLElement
    {
        $filepath = $this->fixturesPath . '/' . $filename;
        $this->assertFileExists($filepath, "Fixture file not found: $filepath");

        $xml = simplexml_load_file($filepath);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml, "Failed to load XML: $filepath");

        // Navigate to the record element
        $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $records = $xml->xpath('//oai:record');
        $this->assertNotEmpty($records, 'No OAI record found in fixture');

        return $records[0];
    }

    public function testGetMetadataPrefix(): void
    {
        $this->assertSame('lido', $this->lidoFormat->getMetadataPrefix());
    }

    public function testGetMappingReference(): void
    {
        $this->assertSame('module:lido/lido.mc.xml', $this->lidoFormat->getMappingReference());
    }

    public function testSetOptions(): void
    {
        $options = [
            'o:is_public' => false,
            'o:item_set' => [['o:id' => 123]],
        ];

        $result = $this->lidoFormat->setOptions($options);

        // setOptions should return $this for chaining
        $this->assertSame($this->lidoFormat, $result);
    }

    public function testSetOptionsOverridesMappingReference(): void
    {
        $options = [
            'mapping_reference' => 'module:custom/custom.xml',
        ];

        $this->lidoFormat->setOptions($options);

        $this->assertSame('module:custom/custom.xml', $this->lidoFormat->getMappingReference());
    }

    public function testMapRecordWithEmptyMetadata(): void
    {
        // Create a record with empty metadata
        // Note: An empty <metadata/> element will still be processed by Mapper,
        // but may return empty results or minimal defaults depending on the mapping.
        $recordXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record xmlns="http://www.openarchives.org/OAI/2.0/">
    <header>
        <identifier>test:empty</identifier>
        <datestamp>2024-01-01</datestamp>
    </header>
    <metadata></metadata>
</record>
XML;
        $record = new SimpleXMLElement($recordXml);

        $result = $this->lidoFormat->mapRecord($record);

        // Mapper processes the empty metadata and may return defaults
        $this->assertIsArray($result);
    }

    public function testMapRecordWithNoMetadataElement(): void
    {
        // Create a record without metadata element
        $recordXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record xmlns="http://www.openarchives.org/OAI/2.0/">
    <header>
        <identifier>test:no-metadata</identifier>
        <datestamp>2024-01-01</datestamp>
    </header>
</record>
XML;
        $record = new SimpleXMLElement($recordXml);

        $result = $this->lidoFormat->mapRecord($record);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testMapLidoRecordMonaLisa(): void
    {
        $record = $this->loadOaiRecord('lido/oai_record_mona_lisa.xml');

        $resources = $this->lidoFormat->mapRecord($record);

        $this->assertIsArray($resources);
        $this->assertNotEmpty($resources, 'Mapper should produce at least one resource');

        // Get the first (and likely only) resource
        $resource = $resources[0];
        $this->assertIsArray($resource);

        // Check that default type is set
        $this->assertArrayHasKey('@type', $resource);
        $this->assertSame('o:Item', $resource['@type']);

        // Check that o:is_public is set (default is false from AbstractHarvesterMap)
        $this->assertArrayHasKey('o:is_public', $resource);
        $this->assertFalse($resource['o:is_public']);

        // Check o:media array exists
        $this->assertArrayHasKey('o:media', $resource);
        $this->assertIsArray($resource['o:media']);
    }

    public function testMapLidoRecordWithCustomOptions(): void
    {
        $record = $this->loadOaiRecord('lido/oai_record_mona_lisa.xml');

        // Set custom options
        $this->lidoFormat->setOptions([
            'o:is_public' => false,
            'o:item_set' => [['o:id' => 456]],
        ]);

        $resources = $this->lidoFormat->mapRecord($record);

        $this->assertNotEmpty($resources);
        $resource = $resources[0];

        // Check visibility is set from options
        $this->assertFalse($resource['o:is_public']);

        // Check item sets are set from options
        $this->assertArrayHasKey('o:item_set', $resource);
        $this->assertEquals([['o:id' => 456]], $resource['o:item_set']);
    }

    /**
     * Test that the factory creates different format instances.
     */
    public function testEadFormatIsDifferentFromLido(): void
    {
        $factory = new MapperFormatFactory();
        $eadFormat = ($factory)($this->services, 'ead');

        $this->assertNotSame($this->lidoFormat, $eadFormat);
        $this->assertSame('ead', $eadFormat->getMetadataPrefix());
        $this->assertSame('module:ead/ead.components.xml', $eadFormat->getMappingReference());
    }
}
