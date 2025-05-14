<?php declare(strict_types=1);

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Api\Manager as ApiManager;

class SourceAddForm extends Form
{
    protected ApiManager $apiManager;

    public function init()
    {
        $this->add([
            'name' => 'o:base_url',
            'type' => Element\Url::class,
            'options' => [
                'label' => 'OAI-PMH repository base URL', // @translate
            ],
            'attributes' => [
                'id' => 'base-url',
                'required' => true,
                'placeholder' => 'https://example.org/oai-pmh-repository',
            ],
        ]);

        $this->add([
            'name' => 'o:configuration',
            'type' => Fieldset::class,
        ]);

        $this->get('o:configuration')->add([
            'name' => 'o:id',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Configuration', // @translate
                'value_options' => $this->getConfigurationValueOptions(),
                'empty_option' => '',
            ],
            'attributes' => [
                'id' => 'configuration-id',
                'required' => true,
            ],
        ]);
    }

    public function getApiManager(): ApiManager
    {
        return $this->apiManager;
    }

    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }

    protected function getConfigurationValueOptions()
    {
        $api = $this->getApiManager();
        $configurations = $api->search('oaipmhharvester_configurations')->getContent();

        return array_map(fn($c) => ['value' => $c->id(), 'label' => $c->name()], $configurations);
    }
}
