<?php declare(strict_types=1);

namespace OaiPmhHarvester\Job;

use DateTime;
use DateTimeZone;
use OaiPmhHarvester\Entity\Harvest as EntityHarvest;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractRepresentation;
use Omeka\Job\AbstractJob;
use SimpleXMLElement;

class Harvest extends AbstractJob
{
    /**
     * Date format for OAI-PMH requests.
     * Only use day-level granularity for maximum compatibility with
     * repositories.
     *
     * @var string
     */
    const OAI_DATE_FORMAT = 'Y-m-d';

    /**
     * @var int
     */
    const BATCH_CREATE_SIZE = 20;

    /**
     * Sleep between requests.
     *
     * @var int
     */
    const REQUEST_WAIT = 10;

    /**
     * @var int
     */
    const REQUEST_MAX_RETRY = 3;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \OaiPmhHarvester\OaiPmh\HarvesterMap\Manager
     */
    protected $harvesterMapManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $propertyIds = [];

    /**
     * @var string
     */
    protected $baseName;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @var \OaiPmhHarvester\Api\Representation\HarvestRepresentation
     */
    protected $harvest;

    /**
     * List of resource ids and oai identifiers.
     *
     * The resource type is "items" or "media", but not managed.
     *
     * @var array
     */
    protected $harvestedResourceIdentifiers = [];

    /**
     * @var bool
     */
    protected $hasErr = false;

    /**
     * @var int|string
     */
    protected $itemSetDefault;

    /**
     * @var string
     */
    protected $modeDelete = EntityHarvest::MODE_SKIP;

    /**
     * @var string
     */
    protected $modeHarvest = EntityHarvest::MODE_SKIP;

    /**
     * Oai endpoint.
     *
     * @var string
     */
    protected $oaiEndpoint;

    /**
     * @var bool
     */
    protected $storeRecord = false;

    /**
     * @var bool
     */
    protected $storeResponse = false;

    /**
     * @var array
     */
    protected $staticEntityIds = [];

    public function perform()
    {
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->harvesterMapManager = $services->get(\OaiPmhHarvester\OaiPmh\HarvesterMap\Manager::class);

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('oai-pmh/harvest/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $args = $this->job->getArgs();

        // Early checks.

        $this->oaiEndpoint = $args['endpoint'] ?? null;
        if (empty($this->oaiEndpoint)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'No endpoint defined.' // @translate
            );
        } elseif (!filter_var($args['endpoint'], FILTER_VALIDATE_URL)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The endpoint "{oai_endpoint}" is not a valid url.', // @translate
                ['oai_endpoint' => $this->oaiEndpoint]
            );
        }

