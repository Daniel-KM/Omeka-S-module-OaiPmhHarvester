<?php declare(strict_types=1);

namespace OaiPmhHarvester\Job;

use DateTime;
use DateTimeZone;
use OaiPmhHarvester\Entity\Harvest as EntityHarvest;
use Omeka\Api\Representation\AbstractRepresentation;
use Omeka\Job\AbstractJob;
use SimpleXMLElement;

class Harvest extends AbstractJob
{
    /**
     * Date format for OAI-PMH requests.
     * Only use day-level granularity for maximum compatibility with
     * repositories.
     */
    const OAI_DATE_FORMAT = 'Y-m-d';

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
     * @var array
     */
    protected $harvestedResourceIdentifiers = [];

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

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
     * @var bool
     */
    protected $hasErr = false;

    /**
     * @var \OaiPmhHarvester\Api\Representation\HarvestRepresentation
     */
    protected $harvest;

    /**
     * @var string
     */
    protected $modeHarvest = EntityHarvest::MODE_SKIP;

    /**
     * @var bool
     */
    protected $storeRecord = false;

    /**
     * @var bool
     */
    protected $storeResponse = false;

    public function perform()
    {
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->harvesterMapManager = $services->get(\OaiPmhHarvester\OaiPmh\HarvesterMap\Manager::class);

        $args = $this->job->getArgs();

        // Early checks.

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

        // Loop all sets.

        $defaultArgs = $args;
        unset($defaultArgs['sets']);
        foreach ($sets as $set) {
            $this->processSet($defaultArgs + $set);
        }
    }

