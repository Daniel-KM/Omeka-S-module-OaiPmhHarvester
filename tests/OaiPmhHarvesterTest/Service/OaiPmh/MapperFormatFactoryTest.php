<?php declare(strict_types=1);

namespace OaiPmhHarvesterTest\Service\OaiPmh;

use OaiPmhHarvester\OaiPmh\HarvesterMap\MapperFormat;
use OaiPmhHarvester\Service\OaiPmh\MapperFormatFactory;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for MapperFormatFactory.
 *
 * These tests verify that the factory correctly creates MapperFormat instances
 * when the Mapper module is available, and returns false when it is not.
 */
class MapperFormatFactoryTest extends AbstractHttpControllerTestCase
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    protected $services;

    /**
     * @var MapperFormatFactory
     */
    protected $factory;

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
        $this->factory = new MapperFormatFactory();
    }

    /**
     * Check if Mapper module is available for testing.
     */
    protected function hasMapperModule(): bool
    {
        return $this->services->has('Mapper\Mapper');
    }

    public function testCanCreateReturnsFalseWithoutMapper(): void
    {
        // Create a mock container without Mapper service
        $mockContainer = $this->createMock(\Psr\Container\ContainerInterface::class);
        $mockContainer->method('has')
            ->with('Mapper\Mapper')
            ->willReturn(false);

        $factory = new MapperFormatFactory();

        $this->assertFalse($factory->canCreate($mockContainer, 'lido'));
        $this->assertFalse($factory->canCreate($mockContainer, 'ead'));
    }

    public function testCanCreateReturnsFalseForUnknownFormat(): void
    {
        if (!$this->hasMapperModule()) {
            $this->markTestSkipped('Mapper module not available.');
        }

        $this->assertFalse($this->factory->canCreate($this->services, 'unknown_format'));
        $this->assertFalse($this->factory->canCreate($this->services, 'invalid'));
        $this->assertFalse($this->factory->canCreate($this->services, ''));
    }

    public function testCanCreateReturnsTrueForKnownFormatsWithMapper(): void
    {
        if (!$this->hasMapperModule()) {
            $this->markTestSkipped('Mapper module not available.');
        }

        $this->assertTrue($this->factory->canCreate($this->services, 'ead'));
        $this->assertTrue($this->factory->canCreate($this->services, 'ead3'));
        $this->assertTrue($this->factory->canCreate($this->services, 'lido'));
        $this->assertTrue($this->factory->canCreate($this->services, 'lido_mc'));
    }

    public function testInvokeCreatesMapperFormatInstance(): void
    {
        if (!$this->hasMapperModule()) {
            $this->markTestSkipped('Mapper module not available.');
        }

        $format = ($this->factory)($this->services, 'lido');

        $this->assertInstanceOf(MapperFormat::class, $format);
        $this->assertSame('lido', $format->getMetadataPrefix());
        $this->assertSame('module:lido/lido.mc.xml', $format->getMappingReference());
    }

    public function testInvokeCreatesEadFormat(): void
    {
        if (!$this->hasMapperModule()) {
            $this->markTestSkipped('Mapper module not available.');
        }

        $format = ($this->factory)($this->services, 'ead');

        $this->assertInstanceOf(MapperFormat::class, $format);
        $this->assertSame('ead', $format->getMetadataPrefix());
        $this->assertSame('module:ead/ead.components.xml', $format->getMappingReference());
    }

    public function testInvokeCreatesEad3Format(): void
    {
        if (!$this->hasMapperModule()) {
            $this->markTestSkipped('Mapper module not available.');
        }

        $format = ($this->factory)($this->services, 'ead3');

        $this->assertInstanceOf(MapperFormat::class, $format);
        $this->assertSame('ead3', $format->getMetadataPrefix());
        // EAD3 uses the same mapping as EAD
        $this->assertSame('module:ead/ead.components.xml', $format->getMappingReference());
    }

    public function testInvokeCreatesLidoMcFormat(): void
    {
        if (!$this->hasMapperModule()) {
            $this->markTestSkipped('Mapper module not available.');
        }

        $format = ($this->factory)($this->services, 'lido_mc');

        $this->assertInstanceOf(MapperFormat::class, $format);
        $this->assertSame('lido_mc', $format->getMetadataPrefix());
        $this->assertSame('module:lido/lido.mc.xml', $format->getMappingReference());
    }

    public function testGetAvailableFormats(): void
    {
        $formats = MapperFormatFactory::getAvailableFormats();

        $this->assertIsArray($formats);
        $this->assertContains('ead', $formats);
        $this->assertContains('ead3', $formats);
        $this->assertContains('lido', $formats);
        $this->assertContains('lido_mc', $formats);
    }

    public function testRegisterFormat(): void
    {
        MapperFormatFactory::registerFormat('custom_format', [
            'mapping' => 'module:custom/custom.xml',
            'metadata_root_xpath' => null,
            'namespaces' => ['custom' => 'http://example.org/custom'],
        ]);

        $formats = MapperFormatFactory::getAvailableFormats();
        $this->assertContains('custom_format', $formats);
    }
}
