<?php declare(strict_types=1);

namespace OaiPmhHarvester\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use OaiPmhHarvester\Form\SourceAddForm;
use OaiPmhHarvester\Form\SourceEditForm;
use OaiPmhHarvester\Form\SourceHarvestForm;
use OaiPmhHarvester\Job\HarvestSource;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\Message;

class SourceController extends AbstractActionController
{
    public function browseAction()
    {
        $this->browse()->setDefaults('oaipmhharvester_sources');

        $response = $this->api()->search('oaipmhharvester_sources', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        $sources = $response->getContent();

        $view = new ViewModel();
        $view->setVariable('sources', $sources);

        return $view;
    }

    public function addAction()
    {
        $form = $this->getForm(SourceAddForm::class);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();

                $baseUrl = $formData['o:base_url'];
                $repositoryName = $this->oaiPmhRepository()->getRepositoryName($baseUrl);
                if ($repositoryName) {
                    $data = array_merge($formData, [
                        'o:name' => $repositoryName,
                        'o:metadata_prefix' => 'oai_dc',
                        'o:converter_id' => 'oai_dc',
                    ]);
                    unset($data['csrf']);
                    $response = $this->api($form)->create('oaipmhharvester_sources', $data);
                    if ($response) {
                        $source = $response->getContent();
                        $this->messenger()->addSuccess('OAI-PMH source successfully created.'); // @translate
                        return $this->redirect()->toRoute('admin/oaipmhharvester/source-id', ['id' => $source->id(), 'action' => 'edit']);
                    }
                } else {
                    $this->messenger()->addError(sprintf('%s does not appear to be an OAI-PMH repository (retrieval of repository name failed)', $baseUrl));
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

        $source = $this->api()->read('oaipmhharvester_sources', $id)->getContent();
        $data = $source->jsonSerialize();

        $metadataFormats = $this->oaiPmhRepository()->listOaiPmhFormats($source->baseUrl());
        $metadataPrefixOptions = array_combine(array_keys($metadataFormats), array_keys($metadataFormats));

        $form = $this->getForm(SourceEditForm::class, ['metadataPrefixOptions' => $metadataPrefixOptions, 'source_id' => $id]);
        $form->setData([
            'o:name' => $source->name(),
            'o:configuration' => ['o:id' => $source->configuration()->id() ],
            'o:base_url' => $source->baseUrl(),
            'o:metadata_prefix' => $source->metadataPrefix(),
            'o:sets' => $source->sets(),
        ]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                unset($formData['csrf']);
                unset($formData['o:base_url']);
                $response = $this->api($form)->update('oaipmhharvester_sources', $id, $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('OAI-PMH source successfully updated.'); // @translate
                    return $this->redirect()->toRoute('admin/oaipmhharvester/source');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $resource = $this->api()->read('oaipmhharvester_sources', $this->params('id'))->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $resource);
        $view->setVariable('resourceLabel', 'source'); // @translate
        $view->setVariable('partialPath', 'oai-pmh-harvester/admin/source/show-details');
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('oaipmhharvester_sources', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Source successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return $this->redirect()->toRoute('admin/oaipmhharvester/source', ['action' => 'browse']);
    }

    public function showDetailsAction()
    {
        $response = $this->api()->read('oaipmhharvester_sources', $this->params('id'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('resource', $response->getContent());
        return $view;
    }

    public function listSetsAction()
    {
        $source = $this->api()->read('oaipmhharvester_sources', $this->params('id'))->getContent();
        $resumptionToken = $this->params()->fromQuery('resumptionToken');

        $document = $this->oaiPmhRepository()->listSets($source->baseUrl(), $resumptionToken);
        $response = [
            'sets' => $document->getSets(),
            'resumptionToken' => $document->getResumptionToken(),
        ];

        return new JsonModel($response);
    }

    public function harvestAction()
    {
        $form = $this->getForm(SourceHarvestForm::class);

        $source = $this->api()->read('oaipmhharvester_sources', $this->params('id'))->getContent();

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();

                $args = [
                    'source_id' => $source->id(),
                    'delete_all_items' => $formData['delete_all_items'] ? true : false,
                    'from' => $formData['from'],
                    'until' => $formData['until'],
                ];
                $job = $this->jobDispatcher()->dispatch(HarvestSource::class, $args);

                $message = new Message(
                    'Harvest started in a background job. %s', // @translate
                    sprintf(
                        '<a href="%s">%s</a>',
                        htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                        $this->translate('See job details')
                    )
                );
                $message->setEscapeHtml(false);
                $this->messenger()->addSuccess($message); // @translate

                $em = $source->getServiceLocator()->get('Omeka\EntityManager');
                $sourceEntity = $em->find('OaiPmhHarvester\Entity\Source', $source->id());
                $sourceEntity->getJobs()->add($job);
                $em->flush();

                return $this->redirect()->toRoute('admin/oaipmhharvester/source');
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('source', $source);
        return $view;
    }
}
