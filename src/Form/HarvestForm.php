<?php declare(strict_types=1);

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Validator\Callback;
use OaiPmhHarvester\Entity\Harvest;
use OaiPmhHarvester\Mvc\Controller\Plugin\OaiPmhRepository;
use Omeka\Form\Element as OmekaElement;

class HarvestForm extends Form
{
    /**
     * @var \OaiPmhHarvester\Mvc\Controller\Plugin\OaiPmhRepository
     */
    protected $oaiPmhRepository;

    public function init(): void
    {
        $translator = $this->oaiPmhRepository->getTranslator();

        $this
            ->setAttribute('id', 'harvest-repository')
            ->setAttribute('class', 'oai-pmh-harvester')

            ->add([
                'name' => 'endpoint',
                'type' => Element\Url::class,
                'options' => [
                    'label' => 'OAI-PMH endpoint', // @translate
                    'info' => 'The base URL of the OAI-PMH data provider.', // @translate
                ],
                'attributes' => [
                    'id' => 'endpoint',
                    'required' => true,
                    // The protocol requires http, but most of repositories
                    // support https, except Gallica and some other big
                    // institutions.
                    'placeholder' => 'https://example.org/oai-pmh-repository',
                ],
                // TODO Add a filter to remove query and fragment.
            ])
            ->add([
                'name' => 'harvest_all_records',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Skip listing of sets and harvest all records', // @translate
                ],
                'attributes' => [
                    'id' => 'harvest_all_records',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'predefined_sets',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Skip listing of sets and harvest only these sets', // @translate
                    'info' => 'Set one set identifier and a metadata prefix by line. Separate the set and the prefix by "=". If no prefix is set, "dcterms" or "oai_dc" will be used.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'predefined_sets',
                    'row' => 10,
                    'placeholder' => <<<'TXT'
                        digital:serie-alpha = mets
                        humanities:serie-beta
                        TXT,
                ],
            ])

            // TODO Find a way to use DateTimeLocal with optional time (js issue).
            // TODO So create a specific DateTime element with the two field merged into one visually.
            ->add([
                'name' => 'from',
                // 'type' => Element\DateTimeLocal::class,
                'type' => Element\Date::class,
                'options' => [
                    'label' => 'From date', // @translate
                    'info' => 'Date should be UTC. Time is optional. Value is included (≥).', // @translate
                    'should_show_seconds' => true,
                ],
                'attributes' => [
                    'id' => 'from',
                    'step' => 1,
                    'placeholder' => '2025-01-01',
                    'class' => 'datetime-date datetime-from',
                ],
            ])
            ->add([
                'name' => 'from_time',
                'type' => Element\Time::class,
                'options' => [
                    'label' => 'Optional from time', // @translate
                    'should_show_seconds' => true,
                ],
                'attributes' => [
                    'id' => 'from-time',
                    'step' => 1,
                    'placeholder' => '00:00:00',
                    'class' => 'datetime-time datetime-from',
                ],
            ])
            ->add([
                'name' => 'until',
                // 'type' => Element\DateTimeLocal::class,
                'type' => Element\Date::class,
                'options' => [
                    'label' => 'From date', // @translate
                    'label' => 'Until date', // @translate
                    'info' => 'Date should be UTC. Time is optional. Value is included (≤).', // @translate
                    'should_show_seconds' => true,
                ],
                'attributes' => [
                    'id' => 'until',
                    'step' => 1,
                    'placeholder' => '2025-01-31',
                    'class' => 'datetime-date datetime-until',
                ],
            ])
            ->add([
                'name' => 'until_time',
                'type' => Element\Time::class,
                'options' => [
                    'label' => 'Optional until time', // @translate
                    'should_show_seconds' => true,
                ],
                'attributes' => [
                    'id' => 'until-time',
                    'step' => 1,
                    'placeholder' => '23:59:59',
                    'class' => 'datetime-time datetime-until',
                ],
            ])

