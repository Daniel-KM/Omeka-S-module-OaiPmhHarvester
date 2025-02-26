<?php declare(strict_types=1);

namespace OaiPmhHarvester\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use OaiPmhHarvester\Form\ConfigurationAddForm;
use OaiPmhHarvester\Form\ConfigurationDuplicateForm;
use OaiPmhHarvester\Form\ConfigurationEditForm;
use OaiPmhHarvester\Converter\ConfigurableConverterInterface;
use Omeka\Form\ConfirmForm;

class ConfigurationController extends AbstractActionController
{
    public function browseAction()
    {
        $this->browse()->setDefaults('oaipmhharvester_configurations');

        $response = $this->api()->search('oaipmhharvester_configurations', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        $configurations = $response->getContent();

        $view = new ViewModel();
        $view->setVariable('configurations', $configurations);

        return $view;
    }

    public function showAction()
    {
        $id = $this->params('id');

        $configuration = $this->api()->read('oaipmhharvester_configurations', $id)->getContent();

        $view = new ViewModel;
        $view->setVariable('configuration', $configuration);

        return $view;
    }

    public function addAction()
    {
        $form = $this->getForm(ConfigurationAddForm::class);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                $response = $this->api($form)->create('oaipmhharvester_configurations', $formData);
                if ($response) {
                    $configuration = $response->getContent();
                    $this->messenger()->addSuccess('OAI-PMH configuration successfully created.'); // @translate

                    $converter = $configuration->converter();
                    if ($converter instanceof ConfigurableConverterInterface) {
                        return $this->redirect()->toRoute('admin/oaipmhharvester/configuration-id', ['id' => $configuration->id(), 'action' => 'edit']);
                    }

                    return $this->redirect()->toRoute('admin/oaipmhharvester/configuration');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function editAction()
    {
        $id = $this->params('id');

        $configuration = $this->api()->read('oaipmhharvester_configurations', $id)->getContent();
        $form = $this->getForm(ConfigurationEditForm::class, ['configuration' => $configuration]);

        $form->setData([
            'o:name' => $configuration->name(),
            'o:settings' => $configuration->settings(),
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                unset($formData['configurationeditform_csrf']);
                unset($formData['o:converter_name']);
                $response = $this->api($form)->update('oaipmhharvester_configurations', $id, $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('OAI-PMH configuration successfully updated.'); // @translate
                    return $this->redirect()->toRoute('admin/oaipmhharvester/configuration');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('configuration', $configuration);
        return $view;
    }

    public function duplicateAction()
    {
        $id = $this->params('id');

        $configuration = $this->api()->read('oaipmhharvester_configurations', $id)->getContent();
        $form = $this->getForm(ConfigurationDuplicateForm::class);

        $form->setData([
            'o:name' => $configuration->name(),
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                $data = [
                    'o:name' => $formData['o:name'],
                    'o:converter_name' => $configuration->converterName(),
                    'o:settings' => $configuration->settings(),
                ];

                $response = $this->api($form)->create('oaipmhharvester_configurations', $data);
                if ($response) {
                    $newConfiguration = $response->getContent();
                    $this->messenger()->addSuccess('OAI-PMH configuration successfully duplicated.'); // @translate

                    $converter = $newConfiguration->converter();
                    if ($converter instanceof ConfigurableConverterInterface) {
                        return $this->redirect()->toRoute('admin/oaipmhharvester/configuration-id', ['id' => $newConfiguration->id(), 'action' => 'edit']);
                    }

                    return $this->redirect()->toRoute('admin/oaipmhharvester/configuration');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('configuration', $configuration);

        return $view;
    }


    public function deleteConfirmAction()
    {
        $resource = $this->api()->read('oaipmhharvester_configurations', $this->params('id'))->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $resource);
        $view->setVariable('resourceLabel', 'configuration'); // @translate
        $view->setVariable('partialPath', 'oai-pmh-harvester/admin/configuration/show-details');
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('oaipmhharvester_configurations', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Configuration successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return $this->redirect()->toRoute('admin/oaipmhharvester/configuration', ['action' => 'browse']);
    }

    public function showDetailsAction()
    {
        $response = $this->api()->read('oaipmhharvester_configurations', $this->params('id'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('resource', $response->getContent());
        return $view;
    }
}
