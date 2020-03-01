<?php
namespace OaiPmhHarvester\Controller\Admin;

use OaiPmhHarvester\Form\HarvestForm;
use OaiPmhHarvester\Form\SetsForm;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * A standard php server allows only 1000 fields, and there are three fields
     * by set (prefix, hidden, harvest).
     *
     * @var int
     */
    protected $maxListSets = 200;

    /**
     * Main form to set the url.
     */
    public function indexAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(HarvestForm::class);
        $view->form = $form;
        return $view;
    }

    /**
     * Prepares the sets view.
     */
    public function setsAction()
    {
        $post = $this->params()->fromPost();
        $endpoint = @$post['endpoint'];

        // Avoid direct acces to the page.
        if (empty($endpoint)) {
            return $this->redirect()->toRoute('admin/oaipmhharvester');
        }

        $url = $endpoint . '?verb=Identify';
        $response = @\simplexml_load_file($url);
        if (!$response) {
            $message = sprintf($this->translate('The endpoint "%s" does not return xml.'), $endpoint); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/oaipmhharvester');
        }

        $repositoryName = (string) $response->Identify->repositoryName ?: $this->translate('[Untitled repository]'); // @translate

        $formats = $this->listOaiPmhFormats($endpoint);
        if (empty($formats)) {
            $message = sprintf($this->translate('The endpoint "%s" does not manage any format.'), $endpoint); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/oaipmhharvester');
        }

        $sets = $this->listOaiPmhSets($endpoint);
        $total = $sets['total'];
        $sets = $sets['sets'];

        $options = [
            'endpoint' => $endpoint,
            'formats' => $formats,
            'sets' => $sets,
        ];
        $form = $this->getForm(SetsForm::class, $options);

        $view = new ViewModel;
        return $view
            ->setVariable('form', $form)
            ->setVariable('repositoryName', $repositoryName)
            ->setVariable('total', $total)
        ;
    }

    /**
     * Launch the harvest process.
     */
    public function harvestAction()
    {
        $post = $this->params()->fromPost();

        $filters = [];
        $filters['whitelist'] = $post['filters_whitelist'];
        $filters['blacklist'] = $post['filters_blacklist'];
        // This method fixes Windows and Apple copy/paste from a textarea input,
        // then explode it by line.
        foreach ($filters as &$filter) {
            $filter = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $filter))), 'strlen');
        }

        $message = sprintf($this->translate('Harvesting from "%s" sets:'), $post['endpoint']) // @translate
            . ' ';

        // List item sets and create oai-pmh harvesting sets.
        $sets = [];
        foreach ($post['namespace'] as $id => $prefix) {
            if ($post['harvest'][$id] == 'yes') {
                $message .= $post['setSpec'][$id] . ' as ' . $prefix . '|';
                $tocreate = [
                    ["dcterms:title" =>
                        ['@value' => $post['setSpec'][$id],
                            'type' => 'literal',
                            "property_id" => 1,
                        ],
                    ],
                ];
                $setId = $this->api()->create('item_sets', $tocreate, [], ['responseContent' => 'resource'])->getContent();
                $sets[$id] = [$post['setSpec'][$id], $prefix, $setId->getId()];
            }
        }

        $message = rtrim($message, '|');
        $this->messenger()->addSuccess($message);

        if ($filters['whitelist']) {
            $message = sprintf($this->translate('These whitelist filters are used: "%s".'), implode('", "', $filters['whitelist']));
            $this->messenger()->addSuccess($message);
        }

        if ($filters['blacklist']) {
            $message = sprintf($this->translate('These blacklist filters are used: "%s".'), implode('", "', $filters['blacklist']));
            $this->messenger()->addSuccess($message);
        }

        $dispatcher = $this->jobDispatcher();

        $urlHelper = $this->url();
        foreach ($sets as $setSpec => $set) {
            //  . "?metadataPrefix=" . $set[1] . "&verb=ListRecords&set=" . $setSpec
            $endpoint = $post['endpoint'];
            // TODO : job harvest / job item creation ?
            // TODO : toutes les propriétés (prefix, resumption, etc.)
            $harvestJson = [
                'comment' => 'Harvest ' . $set[0] . ' from ' . $endpoint,
                'endpoint' => $endpoint,
                'set_name' => $set[0],
                'set_spec' => $setSpec,
                'item_set_id' => $set[2],
                'has_err' => 0,
                'metadata_prefix' => $set[1],
                'resource_type' => 'items',
                'filters' => $filters,
            ];
            $job = $dispatcher->dispatch(\OaiPmhHarvester\Job\Harvest::class, $harvestJson);
            $this->messenger()->addSuccess('Harvesting ' . $set[0] . ' in Job ID ' . $job->getId());

            $message = new Message(
                vsprintf($this->translate('Harvesting %1$s started in background (job %2$s#%3$d%4$s, %5$slogs%4$s). This may take a while.'), // @translate
                [
                    $set['set_name'],
                    sprintf(
                        '<a href="%s">',
                        htmlspecialchars($urlHelper->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                    ),
                    $job->getId(),
                    '</a>',
                    sprintf(
                        '<a href="%s">',
                        htmlspecialchars($urlHelper->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))
                    ),
                ]
            ));
            $message->setEscapeHtml(false);
            $this->messenger()->addSuccess($message);
        }

        return $this->redirect()->toRoute('admin/oaipmhharvester/past-harvests');
    }

    public function pastHarvestsAction()
    {
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $undoJobIds = [];
            foreach ($data['jobs'] as $jobId) {
                $undoJob = $this->undoJob($jobId);
                $undoJobIds[] = $undoJob->getId();
            }
            $this->messenger()->addSuccess(sprintf(
                'Undo in progress in the following jobs: %s', // @translate
                implode(', ', $undoJobIds)
            ));
        }

        $view = new ViewModel;
        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery() + [
            'page' => $page,
            'sort_by' => $this->params()->fromQuery('sort_by', 'id'),
            'sort_order' => $this->params()->fromQuery('sort_order', 'desc'),
        ];
        $response = $this->api()->search('oaipmhharvester_harvests', $query);

        $this->paginator($response->getTotalResults(), $page);
        $view->setVariable('imports', $response->getContent());
        return $view;
    }

    protected function undoJob($jobId)
    {
        $response = $this->api()->search('oaipmhharvester_harvests', ['job_id' => $jobId]);
        $harvest = $response->getContent()[0];
        $dispatcher = $this->jobDispatcher();
        $job = $dispatcher->dispatch(\OaiPmhHarvester\Job\Undo::class, ['jobId' => $jobId]);
        $response = $this->api()->update(
            'oaipmhharvester_harvests',
            $harvest->id(),
            [
                'o:undo_job' => ['o:id' => $job->getId() ],
            ]
        );
        return $job;
    }

    /**
     * Prepare the list of formats of an OAI-PMH repository.
     *
     * @param string $endpoint
     * @return string[] Associative array of format prefix and name.
     */
    protected function listOaiPmhFormats($endpoint)
    {
        $formats = [];

        $url = $endpoint . '?verb=ListMetadataFormats';
        $response = @\simplexml_load_file($url);
        if ($response) {
            foreach ($response->ListMetadataFormats->metadataFormat as $format) {
                $prefix = (string) $format->metadataPrefix;
                if (in_array($prefix, ['oai_dc', 'oai_dcterms', 'dc', 'dcterms', 'mets'])) {
                    $formats[$prefix] = $prefix;
                } else {
                    $formats[$prefix] = sprintf($this->translate('%s [unmanaged]'), $prefix); // @translate
                }
            }
        }

        return $formats;
    }

    /**
     * Prepare the list of sets of an OAI-PMH repository.
     *
     * @param string $endpoint
     * @return array
     */
    protected function listOaiPmhSets($endpoint)
    {
        $sets = [];

        $baseListSetUrl = $endpoint . '?verb=ListSets';
        $resumptionToken = false;
        $totalSets = null;
        do {
            $url = $baseListSetUrl;
            if ($resumptionToken) {
                $url = $baseListSetUrl . '&resumptionToken=' . $resumptionToken;
            }

            /** @var \SimpleXMLElement $response */
            $response = \simplexml_load_file($url);
            if (!isset($response->ListSets)) {
                break;
            }

            if (empty($totalSets)) {
                $totalSets = (int) $response->ListSets->resumptionToken['completeListSize'];
            }

            foreach ($response->ListSets->set as $set) {
                $sets[(string) $set->setSpec] = (string) $set->setName;
                if (count($sets) >= $this->maxListSets) {
                    break 2;
                }
            }

            $resumptionToken = isset($response->ListSets->resumptionToken) && $response->ListSets->resumptionToken !== ''
                ? (string) $response->ListSets->resumptionToken
                : false;
        } while ($resumptionToken && count($sets) <= $this->maxListSets);

        return [
            'total' => $totalSets,
            'sets' => array_slice($sets, 0, $this->maxListSets),
        ];
    }
}