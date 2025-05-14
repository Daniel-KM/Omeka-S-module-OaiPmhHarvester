<?php

namespace OaiPmhHarvester\Converter;

use DOMElement;
use Generator;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Log\LoggerInterface;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use OaiPmhHarvester\OaiPmh\OaiRecord;
use OaiPmhHarvester\Form\Element\Fields;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\PropertyRepresentation;
use Omeka\Form\Element\ArrayTextarea;

class XPathConverter implements ConfigurableConverterInterface
{
    protected $apiManager;
    protected HelperPluginManager $viewHelperManager;
    protected LoggerInterface $logger;

    public function __construct(ApiManager $apiManager, HelperPluginManager $viewHelperManager, LoggerInterface $logger)
    {
        $this->apiManager = $apiManager;
        $this->viewHelperManager = $viewHelperManager;
        $this->logger = $logger;
    }

    public function getLabel(): string
    {
        return 'XPath converter'; // @translate
    }

    /**
     * @return an array that can be passed directly as 2nd parameter of
     *         \Omeka\Api\Manager::batchCreate
     */
    public function convert(OaiRecord $record, array $settings = []): Generator
    {
        $xpath = $record->getDOMXPath();
        $element = $record->getDOMElement();

        $namespaces = $settings['namespaces'] ?? [];
        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $itemData = [];

        $mappings = $settings['mappings'] ?? [];
        foreach ($mappings as $mapping) {
            $property = $this->getPropertyByTerm($mapping['property']);
            if (!$property) {
                $this->logger->warn(sprintf('Unknown property: %s', $mapping['property']));
                continue;
            }

            $nodeList = $xpath->query($mapping['xpath'], $element);
            if ($nodeList === false) {
                $this->logger->warn(sprintf('XPath expression is invalid: %s', $mapping['xpath']));
                continue;
            }

            $type = $mapping['type'] ?? 'literal';
            if ($type !== 'literal' && $type !== 'uri') {
                $type = 'literal';
            }

            foreach ($nodeList as $node) {
                $value = trim($node->textContent);
                if ($value === '') {
                    continue;
                }

                $replacements = $this->stringToKeyValues($mapping['replacements'] ?? '');
                if (array_key_exists($value, $replacements)) {
                    $value = $replacements[$value];
                }

                $valueData = [
                    'property_id' => $property->id(),
                    'is_public' => true,
                    'type' => $type,
                ];
                if ($type === 'uri') {
                    $valueData['@id'] = $value;
                } elseif ($type === 'literal') {
                    $valueData['@value'] = $value;
                }

                if ($node instanceof DOMElement) {
                    $lang = trim($node->getAttribute('xml:lang'));
                    if ($lang) {
                        if ($type === 'uri') {
                            $valueData['o:lang'] = $lang;
                        } elseif ($type === 'literal') {
                            $valueData['@language'] = $lang;
                        }
                    }
                }

                $itemData[$property->term()] ??= [];
                $itemData[$property->term()][] = $valueData;
            }
        }

        $itemId = yield $itemData;
    }

    public function addConfigurationFormElements(Form $form): void
    {
        $configuration = $form->getOption('configuration');

        $url = $this->viewHelperManager->get('Url');

        $form->get('o:settings')->add([
            'name' => 'namespaces',
            'type' => ArrayTextarea::class,
            'options' => [
                'label' => 'XML namespaces', // @translate
                'info' => 'XML namespaces used in the XPath mappings should be registered here, one per line, in the format: <code>prefix = namespace-uri</code>.<br>For instance: <code>oai_dc = http://www.openarchives.org/OAI/2.0/oai_dc/</code>.<br>The <code>oai</code> namespace is always registered and does not need to be listed here.', // @translate
                'escape_info' => false,
                'as_key_value' => true,
            ],
            'attributes' => [
                'placeholder' => "oai_dc = http://www.openarchives.org/OAI/2.0/oai_dc/\ndc = http://purl.org/dc/elements/1.1/",
                'rows' => 5,
            ],
        ]);

        $form->get('o:settings')->add([
            'name' => 'mappings',
            'type' => Fields::class,
            'options' => [
                'label' => 'Mappings', // @translate
                'empty_option' => 'Add a mapping', // @translate
                'value_options' => [
                    [
                        'value' => 'xpath',
                        'label' => 'XPath mapping', // @translate
                        'attributes' => [
                            'data-repeatable' => '1',
                        ],
                    ],
                ],
                'field_list_url' => $url('admin/oaipmhharvester/mappings', ['action' => 'field-list'], ['query' => ['configuration_id' => $configuration->id()]]),
                'field_row_url' => $url('admin/oaipmhharvester/mappings', ['action' => 'field-row'], ['query' => ['configuration_id' => $configuration->id()]]),
                'field_edit_sidebar_url' => $url('admin/oaipmhharvester/mappings', ['action' => 'field-edit-sidebar'], ['query' => ['configuration_id' => $configuration->id()]]),
            ],
        ]);
    }

    public function addConfigurationFormInputFilters(Form $form, InputFilterInterface $inputFilter): void
    {
    }

    public function getConfigurationDetails(PhpRenderer $renderer, array $settings): string
    {
        return $renderer->partial('oai-pmh-harvester/common/xpath-converter-details', ['settings' => $settings]);
    }

    public function getConfigurationDetailsFull(PhpRenderer $renderer, array $settings): string
    {
        return $renderer->partial('oai-pmh-harvester/common/xpath-converter-details-full', ['settings' => $settings]);
    }

    protected function getPropertyByTerm (string $term): ?PropertyRepresentation
    {
        $properties = $this->apiManager->search('properties', ['term' => $term])->getContent();

        return $properties ? $properties[0] : null;
    }

    protected function stringToKeyValues(string $string): array
    {
        $result = [];
        foreach ($this->stringToList($string) as $keyValue) {
            [$key, $value] = array_map('trim', explode('=', $keyValue, 2));
            $result[$key] = $value;
        }

        return $result;
    }

    protected function stringToList(string $string): array
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))), 'strlen');
    }

    protected function fixEndOfLine(string $string): string
    {
        return str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $string);
    }
}