            ->add([
                'name' => 'filters_whitelist',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Filters on each record (whitelist)', // @translate
                    'info' => 'Add strings to filter the input, for example to import only some articles of a journal.', // @translate
                ],
                'attributes' => [
                    'id' => 'filters_whitelist',
                ],
            ])
            ->add([
                'name' => 'filters_blacklist',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Filters on each record (blacklist)', // @translate
                    'info' => 'Add strings to filter the input, for example to import only some articles of a journal.', // @translate
                ],
                'attributes' => [
                    'id' => 'filters_blacklist',
                ],
            ])

            ->add([
                'name' => 'mode_harvest',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Import/update mode for atomic formats', // @translate
                    'info' => 'An atomic format is a format where an oai record with an oai identifier maps to a single resource in Omeka. Ead via oai-pmh is not an atomic format, so a reharvest will duplicate records.', // @translate
                    'value_options' => [
                        Harvest::MODE_SKIP => 'Skip record (keep existing resource)', // @translate
                        Harvest::MODE_APPEND => 'Append new values', // @translate
                        Harvest::MODE_UPDATE => 'Replace existing values and let values of properties not present in harvested record', // @translate
                        Harvest::MODE_REPLACE => 'Replace the whole existing resource', // @translate
                        Harvest::MODE_DUPLICATE => 'Create a new resource (not recommended)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'mode_harvest',
                ],
            ])

            ->add([
                'name' => 'mode_delete',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Process for oai record marked as deleted', // @translate
                    'value_options' => [
                        Harvest::MODE_SKIP => 'Skip', // @translate
                        Harvest::MODE_DELETE_FILTERED  => 'Delete resources previously imported', // @translate
                        Harvest::MODE_DELETE => 'Delete resources previously imported, whatever the filters', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'mode_delete',
                    'value' => 'skip',
                ],
            ])

            ->add([
                'name' => 'item_set',
                'type' => OmekaElement\ItemSetSelect::class,
                'options' => [
                    'label' => 'Item set for items', // @translate
                    'prepend_value_options' => [
                        'none' => 'No item set', // @translate
                        'new' => 'Create a new item set', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'item_set',
                    'value' => 'none',
                ],
            ])

            ->add([
                'name' => 'store_xml',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Store oai-pmh xml response for list of sets', // @translate
                    'info' => 'See log to get the url.', // @translate
                ],
                'attributes' => [
                    'id' => 'store_xml',
                ],
            ])

            ->add([
                'type' => Element\Hidden::class,
                'name' => 'step',
                'attributes' => [
                    'id' => 'step',
                    'value' => 'harvest-repository',
                ],
            ])
        ;

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'endpoint',
                'required' => true,
                'validators' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => [$this->oaiPmhRepository, 'hasNoQueryAndNoFragment'],
                            'messages' => [
                                'callbackValue' => $translator->translate('The endpoint "%value%" should not have a query.'), // @translate
                            ],
                        ],
                    ],
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => [$this->oaiPmhRepository, 'isXmlEndpoint'],
                            'messages' => [
                                'callbackValue' => $translator->translate('The endpoint "%value%" does not return xml.'), // @translate
                            ],
                        ],
                    ],
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => [$this->oaiPmhRepository, 'hasOaiPmhManagedFormats'],
                            'messages' => [
                                'callbackValue' => $translator->translate('The endpoint "%value%" does not manage any format.'), // @translate
                            ],
                        ],
                    ],
                ],
            ])
            ->add([
                'name' => 'until',
                'required' => false,
            ])
            ->add([
                'name' => 'until_time',
                'required' => false,
            ])
            ->add([
                'name' => 'from_time',
                'required' => false,
            ])
            ->add([
                'name' => 'from',
                'required' => false,
                'validators' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => function ($value, $context) {
                                $from = $context['from'] ?? null;
                                if ($from && !empty($context['from_time'])) {
                                    $from = $from . 'T' . $context['from_time'] . 'Z';
                                }
                                $until = $context['until'] ?? null;
                                if ($until && !empty($context['until_time'])) {
                                    $until = $until . 'T' . $context['until_time'] . 'Z';
                                }
                                return !$from
                                    || !$until
                                    || new \DateTime($from) <= new \DateTime($until);
                            },
                            'messages' => [
                                Callback::INVALID_VALUE => 'When set, the "from" date must be before the "until" date.', // @translate
                            ],
                        ],
                    ],
                ],
            ])
        ;
    }

    public function setOaiPmhRepository(OaiPmhRepository $oaiPmhRepository): self
    {
        $this->oaiPmhRepository = $oaiPmhRepository;
        return $this;
    }
}