        $from = empty($args['from']) ? null : (string) $args['from'];
        $until = empty($args['until']) ? null : (string) $args['until'];
        $iso8601Regex = '~\d\d\d\d-\d\d-\d\d(?:T\d\d:\d\d:\d\dZ)?~';
        if ($from && !preg_match($iso8601Regex, $from)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The date "from" {date} is invalid.', // @translate
                ['date' => $from]
            );
        }
        if ($until && !preg_match($iso8601Regex, $until)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The date "until" {date} is invalid.', // @translate
                ['date' => $until]
            );
        }
        if ($from && $until && $from > $until) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The date "from" {date_1} cannot be after the date "until" {date_2}.', // @translate
                ['date_1' => $from, 'date_2' => $until]
            );
        }

        $sets = $args['sets'] ?? [];
        if (!$sets) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'No set defined.' // @translate
            );
        } else {
            $unmanagedPrefixes = [];
            foreach ($sets as $set) {
                $metadataPrefix = $set['metadata_prefix'] ?? null;
                if (!$metadataPrefix || !$this->harvesterMapManager->has($metadataPrefix)) {
                    $unmanagedPrefixes[] = $metadataPrefix;
                }
            }
            if ($unmanagedPrefixes) {
                $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
                if (count($unmanagedPrefixes) <= 1) {
                    $this->logger->err(
                        'The format {format} is not managed by the module currently.', // @translate
                        ['format' => reset($unmanagedPrefixes)]
                    );
                } else {
                    $this->logger->err(
                        'The formats {formats} are not managed by the module currently.', // @translate
                        ['formats' => implode(', ', $unmanagedPrefixes)]
                    );
                }
            }
        }

        // Check harvest mode.
        $modeHarvests = [
            EntityHarvest::MODE_SKIP,
            EntityHarvest::MODE_APPEND,
            EntityHarvest::MODE_UPDATE,
            EntityHarvest::MODE_REPLACE,
            EntityHarvest::MODE_DUPLICATE,
        ];
        $this->modeHarvest = ($args['mode_harvest'] ?? EntityHarvest::MODE_SKIP) ?: EntityHarvest::MODE_SKIP;
        if (!in_array($this->modeHarvest, $modeHarvests)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The harvest mode "{mode}" is not supported.', // @translate
                ['mode' => $this->modeHarvest]
            );
        }

        // Check delete mode.
        $modeDeletes = [
            EntityHarvest::MODE_SKIP,
            EntityHarvest::MODE_DELETE,
            EntityHarvest::MODE_DELETE_FILTERED,
        ];
        $this->modeDelete = ($args['mode_delete'] ?? EntityHarvest::MODE_SKIP) ?: EntityHarvest::MODE_SKIP;
        if (!in_array($this->modeDelete, $modeDeletes)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The delete mode "{mode}" is not supported.', // @translate
                ['mode' => $this->modeDelete]
            );
        }

        // Check default item set.
        // Anyway, this option is useless, since item sets are created earlier,
        // before the job.
        $this->itemSetDefault = ($args['item_set'] ?? 'none') ?: 'none';
        if (is_numeric($this->itemSetDefault)) {
            $this->itemSetDefault = (int) $this->itemSetDefault;
            try {
                $this->api->read('item_sets', ['id' => $this->itemSetDefault ?: -1]);
            } catch (NotFoundException $e) {
                $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
                $this->logger->err(
                    'The item set "{item_set_id}" does not exist.', // @translate
                    ['item_set_id' => $this->itemSetDefault]
                );
            }
        } elseif (!in_array($this->itemSetDefault, ['none', 'new'])) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The option "{mode}" for item set is not supported.', // @translate
                ['mode' => $this->itemSetDefault]
            );
        }

        // Check directory to store xmls.
        $storeXml = !empty($args['store_xml']) && is_array($args['store_xml']) ? $args['store_xml'] : [];
        $this->storeXmlResponse = in_array('page', $storeXml);
        $this->storeXmlRecord = in_array('record', $storeXml);
        if ($this->storeXmlResponse || $this->storeXmlRecord) {
            $config = $services->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            if (!is_dir($basePath) || !is_readable($basePath) || !is_writeable($basePath)) {
                $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
                $this->logger->err(
                    'The directory "{path}" is not writeable, so the oai-pmh xml responses are not storable.', // @translate
                    ['path' => $basePath]
                );
            } else {
                $dir = $basePath . '/oai-pmh-harvest';
                if (!file_exists($dir)) {
                    mkdir($dir);
                }
                $this->basePath = $basePath;
                $this->baseName = $this->slugify(parse_url($args['endpoint'], PHP_URL_HOST));
                $this->baseUri = $config['file_store']['local']['base_uri'] ?: '';
                if (empty($this->baseUri)) {
                    $helpers = $services->get('ViewHelperManager');
                    $serverUrlHelper = $helpers->get('ServerUrl');
                    $basePathHelper = $helpers->get('BasePath');
                    $this->baseUri = $serverUrlHelper($basePathHelper('files'));
                }
            }
        }

        // Early return on any issue without creating the harvest entity.
        // Anyway, normally, the checks are done in controller.

        if ($this->job->getStatus() === \Omeka\Entity\Job::STATUS_ERROR) {
            return false;
        }

        $pageStart = empty($args['page_start']) ? null : (int) $args['page_start'];
        if ($pageStart) {
            $this->logger->info(
                'The process starts at page {page}.', // @translate
                ['page' => $pageStart]
            );
        }
        $args['page_start'] = $pageStart;

        // Get an array of all harvested items to avoid to check them each time.
        // Note: there may be issue in the table. The same oai identifier may be
        // imported or updated multiple times. The oai identifier may be used
        // for multiple resources (ead). But a resource has always a single oai
        // identifier.
        // Futhermore, keep only existing resource ids.
        /*
        $ids = $this->api->search('oaipmhharvester_entities', [], ['returnScalar' => 'entity_id'])->getContent();
        $identifiers = $this->api->search('oaipmhharvester_entities', [], ['returnScalar' => 'identifier'])->getContent();
        $this->harvestedResourceIdentifiers = array_combine($ids, $identifiers);
        unset($ids, $identifiers);
        */
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $qb
            ->select(
                'entity_id',
                'identifier',
            )
            ->from('oaipmhharvester_entity', 'oaipmhharvester_entity')
            ->innerJoin('oaipmhharvester_entity', 'resource', 'resource', 'resource.id = oaipmhharvester_entity.entity_id')
            ->orderBy('entity_id', 'asc')
        ;
        $this->harvestedResourceIdentifiers = $connection->executeQuery($qb)->fetchAllKeyValue();

        $this->propertyIds = $this->getPropertyIds();

        // Prepare data for entity manager.
        $this->staticEntityIds = [
            'job_id' => $this->job->getId(),
            'user_id' => $this->job->getOwner()->getId(),
            // There is no harvest here.
        ];

        // Loop all sets.

        $defaultArgs = $args;
        unset($defaultArgs['sets']);
        foreach ($sets as $set) {
            $setArgs = $defaultArgs + $set;
            $this->processSet($setArgs);
        }
    }

    protected function processSet(array $args)
    {
        $services = $this->getServiceLocator();

        $startTime =  microtime(true);

        $metadataPrefix = $args['metadata_prefix'] ?? null;
        $from = $args['from'] ?? null;
        $until = $args['until'] ?? null;
        $pageStart = empty($args['page_start']) ? null : (int) $args['page_start'];
        $itemSetId = empty($args['item_set_id']) ? null : (int) $args['item_set_id'];
        $whitelist = $args['filters']['whitelist'] ?? [];
        $blacklist = $args['filters']['blacklist'] ?? [];

        $message = null;

        // Note: the number of deleted resources is hard to know exactly because
        // there are many edge cases (added media, already deleted, etc.).
        $stats = [
            'records' => null, // @translate
            'harvested' => 0, // @translate
            'marked_deleted' => 0,
            'whitelisted' => 0, // @translate
            'blacklisted' => 0, // @translate
            'skipped' => 0, // @translate
            // Removed is the number of oai records marked deleted really
            // deleted, and deleted is the number of resources deleted.
            'removed' => 0, // @translate
            'deleted' => 0, // @translate
            'updated' => 0, // @translate
            // Processed means created, but there may be multiple created items.
            'processed' => 0, // @translate
            'duplicated' => 0, // @translate
            // Imported is the number of new items created, duplicated included.
            'imported' => 0, // @translate
            'medias' => 0, // @translate
            'errors' => 0, // @translate
            'duration' => null, // @translate
        ];

        // Only to keep track of translation.
        unset($stats['marked deleted']); // @translate

        $harvestData = [
            'o:job' => ['o:id' => $this->job->getId()],
            'o:undo_job' => null,
            'o-oai-pmh:message' => 'Harvesting started', // @translate
            'o-oai-pmh:entity_name' => $this->getArg('entity_name', 'items'),
            'o-oai-pmh:endpoint' => $args['endpoint'],
            'o:item_set' => ['o:id' => $args['item_set_id']],
            'o-oai-pmh:metadata_prefix' => $args['metadata_prefix'],
            'o-oai-pmh:mode_harvest' => $this->modeHarvest,
            'o-oai-pmh:mode_delete' => $this->modeDelete,
            'o-oai-pmh:from' => $from ? new DateTime($from, new DateTimeZone('UTC')) : null,
            'o-oai-pmh:until' => $until ? new DateTime($until, new DateTimeZone('UTC')) : null,
            'o-oai-pmh:set_spec' => $args['set_spec'],
            'o-oai-pmh:set_name' => $args['set_name'],
            'o-oai-pmh:set_description' => $args['set_description'] ?? null,
            'o-oai-pmh:has_err' => false,
            'o-oai-pmh:stats' => array_filter($stats),
        ];

        /** @var \OaiPmhHarvester\Api\Representation\HarvestRepresentation $harvest */
        $harvest = $this->api->create('oaipmhharvester_harvests', $harvestData)->getContent();
        $this->harvest = $harvest;

        $harvestId = $harvest->id();

        if ($from && $until) {
            $this->logger->notice(
                'Start harvesting {oai_url}, format {format}, from {from} until {until}.', // @translate
                ['oai_url' => $args['endpoint'], 'format' => $metadataPrefix, 'from' => $from, 'until' => $until]
            );
        } elseif ($from) {
            $this->logger->notice(
                'Start harvesting {oai_url}, format {format}, from {from}.', // @translate
                ['oai_url' => $args['endpoint'], 'format' => $metadataPrefix, 'from' => $from]
            );
        } elseif ($from && $until) {
            $this->logger->notice(
                'Start harvesting {oai_url}, format {format}, until {until}.', // @translate
                ['oai_url' => $args['endpoint'], 'format' => $metadataPrefix, 'until' => $until]
            );
        } else {
            $this->logger->notice(
                'Start harvesting {oai_url}, format {format}.', // @translate
                ['oai_url' => $args['endpoint'], 'format' => $metadataPrefix]
            );
        }

        /** @var \OaiPmhHarvester\OaiPmh\HarvesterMap\HarvesterMapInterface $harvesterMap */
        $harvesterMap = $this->harvesterMapManager->get($metadataPrefix);
        $harvesterMap->setOptions([
            'o:is_public' => !$services->get('Omeka\Settings')->get('default_to_private', false),
            // There may be multiple item sets in map, but not managed here for now.
            'o:item_set' => $itemSetId ? [['o:id' => $itemSetId]] : [],
        ]);

        $totalToInsertAll = 0;
        $totalCreatedAll = 0;

        $resumptionToken = false;
        $recordIndex = 0;
        $pageIndex = 0;
        $isPageSkipped = false;

        // Process a page.
        do {
            ++$pageIndex;

            $this->refreshEntityManager();

            if ($this->shouldStop()) {
                $this->logger->notice(
                    'Results: total records = {total}, harvested = {harvested}, marked deleted = {marked_deleted}, not in whitelist = {whitelisted}, blacklisted = {blacklisted}, skipped = {skipped}, removed = {removed}, updated = {updated}, processed = {processed}, deleted resources = {deleted}, imported resources = {imported}, duplicated = {duplicated}, medias = {medias}, errors = {errors}.', // @translate
                    [
                        'total' => $stats['records'] ?: '?',
                        'harvested' => $stats['harvested'],
                        'marked_deleted' => $stats['marked_deleted'],
                        'whitelisted' => $stats['whitelisted'],
                        'blacklisted' => $stats['blacklisted'],
                        'skipped' => $stats['skipped'],
                        'removed' => $stats['removed'],
                        'updated' => $stats['updated'],
                        'processed' => $stats['processed'],
                        'deleted' => $stats['deleted'],
                        'imported' => $stats['imported'],
                        'duplicated' => $stats['duplicated'],
                        'medias' => $stats['medias'],
                        'errors' => $stats['errors'],
                    ]
                );
                $this->logger->warn(
                    'The job was stopped.' // @translate
                );
                return false;
            }

            if ($resumptionToken) {
                $url = $args['endpoint'] . '?verb=ListRecords&resumptionToken=' . rawurlencode($resumptionToken);
            } else {
                $url = $args['endpoint'] . '?verb=ListRecords'
                    . (isset($args['set_spec']) && strlen((string) $args['set_spec']) ? '&set=' . rawurlencode($args['set_spec']) : '')
                    . '&metadataPrefix=' . rawurlencode($metadataPrefix);
                // Here, the from/until dates may be a date or a date with time.
                if ($from) {
                    $url .= '&from=' . rawurlencode($from);
                }
                if ($until) {
                    $url .= '&until=' . rawurlencode($until);
                }
            }

            $response = $this->tryToLoadXml($url);
            if (!$response) {
                $this->hasErr = true;
                $message = 'Error: Server unavailable.'; // @translate
                $this->logger->err(
                    'Error: the harvester does not list records with url {url}.', // @translate
                    ['url' => $url]
                );
                break;
            }

            // @todo Store the real response, not the domified one.
            if ($this->storeXmlResponse) {
                $this->storeXml($response, $pageIndex);
            }

            if (!$response->ListRecords) {
                $this->hasErr = true;
                $message = 'Error.'; // @translate
                $this->logger->err(
                    'The harvester does not list records with url {url}.', // @translate
                    ['url' => $url]
                );
                break;
            }

            // Get the resumption token early to manage the do/while loop.
            $resumptionToken = isset($response->ListRecords->resumptionToken) && $response->ListRecords->resumptionToken !== ''
                ? (string) $response->ListRecords->resumptionToken
                : false;

            $isPageSkipped = $pageStart && $pageIndex < $pageStart;
            if ($isPageSkipped) {
                continue;
            }

            $records = $response->ListRecords;

            if (is_null($stats['records'])) {
                $stats['records'] = $resumptionToken
                    ? (int) $records->resumptionToken['completeListSize']
                    : count($response->ListRecords->record);
            }

            $toInsert = [];
            /** @var \SimpleXMLElement $record */
            foreach ($records->record as $record) {
                ++$recordIndex;
                ++$stats['harvested'];

                if ($this->storeXmlRecord) {
                    $this->storeXml($record, $pageIndex, $recordIndex);
                }

                // The oai identifier is not part of the resource.
                // The oai identifier should not be included in the resource.
                // The oai identifier does not depend on the metadata prefix.
                // To make identifier really unique, the endpoint from the
                // harvest may be used.
                // A record can be mapped to multiple resources: cf. ead.
                $identifier = (string) $record->header->identifier;

                $isDeletedRecord = $harvesterMap->isDeletedRecord($record);
                if ($isDeletedRecord) {
                    ++$stats['marked_deleted'];

                    if (!in_array($this->modeDelete, [EntityHarvest::MODE_DELETE, EntityHarvest::MODE_DELETE_FILTERED])) {
                        ++$stats['skipped'];
                        $this->logger->info(
                            'The oai record {oai_id} was marked deleted and skipped.', // @translate
                            ['oai_id' => $identifier]
                        );
                        continue;
                    }

                    if ($identifier && $this->modeDelete === EntityHarvest::MODE_DELETE) {
                        ++ $stats['removed'];
                        $result = $this->deleteResources($identifier);
                        if (count($result)) {
                            $stats['deleted'] += count($result);
                            $this->logger->info(
                                'The oai record {oai_id} was marked deleted and imported resources were deleted: {resource_ids}.', // @translate
                                ['oai_id' => $identifier, 'resource_ids' => implode(', ', $result)]
                            );
                        }
                        continue;
                    }
                }

                if ($whitelist || $blacklist) {
                    // Use xml instead of string because some formats may use
                    // attributes for data.
                    $recordString = $record->asXML();
                    foreach ($whitelist as $string) {
                        if (mb_stripos($recordString, $string) === false) {
                            ++$stats['whitelisted'];
                            continue 2;
                        }
                    }
                    foreach ($blacklist as $string) {
                        if (mb_stripos($recordString, $string) !== false) {
                            ++$stats['blacklisted'];
                            continue 2;
                        }
                    }
                }

                if ($identifier
                    && $isDeletedRecord
                    && $this->modeDelete === EntityHarvest::MODE_DELETE_FILTERED
                ) {
                    ++ $stats['removed'];
                    $result = $this->deleteResources($identifier);
                    if (count($result)) {
                        $stats['deleted'] += count($result);
                        $this->logger->info(
                            'The oai record {oai_id} was marked deleted and imported resources were deleted: {resource_ids}.', // @translate
                            ['oai_id' => $identifier, 'resource_ids' => implode(', ', $result)]
                        );
                    }
                    continue;
                }

                $isToUpdate = false;
                if ($identifier
                    && in_array($identifier, $this->harvestedResourceIdentifiers)
                ) {
                    // Only atomic values are managed. Records for other formats
                    // are duplicated.
                    $harvestedResourceIds = array_keys($this->harvestedResourceIdentifiers, $identifier, true);
                    if (count($harvestedResourceIds) === 1) {
                        $harvestedResourceId = (int) reset($harvestedResourceIds);
                        switch ($this->modeHarvest) {
                            default:
                            case EntityHarvest::MODE_SKIP:
                                $this->logger->info(
                                    'The oai record {oai_id} was already imported as resource {resource_id}. New data are skipped.', // @translate
                                    ['oai_id' => $identifier, 'resource_id' => $harvestedResourceId]
                                );
                                ++$stats['skipped'];
                                continue 2;
                            case EntityHarvest::MODE_APPEND:
                            case EntityHarvest::MODE_UPDATE:
                            case EntityHarvest::MODE_REPLACE:
                                $isToUpdate = true;
                                break;
                            case EntityHarvest::MODE_DUPLICATE:
                                $this->logger->info(
                                    'The oai record {oai_id} was already imported as resource {resource_id}. A new resource is created.', // @translate
                                    ['oai_id' => $identifier, 'resource_id' => $harvestedResourceId]
                                );
                                ++$stats['duplicated'];
                                break;
                        }
                    }
                }

                if ($isToUpdate) {
                    // Update requires a single resource.
                    $resources = $harvesterMap->mapRecord($record);
                    if (!count($resources)) {
                        continue;
                    } elseif (count($resources) > 1) {
                        $this->logger->err(
                            'The oai record {oai_id} (resource {resource_id} cannot be updated, because it maps to multiple resources.', // @translate
                            ['oai_id' => $identifier, 'resource_id' => $harvestedResourceId]
                        );
                        // Error is counted below.
                        continue;
                    }
                    $result = $this->updateResource($harvestedResourceId, reset($resources));
                    if ($result === null) {
                        ++$stats['updated'];
                        $this->logger->info(
                            'The oai record {oai_id} was already imported as resource {resource_id}. There is no change.', // @translate
                            ['oai_id' => $identifier, 'resource_id' => $harvestedResourceId]
                        );
                    } elseif ($result) {
                        ++$stats['updated'];
                        switch ($this->modeHarvest) {
                            default:
                            case EntityHarvest::MODE_APPEND:
                                $this->logger->info(
                                    'The oai record {oai_id} was already imported as resource {resource_id}. The resource was completed.', // @translate
                                    ['oai_id' => $identifier, 'resource_id' => $harvestedResourceId]
                                );
                                break;
                            case EntityHarvest::MODE_UPDATE:
                                $this->logger->info(
                                    'The oai record {oai_id} was already imported as resource {resource_id}. The resource was updated.', // @translate
                                    ['oai_id' => $identifier, 'resource_id' => $harvestedResourceId]
                                );
                                break;
                            case EntityHarvest::MODE_REPLACE:
                                $this->logger->info(
                                    'The oai record {oai_id} was already imported as resource {resource_id}. The resource was replaced.', // @translate
                                    ['oai_id' => $identifier, 'resource_id' => $harvestedResourceId]
                                );
                                break;
                        }
                    } else {
                        $this->logger->warn(
                            'The oai record {oai_id} was already imported as resource {resource_id}. The resource cannot be updated.', // @translate
                            ['oai_id' => $identifier, 'resource_id' => $harvestedResourceId]
                        );
                    }
                } else {
                    $toInsert[$identifier] = [];
                    $resources = $harvesterMap->mapRecord($record);
                    foreach ($resources as $resource) {
                        $toInsert[$identifier][] = $resource;
                        $stats['medias'] += !empty($resource['o:media']) ? count($resource['o:media']) : 0;
                        ++$stats['imported'];
                    }
                    ++$stats['processed'];
                }
            }

            // Messages are already logged when the total is lower.
            $totalCreated = $this->createItems($toInsert);

            $totalToInsertAll += count($toInsert);
            $totalCreatedAll += $totalCreated;

            $stats['errors'] = $stats['harvested']
                - $stats['whitelisted']
                - $stats['blacklisted']
                - $stats['skipped']
                // $stats['deleted'] is not an atomic value, so use removed.
                - $stats['removed']
                - $stats['updated']
                // $stats['imported'] is not an atomic value, so use processed.
                - $stats['processed']
                + (count($toInsert) - $totalCreated);

            // Update job.
            $stats['duration'] = (int) (microtime(true) - $startTime);
            $harvestData = [
                'o-oai-pmh:message' => 'Processing', // @translate
                'o-oai-pmh:has_err' => $this->hasErr,
                'o-oai-pmh:stats' => array_filter($stats),
            ];
            try {
                $this->api->update('oaipmhharvester_harvests', $harvestId, $harvestData);
            } catch (\Exception $e) {
                // Don't fail here, because this is only information and to
                // harvest is long.
            }

            $this->logger->info(
                'Page #{page} processed: total records = {total}, harvested = {harvested}, marked deleted = {marked_deleted}, not in whitelist = {whitelisted}, blacklisted = {blacklisted}, skipped = {skipped}, removed = {removed}, updated resources = {updated}, processed = {processed}, deleted resources = {deleted}, imported resources = {imported}, duplicated = {duplicated}, medias = {medias}, errors = {errors}.', // @translate
                [
                    'page' => $pageIndex,
                    'total' => $stats['records'] ?: '?',
                    'harvested' => $stats['harvested'],
                    'marked_deleted' => $stats['marked_deleted'],
                    'whitelisted' => $stats['whitelisted'],
                    'blacklisted' => $stats['blacklisted'],
                    'skipped' => $stats['skipped'],
                    'removed' => $stats['removed'],
                    'updated' => $stats['updated'],
                    'processed' => $stats['processed'],
                    'deleted' => $stats['deleted'],
                    'imported' => $stats['imported'],
                    'duplicated' => $stats['duplicated'],
                    'medias' => $stats['medias'],
                    'errors' => $stats['errors'],
                ]
            );

            // Avoid memory issue.
            $this->refreshEntityManager();

            sleep(self::REQUEST_WAIT);
        } while ($resumptionToken || $isPageSkipped);

        // Update job.
        if (empty($message)) {
            $message = 'Harvest ended.'; // @translate
        }

        $stats['errors'] = $stats['harvested']
            - $stats['whitelisted']
            - $stats['blacklisted']
            - $stats['skipped']
            - $stats['removed']
            - $stats['updated']
            - $stats['processed']
            + ($totalToInsertAll - $totalCreatedAll);

        $stats['duration'] = (int) (microtime(true) - $startTime);
        $harvestData = [
            'o-oai-pmh:message' => $message,
            'o-oai-pmh:has_err' => $this->hasErr,
            'o-oai-pmh:stats' => array_filter($stats),
        ];

        $this->api->update('oaipmhharvester_harvests', $harvestId, $harvestData);

        // Avoid memory issue.
        $this->refreshEntityManager();

        $this->logger->notice(
            'Results: total records = {total}, harvested = {harvested}, marked deleted = {marked_deleted}, not in whitelist = {whitelisted}, blacklisted = {blacklisted}, skipped = {skipped}, removed = {removed}, updated = {updated}, processed = {processed}, deleted resources = {deleted}, imported resources = {imported}, duplicated = {duplicated}, medias = {medias}, errors = {errors}.', // @translate
            [
                'total' => $stats['records'] ?: '?',
                'harvested' => $stats['harvested'],
                'marked_deleted' => $stats['marked_deleted'],
                'whitelisted' => $stats['whitelisted'],
                'blacklisted' => $stats['blacklisted'],
                'skipped' => $stats['skipped'],
                'removed' => $stats['removed'],
                'updated' => $stats['updated'],
                'processed' => $stats['processed'],
                'deleted' => $stats['deleted'],
                'imported' => $stats['imported'],
                'duplicated' => $stats['duplicated'],
                'medias' => $stats['medias'],
                'errors' => $stats['errors'],
            ]
        );

        if ($stats['medias']) {
            $this->logger->notice(
                'Imports of medias should be checked separately.' // @translate
            );
        }

        if ($stats['errors']) {
            $this->logger->err(
                'Some records were not imported, probably related to issue on media. You may check the main logs.' // @translate
            );
        }
    }

    /**
     * Try to load XML from specified URL and handle network issues by retrying
     * several times.
     *
     * @param string $url The URL to load
     * @param int $retry The maximum number of retries
     * @param int $timeToWaitBeforeRetry The initial wait time before the first
     *   retry. This time will be multiplied by 2 for each subsequent retry.
     * @return null|SimpleXMLElement Returns a SimpleXMLElement on success, or
     *   null on failure.
     */
    protected function tryToLoadXml(
        string $url,
        int $retry = self::REQUEST_MAX_RETRY,
        int $timeToWaitBeforeRetry = self::REQUEST_WAIT * 3
    ): ?SimpleXMLElement {
        /** @var \SimpleXMLElement $response */
        $response = simplexml_load_file($url);
        if (!$response && $retry > 0) {
            $retry -= 1;
            $this->logger->warn(
                'Error: the harvester does not list records with url {url}. Retrying {count}/{total} times in {seconds} seconds', // @translate
                ['url' => $url, 'count' => self::REQUEST_MAX_RETRY - $retry, 'total' => self::REQUEST_MAX_RETRY, 'seconds' => self::REQUEST_WAIT * 3]
            );
            sleep($timeToWaitBeforeRetry);
            $response = $this->tryToLoadXml($url, $retry, $timeToWaitBeforeRetry * 2);
        }
        return $response;
    }

    /**
     * @param array $toCreate Array of array with resources related to each
     *   record source identifier in order to store the identifier when a record
     *   create multiple resources.
     */
    protected function createItems(array $toCreate): int
    {
        // TODO The length should be related to the size of the repository output?
        $total = 0;
        $getId = fn ($v) => $v->id();
        foreach ($toCreate as $identifier => $resources) {
            // Sometime, the identifier is a number.
            $identifier = (string) $identifier;
            if (count($resources)) {
                $identifierIds = [];
                foreach (array_chunk($resources, self::BATCH_CREATE_SIZE, true) as $chunk) {
                    $this->refreshEntityManager();
                    $response = $this->api->batchCreate('items', $chunk, [], ['continueOnError' => false]);
                    // TODO The batch create does not return the total of results in Omeka 3.
                    // $totalResults = $response->getTotalResults();
                    $currentResults = $response->getContent();
                    $total += count($currentResults);
                    $identifierIds = array_merge($identifierIds, array_map($getId, array_values($currentResults)));
                    $this->createRollback($currentResults, $identifier);
                }
                $identifierTotal = count($identifierIds);
                if ($identifierTotal === count($resources)) {
                    if ($identifierTotal === 1) {
                        $this->logger->info(
                            '{count} resource created from oai record {oai_id}: {resource_id}.', // @translate
                            ['count' => 1, 'oai_id' => $identifier, 'resource_id' => reset($identifierIds)]
                        );
                    } else {
                        $this->logger->info(
                            '{count} resources created from oai record {oai_id}: {resource_ids}.', // @translate
                            ['count' => $identifierTotal, 'oai_id' => $identifier, 'resource_ids' => implode(', ', $identifierIds)]
                        );
                    }
                } elseif ($identifierTotal && $identifierTotal !== count($resources)) {
                    $this->logger->warn(
                        'Only {count}/{total} resources created from oai record {oai_id}: {resource_ids}.', // @translate
                        ['count' => $identifierTotal, 'total' => count($resources) - $identifierTotal, 'oai_id' => $identifier, 'resource_ids' => implode(', ', $identifierIds)]
                    );
                } else {
                    $this->logger->warn(
                        'No resource created from oai record {oai_id}.', // @translate
                        ['oai_id' => $identifier]
                    );
                }
            } else {
                $this->logger->warn(
                    'No resource created from oai record {oai_id}, according to its metadata.', // @translate
                    ['oai_id' => $identifier]
                );
            }
        }
        return $total;
    }

    protected function updateResource(int $resourceId, array $resource): ?bool
    {
        // The id is already checked.
        $existingResource = $this->api->read('resources', $resourceId)->getContent()->jsonSerialize();
        $updatedResource = $existingResource;
        switch ($this->modeHarvest) {
            default:
            case EntityHarvest::MODE_APPEND:
                // The function array_unique() is not fully working here,
                // because the existing values have specific keys.
                // Deduplication is done outside (see modules BulkEdit or EasyAdmin).
                // Else see the process of the modules BulkImport or CsvImport.
                foreach (array_filter(array_intersect_key($resource, $this->propertyIds)) as $term => $values) {
                    $updatedResource[$term] = empty($existingResource[$term])
                        ? $values
                        : array_unique(array_merge(array_values($existingResource[$term]), array_values($values)));
                }
                break;
            case EntityHarvest::MODE_UPDATE:
                $updatedResource = array_replace(
                    $existingResource,
                    array_filter(array_intersect_key($resource, $this->propertyIds))
                );
                break;
            case EntityHarvest::MODE_REPLACE:
                $updatedResource = array_diff_key($existingResource, $this->propertyIds)
                    + array_filter(array_intersect_key($resource, $this->propertyIds));
                break;
        }

        // TODO Improve the comparison between existing resource and updated resource.
        if ($existingResource === $updatedResource) {
            return null;
        }

        try {
            $this->api->update('items', $resourceId, $updatedResource, [], ['isPartial' => true]);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Delete resources from a harvested record identifier.
     *
     * @param string $identifier
     * @return array The deleted resource ids from previously harvested and
     * imported records with the identifier that were deleted (items and media).
     */
    protected function deleteResources(string $identifier): array
    {
        $resourceIds = array_keys($this->harvestedResourceIdentifiers, $identifier);
        if (!count($resourceIds)) {
            return [];
        }

        // Some resource may have been deleted.
        // Be sure this is not an empty array, else everything will be deleted..
        $resourceIds = array_values(array_unique(array_filter(array_map('intval', $resourceIds))));

        // For now, only items can be imported, so deleted. Media will be
        // deleted automatically with the items.
        if (count($resourceIds)) {
            $this->api->batchDelete('items', $resourceIds);
            $this->api->batchDelete('media', $resourceIds);
        }

        // The right way is to keep track of deleted records by adding a column
        // "deleted" for the current harvest. But it is heavy and not really
        // useful in real use cases.
        // TODO Do we need to remove info about deleted harvested records from previous harvests? Or to keep track of deleted entities?
        $harvestEntityIds = $this->api
            ->search(
                'oaipmhharvester_entities',
                ['identifier' => $identifier],
                ['returnScalar' => 'id']
            )
            ->getContent();

        if (count($harvestEntityIds)) {
            $this->api->batchDelete('oaipmhharvester_entities', array_keys($harvestEntityIds));
        }

        return $resourceIds;
    }

    protected function createRollback(array $resources, string $identifier)
    {
        if (empty($resources)) {
            return null;
        }

        $importEntities = [];
        foreach ($resources as $resource) {
            $importEntities[] = $this->buildImportEntity($resource, $identifier);
        }

        $this->api->batchCreate('oaipmhharvester_entities', $importEntities, [], ['continueOnError' => false]);
        $this->refreshEntityManager();
    }

    /**
     * The resource is always an item for now.
     */
    protected function buildImportEntity(AbstractRepresentation $resource, string $identifier): array
    {
        return [
            'o-oai-pmh:harvest' => ['o:id' => $this->harvest->id()],
            'o-oai-pmh:entity_id' => $resource->id(),
            'o-oai-pmh:entity_name' => $resource->resourceName(),
            'o-oai-pmh:identifier' => $identifier,
        ];
    }

    protected function storeXml(\SimpleXMLElement $xml, int $pageIndex, ?int $recordIndex = null): void
    {
        $isRecord = $recordIndex !== null;
        $filename = $isRecord
            ? sprintf('%s.h%04d.p%04d.r%07d.oaipmh.xml', $this->baseName, $this->harvest->id(), $pageIndex, $recordIndex)
            : sprintf('%s.h%04d.p%04d.oaipmh.xml', $this->baseName, $this->harvest->id(), $pageIndex);
        $filepath = $this->basePath . '/oai-pmh-harvest/' . $filename;
        // dom_import_simplexml($response);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $result = $dom->save($filepath);
        if (!$result) {
            $isRecord
                ? $this->logger->err(
                    'Unable to store xml for page #{page}, record #{index}.', // @translate
                    ['page' => $pageIndex, 'index' => $recordIndex]
                )
                : $this->logger->err(
                    'Unable to store xml for page #{page}.', // @translate
                    ['page' => $pageIndex]
                );
        } else {
            $isRecord
                ? $this->logger->info(
                    'Page #{page}: the xml record {index} was stored as {url}.', // @translate
                    ['page' => $pageIndex, 'index' => $recordIndex, 'url' => $this->baseUri . '/oai-pmh-harvest/' . $filename]
                )
                : $this->logger->info(
                    'The xml response #{page} was stored as {url}.', // @translate
                    ['page' => $pageIndex, 'url' => $this->baseUri . '/oai-pmh-harvest/' . $filename]
                );
        }
    }

    protected function refreshEntityManager(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();

        $user = $this->entityManager->find(\Omeka\Entity\User::class, $this->staticEntityIds['user_id']);
        if (!$this->entityManager->contains($user)) {
            $this->entityManager->persist($user);
            // $this->getServiceLocator()->get('Omeka\AuthenticationService')->setIdentity($user);
        }

        $this->job = $this->entityManager->find(\Omeka\Entity\Job::class, $this->staticEntityIds['job_id']);
        if (!$this->entityManager->contains($this->job)) {
            $this->job->setOwner($user);
            $this->entityManager->persist($this->job);
        }
    }

    /**
     * Get all property ids by term.
     *
     * @return array Associative array of ids by term.
     *
     * @todo Use \Common\Stdlib\EasyMeta.
     */
    protected function getPropertyIds(): array
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                'property.id AS id',
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id'
            )
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('property.id', 'asc')
            ->addGroupBy('property.id')
        ;
        return array_map('intval', $connection->executeQuery($qb)->fetchAllKeyValue());
    }

    /**
     * Transform the given string into a valid URL slug
     *
     * Copy from \Omeka\Api\Adapter\SiteSlugTrait::slugify().
     */
    protected function slugify(string $input): string
    {
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $slug = $transliterator->transliterate($input);
        } elseif (extension_loaded('iconv')) {
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        } else {
            $slug = $input;
        }
        $slug = mb_strtolower($slug, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9-]+/u', '-', $slug);
        $slug = preg_replace('/-{2,}/', '-', $slug);
        $slug = preg_replace('/-*$/', '', $slug);
        return $slug;
    }
}
