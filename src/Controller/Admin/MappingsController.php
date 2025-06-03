<?php
namespace OaiPmhHarvester\Controller\Admin;

use Laminas\View\Model\ViewModel;
use Laminas\Mvc\Controller\AbstractActionController;
use OaiPmhHarvester\Form\MappingForm;

class MappingsController extends AbstractActionController
{
    public function fieldListAction()
    {
        $configuration_id = $this->params()->fromQuery('configuration_id');
        $configuration = $this->api()->read('oaipmhharvester_configurations', $configuration_id)->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('configuration', $configuration);

        return $view;
    }

    public function fieldRowAction()
    {
        $configuration_id = $this->params()->fromQuery('configuration_id');
        $fieldData = $this->params()->fromQuery('field_data');

        $configuration = $this->api()->read('oaipmhharvester_configurations', $configuration_id)->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('configuration', $configuration);
        $view->setVariable('fieldData', $fieldData);

        return $view;
    }

    public function fieldEditSidebarAction()
    {
        $fieldData = $this->params()->fromQuery('field_data');
        $form = $this->getForm(MappingForm::class);
        $form->setData($fieldData);

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('form', $form);

        return $view;
    }
}
