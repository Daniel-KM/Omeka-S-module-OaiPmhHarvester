<?php declare(strict_types=1);

namespace OaiPmhHarvester\Controller\Admin;

use Common\Stdlib\PsrMessage;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use OaiPmhHarvester\Form\HarvestForm;
use OaiPmhHarvester\Form\SetsForm;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Entity\Job;
use Omeka\Stdlib\Message;

class IndexController extends AbstractActionController
{
    /**
     * Main form to set the url.
     */
    public function indexAction()
    {
        /** @var \OaiPmhHarvester\Form\HarvestForm $form */
        $form = $this->getForm(HarvestForm::class);

        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromRoute();
            $hasError = !empty($params['has_error']);
            $post = $this->params()->fromPost();
            $step = $post['step'] ?? null;
            if (!$hasError && $step === 'harvest-repository') {
                $form->setData($post);
                if ($form->isValid()) {
                    $params = $this->params()->fromRoute();
                    $params['action'] = 'sets';
                    $params['prev_action'] = 'index';
                    return $this->forward()->dispatch(__CLASS__, $params);
                }
            }
            $this->messenger()->addFormErrors($form);
        }

        return new ViewModel([
            'form' => $form,
        ]);
    }

    /**
     * Prepares the sets view.
     */
    public function setsAction()
    {
        // Avoid direct access to the page.
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('admin/default', ['controller' => 'oai-pmh-harvester', 'action' => 'index']);
        }

        // Check if the post come from index or sets.
        $params = $this->params()->fromRoute();
        $post = $this->params()->fromPost();

        // TODO Check granularity early.

        $step = $post['step'] ?? 'harvest-repository';
        $prevAction = $params['prev_action'] ?? null;
        $hasError = !empty($params['has_error']);
        unset($post['step'], $params['prev_action'], $params['has_error']);

        if ($step === 'harvest-repository' || $prevAction === 'index') {
            /** @var \OaiPmhHarvester\Form\HarvestForm $form */
            $form = $this->getForm(HarvestForm::class);
            $form->setData($post);
            if (!$form->isValid()) {
                $params['action'] = 'index';
                $params['has_error'] = true;
                $this->messenger()->addFormErrors($form);
                return $this->forward()->dispatch(__CLASS__, $params);
            }
            $data = $form->getData();
        } elseif ($step === 'harvest-list-sets') {
            if ($hasError) {
                return $this->redirect()->toRoute('admin/default', ['controller' => 'oai-pmh-harvester', 'action' => 'index']);
            }
            // The first time, the check is already done.
            // The full check on the full form is done below.
            $data = $post;
        } else {
            return $this->redirect()->toRoute('admin/default', ['controller' => 'oai-pmh-harvester', 'action' => 'index']);
        }

        // Process Harvest form.
        // Most of checks are done via the form in the first step.

        $endpoint = $data['endpoint'];
        $harvestAllRecords = !empty($data['harvest_all_records']);
        $predefinedSets = $data['predefined_sets'] ?? [];
        // In the second form, predefined sets are hdden.
        if (!is_array($predefinedSets)) {
            $predefinedSets = @json_decode($predefinedSets, true) ?: [];
        }
        $data['predefined_sets'] = $predefinedSets;

        $storeXml = !empty($data['store_xml']);

        // TODO Move last checks to form.
        $optionsData = $this->dataFromEndpoint($endpoint, $harvestAllRecords, $predefinedSets, $storeXml);
        if (!empty($optionsData['message'])) {
            $this->messenger()->addError($optionsData['message']);
            $params['action'] = $optionsData['redirect'] ?? 'sets';
            $params['has_error'] = true;
            return $this->forward()->dispatch(__CLASS__, $params);
        }

        // TODO Add list of existing item sets, taking care of the metadata prefix. Or set it inside the select.

        $optionsData = [
            'step' => 'harvest-list-sets',
        ] + $data + $optionsData;

        // The form for sets is dynamic.
        /** @var \OaiPmhHarvester\Form\SetsForm $form */
        $form = $this->getForm(SetsForm::class, $optionsData)
            ->setAttribute('action', $this->url()->fromRoute('admin/default', ['controller' => 'oai-pmh-harvester', 'action' => 'sets']));
        $optionsData['predefined_sets'] = json_encode($predefinedSets, 320);
        $form
            ->setData($optionsData);

        if (!$predefinedSets && !empty($optionsData['sets']) && count($optionsData['sets']) !== $optionsData['total']) {
            $this->messenger()->addWarning('This repository has duplicate identifiers for sets, so they are not all displayed. You may warn the admin of the repository.'); // @translate
        }

        // Don't check validity if the previous form was the repository one.
        if ($prevAction === 'index') {
            return new ViewModel([
                'form' => $form,
                'endpoint' => $endpoint,
                'repositoryName' => $optionsData['repository_name'],
                'total' => $optionsData['total'],
                'harvestAllRecords' => $harvestAllRecords,
            ]);
        }

        $toHarvest = $this->setsToHarvestFromFormData($data);
        if (!$harvestAllRecords && !$predefinedSets && empty($toHarvest)) {
            $this->messenger()->addError('At least one repository should be selected.'); // @translate
            return new ViewModel([
                'form' => $form,
                'endpoint' => $endpoint,
                'repositoryName' => $optionsData['repository_name'],
                'total' => $optionsData['total'],
                'harvestAllRecords' => $harvestAllRecords,
            ]);
        }

        if ($form->isValid()) {
            $params['action'] = 'harvest';
            return $this->forward()->dispatch(__CLASS__, $params);
        }

        $this->messenger()->addFormErrors($form);
        $params['has_error'] = true;
        return new ViewModel([
            'form' => $form,
            'endpoint' => $endpoint,
            'repositoryName' => $optionsData['repository_name'],
            'total' => $optionsData['total'],
            'harvestAllRecords' => $harvestAllRecords,
        ]);
    }

    /**
     * Launch the harvest process.
     */
    public function harvestAction()
    {
        // Avoid direct access to the page.
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('admin/default', ['controller' => 'oai-pmh-harvester', 'action' => 'index']);
        }

        // Check if the post come from index or sets.
        $params = $this->params()->fromRoute();
        $post = $this->params()->fromPost();
        $step = $post['step'] ?? 'harvest-repository';

        if ($step !== 'harvest-list-sets') {
            $params['action'] = 'index';
            $params['has_error'] = true;
            return $this->forward()->dispatch(__CLASS__, $params);
        }

        // Pass a filtered post as params.
        $endpoint = $post['endpoint'];
        $harvestAllRecords = !empty($post['harvest_all_records']);
        $predefinedSets = $post['predefined_sets'] ?? [];
        // In the second form, predefined sets are hdden.
        if (!is_array($predefinedSets)) {
            $predefinedSets = @json_decode($predefinedSets, true) ?: [];
        }
        $optionsData = $this->dataFromEndpoint($endpoint, $harvestAllRecords, $predefinedSets);

        /** @var \OaiPmhHarvester\Form\SetsForm $form */
        $form = $this->getForm(SetsForm::class, $optionsData);

        $form->setData($post);
        if (!$form->isValid()) {
            $params['action'] = 'sets';
            $this->messenger()->addFormErrors($form);
            return $this->forward()->dispatch(__CLASS__, $params);
        }

        // Process List Sets form.
        $data = $form->getData();

        $from = $data['from'] ?? null;
        if ($from && !empty($data['from_time'])) {
            $from = $from . 'T' . $data['from_time'] . 'Z';
        }
        $until = $data['until'] ?? null;
        if ($until) {
            $until = $until . 'T' . (empty($until['until_time']) ? '23:59:59' : $data['until_time']) . 'Z';
        }

        $filters = [
            'whitelist' => $data['filters_whitelist'] ?? [],
            'blacklist' => $data['filters_blacklist'] ?? [],
        ];

        $message = new PsrMessage(
            $this->translate('Harvesting from endpoint {url}'), // @translate
            ['url' => $data['endpoint']]
        );
        $message .= ': ';

        $repositoryName = $data['repository_name'];
        $harvestAllRecords = !empty($data['harvest_all_records']);

        // Create or list item sets and create oai-pmh harvesting sets if needed.
        $itemSetDefault = ($data['item_set'] ?? 'none') ?: 'none';

        // TODO Append description of sets, if any.
        $sets = [];
        if ($harvestAllRecords) {
            $prefix = $data['namespace'][0];
            $message .= $repositoryName;
            $uniqueUri = $data['endpoint'] . '?verb=ListRecords&metadataPrefix=' . rawurlencode($prefix);
            $itemSet = $this->searchOrCreateItemSet($itemSetDefault, $uniqueUri, $repositoryName);
            $sets[''] = [
                'set_spec' => '',
                'set_name' => $repositoryName,
                'metadata_prefix' => $prefix,
                'item_set_id' => $itemSet ? $itemSet->id() : null,
            ];
        } else {
            $toHarvest = $this->setsToHarvestFromFormData($data);

            foreach (array_keys($toHarvest) as $setSpec) {
                $prefix = $toHarvest[$setSpec]['namespace'];
                $label = $toHarvest[$setSpec]['label'];
                $message .= sprintf(
                    $this->translate('%s as %s'), // @translate
                    $label,
                    $prefix
                ) . ' | ';
                $uniqueUri = $data['endpoint'] . '?verb=ListRecords&set=' . rawurlencode($setSpec) . '&metadataPrefix=' . rawurlencode($prefix);
                $itemSet = $this->searchOrCreateItemSet($itemSetDefault, $uniqueUri, $label);
                $sets[$setSpec] = [
                    'set_spec' => $setSpec,
                    'set_name' => $label,
                    'metadata_prefix' => $prefix,
                    'item_set_id' => $itemSet ? $itemSet->id() : null,
                ];
            }
        }

        $message = trim($message, ':| ') . '.';
        $this->messenger()->addSuccess($message);

        if ($from && $until) {
            $message = new PsrMessage(
                $this->translate('The harvesting will be limited to period from {from} until {until}.'), // @translate
                ['from' => $from, 'until' => $until]
            );
            $this->messenger()->addSuccess($message);
        } elseif ($from) {
            $message = new PsrMessage(
                $this->translate('The harvesting will be limited to period from {from}.'), // @translate
                ['from' => $from]
            );
            $this->messenger()->addSuccess($message);
        } elseif ($until) {
            $message = new PsrMessage(
                $this->translate('The harvesting will be limited to period until {until}.'), // @translate
                ['until' => $until]
            );
            $this->messenger()->addSuccess($message);
        }

        if ($filters['whitelist']) {
            $message = new PsrMessage(
                $this->translate('These whitelist filters are used: {list}.'), // @translate
                ['list' => implode(', ', $filters['whitelist'])]
            );
            $this->messenger()->addSuccess($message);
        }

        if ($filters['blacklist']) {
            $message = new PsrMessage(
                $this->translate('These blacklist filters are used: {list}.'), // @translate
                ['list' => implode(', ', $filters['blacklist'])]
            );
            $this->messenger()->addSuccess($message);
        }

        // Prepare the lists of harvests to process all of them in a single job.
        $args = [
            'repository_name' => $repositoryName,
            'endpoint' => $data['endpoint'],
            'from' => $from,
            'until' => $until,
            'page_start' => empty($data['page_start']) ? null : (int) $data['page_start'],
            'has_err' => false,
            'entity_name' => 'items',
            'filters' => $filters,
            'mapping' => empty($data['mapping']) ? null : $data['mapping'],
            'mode_harvest' => ($data['mode_harvest'] ?? 'skip') ?: 'skip',
            'mode_delete' => ($data['mode_delete'] ?? 'skip') ?: 'skip',
            // Already checked and set in sets, so just for info.
            'item_set' => $itemSetDefault,
            'store_xml' => $data['store_xml'] ?? [],
            'sets' => $sets,
        ];

        // For testing purpose.
        // Use synchronous dispatcher for quick testing purpose.
        $strategy = null;
        /*
        $strategy = $this->api()->read('vocabularies', 1)->getContent()->getServiceLocator()->get(\Omeka\Job\DispatchStrategy\Synchronous::class);
        */

        $job = $this->jobDispatcher()->dispatch(\OaiPmhHarvester\Job\Harvest::class, $args, $strategy);

        $urlPlugin = $this->url();
        // TODO Don't use PsrMessage for now to fix issues with Doctrine and inexisting file to remove.
        $message = new Message(
            'Harvesting started in background (job %1$s#%2$d%3$s, %4$slogs%3$s). This may take a while.', // @translate
            sprintf(
                '<a href="%s">',
                htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
            ),
            $job->getId(),
            '</a>',
            class_exists('Log\Module', false)
                ? sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                : sprintf('<a href="%1$s" target="_blank">', $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);

        return $this->redirect()->toRoute('admin/default', ['controller' => 'oai-pmh-harvester', 'action' => 'past-harvests']);
    }

    public function pastHarvestsAction()
    {
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $undoJobIds = [];
            foreach ($data['harvest_id'] ?? [] as $harvestId) {
                $undoJob = $this->undoHarvest($harvestId);
                if ($undoJob) {
                    $undoJobIds[] = $undoJob->getId();
                }
            }
            $this->messenger()->addSuccess(new PsrMessage(
                'Undo in progress in the following jobs: {job_ids}.', // @translate
                ['job_ids' => implode(', ', $undoJobIds)]
            ));
        }

        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery() + [
            'page' => $page,
            'sort_by' => $this->params()->fromQuery('sort_by', 'id'),
            'sort_order' => $this->params()->fromQuery('sort_order', 'desc'),
        ];
        $response = $this->api()->search('oaipmhharvester_harvests', $query);

        $this->paginator($response->getTotalResults(), $page);

        return new ViewModel([
            'harvests' => $response->getContent(),
        ]);
    }

    protected function undoHarvest($harvestId): ?Job
    {
        $harvestId = (int) $harvestId;
        if (!$harvestId) {
            return null;
        }

        /** @var \OaiPmhHarvester\Api\Representation\HarvestRepresentation $harvest */
        $harvest = $this->api()->read('oaipmhharvester_harvests', ['id' => $harvestId])->getContent();

        $args = ['harvestId' => $harvest->id()];
        $job = $this->jobDispatcher()->dispatch(\OaiPmhHarvester\Job\DeleteHarvestedEntities::class, $args);

        $this->api()->update('oaipmhharvester_harvests', $harvest->id(), [
            'o:undo_job' => ['o:id' => $job->getId() ],
        ]);

        return $job;
    }

    /**
     * Get data for the setsForm.
     *
     * The endpoint should be checked.
     */
    protected function dataFromEndpoint($endpoint, $harvestAllRecords, $predefinedSets, bool $storeXml = false): array
    {
        $harvestAllRecords = (bool) $harvestAllRecords;
        $hasPredefinedSets = !empty($predefinedSets);
        $result = [
            'repository_name' => '',
            'endpoint' => '',
            'harvest_all_records' => false,
            'predefined_sets' => $predefinedSets,
            'formats' => [],
            'favorite_format' => '',
            'sets' => [],
            'has_predefined_sets' => $hasPredefinedSets,
            'message' => null,
        ];

        if (!$endpoint) {
            $result['message'] = $this->translate('Missing endpoint.'); // @translate
            return $result;
        }

        $message = null;

        /** @var \OaiPmhHarvester\Mvc\Controller\Plugin\OaiPmhRepository $oaiPmhRepository */
        $oaiPmhRepository = $this->oaiPmhRepository($endpoint);
        $oaiPmhRepository->setStoreXml($storeXml);
        $repositoryName = $oaiPmhRepository->getRepositoryName()
            ?: $this->translate('[Untitled repository]'); // @translate

        $formats = $oaiPmhRepository->listOaiPmhFormats();

        // Set oai_dc and oai_dcterms first if available.
        if (isset($formats['oai_dcterms'])) {
            $formats = ['oai_dcterms' => 'oai_dcterms'] + $formats;
        }
        if (isset($formats['oai_dc'])) {
            $formats = ['oai_dc' => 'oai_dc'] + $formats;
        }

        $favoriteFormat = isset($formats['oai_dcterms']) ? 'oai_dcterms' : 'oai_dc';

        // TODO Move the next checks of oai-pmh sets to the helper.

        if ($hasPredefinedSets) {
            $originalPredefinedSets = $predefinedSets;
            foreach ($predefinedSets as $setSpec => $format) {
                if (!$setSpec) {
                    unset($predefinedSets[$setSpec]);
                } elseif (!$format) {
                    $predefinedSets[$setSpec] = $favoriteFormat;
                }
            }

            if (count($originalPredefinedSets) !== count($predefinedSets)) {
                $result['message'] = $this->translate('The sets you specified are not correctly formatted.'); // @translate
                $result['redirect'] = 'index';
                return $result;
            }

            // Check if all sets have a managed format.
            $checks = array_filter($formats, fn ($v, $k) => $v === $k, ARRAY_FILTER_USE_BOTH);
            $unmanaged = array_filter($predefinedSets, fn ($v) => !in_array($v, $checks));
            if ($unmanaged) {
                $result['message'] = (new PsrMessage(
                    'The following formats are not managed: {list}.', // @translate
                    ['list' => implode(', ', $unmanaged)]
                ))->setTranslator($this->translator());
                return $result;
            }
        }

        if ($harvestAllRecords) {
            $total = null;
            $sets = [];
        } else {
            $setsTotals = $oaiPmhRepository->listOaiPmhSets();
            $total = $setsTotals['total'];
            $sets = $predefinedSets ?: $setsTotals['sets'];
        }

        // TODO Normalize sets form with fieldsets and better names.
        return [
            'repository_name' => $repositoryName,
            'endpoint' => $endpoint,
            'harvest_all_records' => $harvestAllRecords,
            'predefined_sets' => $predefinedSets,
            'formats' => $formats,
            'favorite_format' => $favoriteFormat,
            'sets' => $sets,
            'has_predefined_sets' => $hasPredefinedSets,
            'total' => $total,
            'message' => $message,
        ];
    }

    /**
     * @param string|int $itemSetDefault An item set id, "new" or "none".
     * @param string $uri The URI to search in dcterms:isFormatOf (property 37)
     * @param string $title The title to use in case of creation
     * @return ItemSetRepresentation|null The ItemSetRepresentation of the found or
     *   created ItemSet, or null according to default item set.
     */
    protected function searchOrCreateItemSet(
        $itemSetDefault,
        string $uri,
        string $title
    ): ?ItemSetRepresentation {
        $itemSetDefault = (is_numeric($itemSetDefault) ? (int) $itemSetDefault : $itemSetDefault) ?: 'none';

        if (is_numeric($itemSetDefault)) {
            try {
                return $this->api()->read('item_sets', $itemSetDefault)->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return null;
            }
        } elseif ($itemSetDefault !== 'new') {
            return null;
        }

        $itemSet = $this->api()
            ->searchOne('item_sets', ['property' => [['property' => 37, 'type' => 'eq', 'text' => $uri]]])
            ->getContent();
        if ($itemSet) {
            return $itemSet;
        }

        $toCreate = [
            // dctype:Collection.
            'o:resource_class' => ['o:id' => 23],
            'dcterms:title' => [[
                '@value' => $title,
                'type' => 'literal',
                'property_id' => 1,
            ]],
            'dcterms:isFormatOf' => [[
                'type' => 'uri',
                'property_id' => 37,
                '@id' => $uri,
                'o:label' => 'OAI-PMH repository',
            ]],
        ];
        return $this->api()
            ->create('item_sets', $toCreate)
            ->getContent();
    }

    private function setsToHarvestFromFormData(array $formData): array
    {
        return array_filter(
            $formData,
            fn ($v) => is_array($v) && !empty($v['harvest'])
        );
    }
}