    protected function processSet(array $args)
    {
        $services = $this->getServiceLocator();

        $metadataPrefix = $args['metadata_prefix'] ?? null;
        $from = $args['from'] ?? null;
        $until = $args['until'] ?? null;
        $itemSetId = empty($args['item_set_id']) ? null : (int) $args['item_set_id'];
        $whitelist = $args['filters']['whitelist'] ?? [];
        $blacklist = $args['filters']['blacklist'] ?? [];

        $message = null;
        $stats = [
            'records' => null,
            'harvested' => 0,
            'whitelisted' => 0,
            'blacklisted' => 0,
            'skipped' => 0,
            'imported' => 0,
            'duplicated' => 0,
            'medias' => 0,
            'errors' => 0,
        ];

        $harvestData = [
            'o:job' => ['o:id' => $this->job->getId()],
            'o:undo_job' => null,
            'o-oai-pmh:message' => 'Harvesting started', // @translate
            'o-oai-pmh:entity_name' => $this->getArg('entity_name', 'items'),
            'o-oai-pmh:endpoint' => $args['endpoint'],
            'o:item_set' => ['o:id' => $args['item_set_id']],
            'o-oai-pmh:metadata_prefix' => $args['metadata_prefix'],
            'o-oai-pmh:mode_harvest' => $this->modeHarvest,
            'o-oai-pmh:from' => $from ? new DateTime($from, new DateTimeZone('UTC')) : null,
            'o-oai-pmh:until' => $until ? new DateTime($until, new DateTimeZone('UTC')) : null,
            'o-oai-pmh:set_spec' => $args['set_spec'],
            'o-oai-pmh:set_name' => $args['set_name'],
            'o-oai-pmh:set_description' => $args['set_description'] ?? null,
            'o-oai-pmh:has_err' => false,
            'o-oai-pmh:stats' => $stats,
        ];

        /** @var \OaiPmhHarvester\Api\Representation\HarvestRepresentation $harvest */
        $harvest = $this->api->create('oaipmhharvester_harvests', $harvestData)->getContent();
        $this->harvest = $harvest;

        $harvestId = $harvest->id();

        if ($from && $until) {
            $this->logger->notice(
                'Start harvesting {url}, format {format}, from {from} until {until}.', // @translate
                ['url' => $args['endpoint'], 'format' => $metadataPrefix, 'from' => $from, 'until' => $until]
            );
        } elseif ($from) {
            $this->logger->notice(
                'Start harvesting {url}, format {format}, from {from}.', // @translate
                ['url' => $args['endpoint'], 'format' => $metadataPrefix, 'from' => $from]
            );
        } elseif ($from && $until) {
            $this->logger->notice(
                'Start harvesting {url}, format {format}, until {until}.', // @translate
                ['url' => $args['endpoint'], 'format' => $metadataPrefix, 'until' => $until]
            );
        } else {
            $this->logger->notice(
                'Start harvesting {url}, format {format}.', // @translate
                ['url' => $args['endpoint'], 'format' => $metadataPrefix]
            );
        }

        /** @var \OaiPmhHarvester\OaiPmh\HarvesterMap\HarvesterMapInterface $harvesterMap */
        $harvesterMap = $this->harvesterMapManager->get($metadataPrefix);
        $harvesterMap->setOptions([
            'o:is_public' => !$services->get('Omeka\Settings')->get('default_to_private', false),
            // There may be multiple item sets in map, but not managed here for now.
            'o:item_set' => $itemSetId ? [['o:id' => $itemSetId]] : [],
        ]);

        $resumptionToken = false;
        $recordIndex = 0;
        $pageIndex = 0;
        do {
            ++$pageIndex;
            if ($this->shouldStop()) {
                $this->logger->notice(
                    'Results: total records = {total}, harvested = {harvested}, not in whitelist = {whitelisted}, blacklisted = {blacklisted}, skipped = {skipped}, imported = {imported}, duplicated = {duplicated}, medias = {medias}, errors = {errors}.', // @translate
                    [
                        'total' => $stats['records'] ?: '?',
                        'harvested' => $stats['harvested'],
                        'whitelisted' => $stats['whitelisted'],
                        'blacklisted' => $stats['blacklisted'],
                        'skipped' => $stats['skipped'],
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

            $records = $response->ListRecords;

            if (is_null($stats['records'])) {
                $stats['records'] = isset($response->ListRecords->resumptionToken)
                    ? (int) $records->resumptionToken['completeListSize']
                    : count($response->ListRecords->record);
            }

            $toInsert = [];
            /** @var \SimpleXMLElement $record */
            foreach ($records->record as $record) {
                ++$recordIndex;
                if ($this->storeXmlRecord) {
                    $this->storeXml($record, $pageIndex, $recordIndex);
                }
                if ($harvesterMap->isDeletedRecord($record)) {
                    continue;
                }

                ++$stats['harvested'];
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

                // The oai identifier is not part of the resource.
                // The oai identifier should not be included in the resource.
                // The oai identifier does not depend on the metadata prefix.
                // To make identifier really unique, the endpoint from the
                // harvest may be used.
                // A record can be mapped to multiple resources: cf. ead.
                $identifier = (string) $record->header->identifier;

                if ($identifier
                    && in_array($identifier, $this->harvestedResourceIdentifiers)
                ) {
                    // Only atomic values are managed. Records for other formats
                    // are duplicated.
                    $harvestedResourceIds = array_keys($this->harvestedResourceIdentifiers, $identifier, true);
                    if (count($harvestedResourceIds) === 1) {
                        $harvestedResourceId = reset($harvestedResourceIds);
                        switch ($this->modeHarvest) {
                            default:
                            case EntityHarvest::MODE_SKIP:
                                $this->logger->info(
                                    'The identifier was already imported as resource #{resource_id}. New data are skipped.', // @translate
                                    ['resource_id' => $harvestedResourceId]
                                );
                                ++$stats['skipped'];
                                continue 2;
                            case EntityHarvest::MODE_DUPLICATE:
                                $this->logger->info(
                                    'The identifier was already imported as resource #{resource_id}. A new resource is created.', // @translate
                                    ['resource_id' => $harvestedResourceId]
                                );
                                ++$stats['duplicated'];
                                break;
                        }
                    }
                }

                $toInsert[$identifier] = [];
                $resources = $harvesterMap->mapRecord($record);
                foreach ($resources as $resource) {
                    $toInsert[$identifier][] = $resource;
                    $stats['medias'] += !empty($resource['o:media']) ? count($resource['o:media']) : 0;
                    ++$stats['imported'];
                }
            }

            $totalCreated = $this->createItems($toInsert);
            $stats['errors'] += count($toInsert) - $totalCreated;

            $resumptionToken = isset($response->ListRecords->resumptionToken) && $response->ListRecords->resumptionToken !== ''
                ? (string) $response->ListRecords->resumptionToken
                : false;

            // Update job.
            $harvestData = [
                'o-oai-pmh:message' => 'Processing', // @translate
                'o-oai-pmh:has_err' => $this->hasErr,
                'o-oai-pmh:stats' => $stats,
            ];
            $this->api->update('oaipmhharvester_harvests', $harvestId, $harvestData);

            $this->logger->info(
                'Page #{page} processed: total records = {total}, harvested = {harvested}, not in whitelist = {whitelisted}, blacklisted = {blacklisted}, skipped = {skipped}, imported = {imported}, duplicated = {duplicated}, medias = {medias}, errors = {errors}.', // @translate
                [
                    'page' => $pageIndex,
                    'total' => $stats['records'] ?: '?',
                    'harvested' => $stats['harvested'],
                    'whitelisted' => $stats['whitelisted'],
                    'blacklisted' => $stats['blacklisted'],
                    'skipped' => $stats['skipped'],
                    'imported' => $stats['imported'],
                    'duplicated' => $stats['duplicated'],
                    'medias' => $stats['medias'],
                    'errors' => $stats['errors'],
                ]
            );

            sleep(self::REQUEST_WAIT);
        } while ($resumptionToken);

        // Update job.
        if (empty($message)) {
            $message = 'Harvest ended.'; // @translate
        }

        $harvestData = [
            'o-oai-pmh:message' => $message,
            'o-oai-pmh:has_err' => $this->hasErr,
            'o-oai-pmh:stats' => $stats,
        ];

        $this->api->update('oaipmhharvester_harvests', $harvestId, $harvestData);

        $this->logger->notice(
            'Results: total records = {total}, harvested = {harvested}, not in whitelist = {whitelisted}, blacklisted = {blacklisted}, skipped = {skipped}, imported = {imported}, duplicated = {duplicated}, medias = {medias}, errors = {errors}.', // @translate
            [
                'total' => $stats['records'] ?: '?',
                'harvested' => $stats['harvested'],
                'whitelisted' => $stats['whitelisted'],
                'blacklisted' => $stats['blacklisted'],
                'skipped' => $stats['skipped'],
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
     * Try to load XML from specified URL and handle network issues by retrying several times
     * @param string $url The URL to load
     * @param int $retry The maximum number of retries
     * @param int $timeToWaitBeforeRetry The initial wait time before the first retry. This time will be multiplied by 2 for each subsequent retry.
     * @return null|SimpleXMLElement Returns a SimpleXMLElement on success, or null on failure.
     */
    private function tryToLoadXml(string $url, int $retry = self::REQUEST_MAX_RETRY, int $timeToWaitBeforeRetry = self::REQUEST_WAIT * 3): ?SimpleXMLElement
    {
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
            if (count($resources)) {
                $identifierIds = [];
                foreach (array_chunk($resources, self::BATCH_CREATE_SIZE, true) as $chunk) {
                    $response = $this->api->batchCreate('items', $chunk, [], ['continueOnError' => true]);
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
                            '{count} resource created from oai record {identifier}: #{resource_ids}.', // @translate
                            ['count' => 1, 'identifier' => $identifier, 'resource_ids' => reset($identifierIds)]
                        );
                    } else {
                        $this->logger->info(
                            '{count} resources created from oai record {identifier}: #{resource_ids}.', // @translate
                            ['count' => $identifierTotal, 'identifier' => $identifier, 'resource_ids' => implode('#, ', $identifierIds)]
                        );
                    }
                } elseif ($identifierTotal && $identifierTotal !== count($resources)) {
                    $this->logger->warn(
                        'Only {count}/{total} resources created from oai record {identifier}: #{resource_ids}.', // @translate
                        ['count' => $identifierTotal, 'total' => count($resources) - $identifierTotal, 'identifier' => $identifier, 'resource_ids' => implode('#, ', $identifierIds)]
                    );
                } else {
                    $this->logger->warn(
                        'No resource created from oai record {identifier}.', // @translate
                        ['identifier' => $identifier]
                    );
                }
            } else {
                $this->logger->warn(
                    'No resource created from oai record {identifier}, according to its metadata.', // @translate
                    ['identifier' => $identifier]
                );
            }
        }
        return $total;
    }

    protected function createRollback(array $resources, $identifier)
    {
        if (empty($resources)) {
            return null;
        }

        $importEntities = [];
        foreach ($resources as $resource) {
            $importEntities[] = $this->buildImportEntity($resource, $identifier);
        }
        $this->api->batchCreate('oaipmhharvester_entities', $importEntities, [], ['continueOnError' => true]);
    }

    protected function buildImportEntity(AbstractRepresentation $resource, $identifier): array
    {
        return [
            'o-oai-pmh:harvest' => ['o:id' => $this->harvest->id()],
            'o-oai-pmh:entity_id' => $resource->id(),
            'o-oai-pmh:entity_name' => $this->getArg('entity_name', 'items'),
            'o-oai-pmh:identifier' => (string) $identifier,
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
            $resultSave = $dom->save($filepath);
            if (!$resultSave) {
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
