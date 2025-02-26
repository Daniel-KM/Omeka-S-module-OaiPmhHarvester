<?php declare(strict_types=1);

namespace OaiPmhHarvester;

return [
    'service_manager' => [
        'factories' => [
            'OaiPmhHarvester\Client' => Service\ClientFactory::class,
            'OaiPmhHarvester\ConverterManager' => Service\ConverterManagerFactory::class,
            OaiPmh\HarvesterMap\Manager::class => Service\OaiPmh\HarvesterMapManagerFactory::class,
        ],
        'aliases' => [
            'OaiPmh\HarvesterMapManager' => OaiPmh\HarvesterMap\Manager::class,
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'oaipmhharvester_entities' => Api\Adapter\EntityAdapter::class,
            'oaipmhharvester_harvests' => Api\Adapter\HarvestAdapter::class,
            'oaipmhharvester_sources' => Api\Adapter\SourceAdapter::class,
            'oaipmhharvester_source_records' => Api\Adapter\SourceRecordAdapter::class,
            'oaipmhharvester_configurations' => Api\Adapter\ConfigurationAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'oaipmhharvesterFormSetsTextarea' => Form\View\Helper\FormSetsTextarea::class,
            'oaipmhharvesterFormFields' => Form\View\Helper\FormFields::class,
        ],
        'delegators' => [
            'Laminas\Form\View\Helper\FormElement' => [
                Service\Delegator\FormElementDelegatorFactory::class,
            ],
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Element\Fields::class => Form\Element\Fields::class,
            Form\Element\SetsTextarea::class => Form\Element\SetsTextarea::class,
            Form\MappingForm::class => Form\MappingForm::class,
            Form\SetsForm::class => Form\SetsForm::class,
        ],
        'factories' => [
            Form\HarvestForm::class => Service\Form\HarvestFormFactory::class,
            Form\ConfigurationAddForm::class => Service\Form\ConfigurationAddFormFactory::class,
            Form\SourceAddForm::class => Service\Form\SourceAddFormFactory::class,
            Form\SourceEditForm::class => Service\Form\SourceEditFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'OaiPmhHarvester\Controller\Admin\Index' => Controller\Admin\IndexController::class,
            'OaiPmhHarvester\Controller\Admin\Source' => Controller\Admin\SourceController::class,
            'OaiPmhHarvester\Controller\Admin\Configuration' => Controller\Admin\ConfigurationController::class,
            'OaiPmhHarvester\Controller\Admin\Mappings' => Controller\Admin\MappingsController::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'oaiPmhRepository' => Service\ControllerPlugin\OaiPmhRepositoryFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'oaipmhharvester' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/oai-pmh-harvester',
                            'defaults' => [
                                '__NAMESPACE__' => 'OaiPmhHarvester\Controller\Admin',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => 'index|sets|harvest|past-harvests',
                                    ],
                                    'defaults' => [
                                        'action' => 'sets',
                                    ],
                                ],
                            ],
                            'source' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/source[/:action]',
                                    'defaults' => [
                                        'controller' => 'source',
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'source-id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/source/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'controller' => 'source',
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                            'configuration' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/configuration[/:action]',
                                    'defaults' => [
                                        'controller' => 'configuration',
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'configuration-id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/configuration/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'controller' => 'configuration',
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                            'mappings' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/mappings/:action',
                                    'defaults' => [
                                        'controller' => 'mappings',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'OAI-PMH Harvester', // @translate
                'route' => 'admin/oaipmhharvester',
                'resource' => 'OaiPmhHarvester\Controller\Admin\Index',
                'class' => 'o-icon- fa-seedling',
                'pages' => [
                    [
                        'label' => 'One-off harvests', // @translate
                        'route' => 'admin/oaipmhharvester',
                        'pages' => [
                            [
                                'route' => 'admin/oaipmhharvester/default',
                                'visible' => false,
                            ],
                        ],
                    ],
                    [
                        'label' => 'Sources', // @translate
                        'route' => 'admin/oaipmhharvester/source',
                        'resource' => 'OaiPmhHarvester\Controller\Admin\Source',
                        'privilege' => 'browse',
                        'pages' => [
                            [
                                'route' => 'admin/oaipmhharvester/source-id',
                                'visible' => false,
                            ],
                        ],
                    ],
                    [
                        'label' => 'Configurations', // @translate
                        'route' => 'admin/oaipmhharvester/configuration',
                        'resource' => 'OaiPmhHarvester\Controller\Admin\Configuration',
                        'privilege' => 'browse',
                        'pages' => [
                            [
                                'route' => 'admin/oaipmhharvester/configuration-id',
                                'visible' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'OaiPmhHarvester' => [
            [
                'label' => 'One-off harvest', // @translate
                'route' => 'admin/oaipmhharvester',
                'resource' => 'OaiPmhHarvester\Controller\Admin\Index',
                'action' => 'index',
                'privilege' => 'edit',
                'useRouteMatch' => true,
            ],
            [
                'label' => 'Past one-off harvests', // @translate
                'route' => 'admin/oaipmhharvester/default',
                'resource' => 'OaiPmhHarvester\Controller\Admin\Index',
                'action' => 'past-harvests',
                'privilege' => 'view',
                'useRouteMatch' => true,
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        'add set', // @translate
    ],
    'oaipmh_harvester_maps' => [
        'invokables' => [
            // Let oai_dc first, the only required format.
            'oai_dc' => OaiPmh\HarvesterMap\OaiDc::class,
            'oai_dcterms' => OaiPmh\HarvesterMap\OaiDcTerms::class,
            'mets' => OaiPmh\HarvesterMap\Mets::class,
            // 'mock' => OaiPmh\HarvesterMap\Mock::class,
        ],
        'aliases' => [
            'dc' => 'oai_dc',
            'dcterms' => 'oai_dcterms',
            'oai_dcq' => 'oai_dcterms',
            'oai_qdc' => 'oai_dcterms',
            'dcq' => 'oai_dcterms',
            'qdc' => 'oai_dcterms',
        ],
    ],

    'advancedsearch' => [
        'search_fields' => [
            'common/advanced-search/harvests' => [
                'module' => 'OaiPmhHarvester',
                'label' => 'OAI-PMH harvests', // @translate
                'resource_type' => ['items'],
                'default_admin' => true,
                'default_site' => false,
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'oaipmhharvester_sources' => [
                'sort_by' => 'name',
                'sort_order' => 'asc',
            ],
            'oaipmhharvester_configurations' => [
                'sort_by' => 'name',
                'sort_order' => 'asc',
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'oaipmhharvester_sources' => [
                'name' => 'Name', // @translate
            ],
            'oaipmhharvester_configurations' => [
                'name' => 'Name', // @translate
            ],
        ],
    ],
    'oaipmhharvester_converters' => [
        'factories' => [
            'xpath' => Service\Converter\XPathConverterFactory::class,
            'oai_dc' => Service\Converter\HarvesterMapConverterFactory::class,
            'oai_dcterms' => Service\Converter\HarvesterMapConverterFactory::class,
            'mets' => Service\Converter\HarvesterMapConverterFactory::class,
        ],
    ],
];
