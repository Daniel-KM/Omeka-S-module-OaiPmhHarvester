<?php declare(strict_types=1);

namespace OaiPmhHarvester\Job;

use Omeka\Api\Representation\AbstractRepresentation;
use Omeka\Job\AbstractJob;

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

    public function perform()
    {
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->entityManager = $services->get('Omeka\EntityManager');

        /** @var \OaiPmhHarvester\OaiPmh\HarvesterMap\Manager $harvesterMapManager */
        $harvesterMapManager = $services->get(\OaiPmhHarvester\OaiPmh\HarvesterMap\Manager::class);

        $args = $this->job->getArgs();
        $itemSetId = empty($args['item_set_id']) ? null : (int) $args['item_set_id'];
        $whitelist = $args['filters']['whitelist'] ?? [];
        $blacklist = $args['filters']['blacklist'] ?? [];

        $message = null;
        $stats = [
            'records' => null, // @translate
            'harvested' => 0, // @translate
            'whitelisted' => 0, // @translate
            'blacklisted' => 0, // @translate
            'medias' => 0, // @translate
            'imported' => 0, // @translate
            'errors' => 0, // @translate
        ];

        $harvestData = [
            'o:job' => ['o:id' => $this->job->getId()],
            'o:undo_job' => null,
            'o-oai-pmh:message' => 'Harvesting started', // @translate
            'o-oai-pmh:entity_name' => $this->getArg('entity_name', 'items'),
            'o-oai-pmh:endpoint' => $args['endpoint'],
            'o:item_set' => ['o:id' => $args['item_set_id']],
            'o-oai-pmh:metadata_prefix' => $args['metadata_prefix'],
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

        $metadataPrefix = $args['metadata_prefix'] ?? null;
        if (!$metadataPrefix || !$harvesterMapManager->has($metadataPrefix)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The format "{format}" is not managed by the module currently.', // @translate
                ['format' => $metadataPrefix]
            );
            $this->api->update('oaipmhharvester_harvests', $harvestId, ['o-oai-pmh:has_err' => true]);
            return false;
        }

        // Check directory to store xmls.
        $storeXml = !empty($args['store_xml']) && is_array($args['store_xml']) ? $args['store_xml'] : [];
        $storeXmlResponse = in_array('page', $storeXml);
        $storeXmlRecord = in_array('record', $storeXml);
        if ($storeXmlResponse || $storeXmlRecord) {
            $config = $services->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            if (!is_dir($basePath) || !is_readable($basePath) || !is_writeable($basePath)) {
                $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
                $this->logger->err(
                    'The directory "{path}" is not writeable, so the oai-pmh xml responses are not storable.', // @translate
                    ['path' => $basePath]
                );
                return false;
            }
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

        $this->logger->notice(
            'Start harvesting {url}, format {format}.', // @translate
            ['url' => $args['endpoint'], 'format' => $metadataPrefix]
        );

        /** @var \OaiPmhHarvester\OaiPmh\HarvesterMap\HarvesterMapInterface $harvesterMap */
        $harvesterMap = $harvesterMapManager->get($metadataPrefix);
        $harvesterMap->setOptions([
            'o:is_public' => !$services->get('Omeka\Settings')->get('default_to_private', false),
            'o:item_set' => $itemSetId ? [['o:id' => $itemSetId]] : [],
        ]);

        $resumptionToken = false;
        $recordIndex = 0;
        $pageIndex = 0;
        do {
            ++$pageIndex;
            if ($this->shouldStop()) {
                $this->logger->notice(
                    'Results: total records = {total}, harvested = {harvested}, not in whitelist = {whitelisted}, blacklisted = {blacklisted}, imported = {imported}, medias = {medias}, errors = {errors}.', // @translate
                    [
                        'total' => $stats['records'],
                        'harvested' => $stats['harvested'],
                        'whitelisted' => $stats['whitelisted'],
                        'blacklisted' => $stats['blacklisted'],
                        'imported' => $stats['imported'],
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
            }

            /** @var \SimpleXMLElement $response */
            $response = simplexml_load_file($url);
            if (!$response) {
                $message = 'Server unavailable. Retrying.'; // @translate
                $this->logger->warn(
                    'Error: the harvester does not list records with url {url}. Retrying {count}/{total} times in {seconds} seconds', // @translate
                    ['url' => $url, 'count' => 1, 'total' => self::REQUEST_MAX_RETRY, 'seconds' => self::REQUEST_WAIT * 3]
                );

                sleep(self::REQUEST_WAIT * 3);
                $response = simplexml_load_file($url);
                if (!$response) {
                    $message = 'Server unavailable. Retrying.'; // @translate
                    $this->logger->warn(
                        'Error: the harvester does not list records with url {url}. Retrying {count}/{total} times in {seconds} seconds', // @translate
                        ['url' => $url, 'count' => 2, 'total' => self::REQUEST_MAX_RETRY, 'seconds' => self::REQUEST_WAIT * 6]
                    );

                    sleep(self::REQUEST_WAIT * 6);
                    $response = simplexml_load_file($url);
                    if (!$response) {
                        $message = 'Server unavailable. Retrying.'; // @translate
                        $this->logger->warn(
                            'Error: the harvester does not list records with url {url}. Retrying {count}/{total} times in {seconds} seconds', // @translate
                            ['url' => $url, 'count' => 3, 'total' => self::REQUEST_MAX_RETRY, 'seconds' => self::REQUEST_WAIT * 10]
                        );

                        sleep(self::REQUEST_WAIT * 10);
                        $response = simplexml_load_file($url);
                        if (!$response) {
                            $this->hasErr = true;
                            $message = 'Error.'; // @translate
                            $this->logger->err(
                                'Error: the harvester does not list records with url {url}.', // @translate
                                ['url' => $url]
                            );
                            break;
                        }
                    }
                }
            }

            // @todo Store the real response, not the domified one.
            if ($storeXmlResponse) {
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
                if ($storeXmlRecord) {
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
                'Page #{index} processed ({count}/{total} records, {count_2} errors).', // @translate
                ['index' => $pageIndex, 'count' => $recordIndex, 'total' => $stats['records'] ?: '?', 'count_2' => $stats['errors']]
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
            'Results: total records = {total}, harvested = {harvested}, not in whitelist = {whitelisted}, blacklisted = {blacklisted}, imported = {imported}, medias = {medias}, errors = {errors}.', // @translate
            [
                'total' => $stats['records'],
                'harvested' => $stats['harvested'],
                'whitelisted' => $stats['whitelisted'],
                'blacklisted' => $stats['blacklisted'],
                'imported' => $stats['imported'],
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
            'o:job' => ['o:id' => $this->job->getId()],
            'o-oai-pmh:entity_id' => $resource->id(),
            'o-oai-pmh:entity_name' => $this->getArg('entity_name', 'items'),
            'o-oai-pmh:identifier' => (string) $identifier,
        ];
    }

    protected function storeXml(\SimpleXMLElement $xml, int $pageIndex, ?int $recordIndex = null): void
    {
            $isRecord = $recordIndex !== null;
            $filename = $isRecord
                ? sprintf('%s.%04d.%04d.%07d.oaipmh.xml', $this->baseName, $this->harvest->id(), $pageIndex, $recordIndex)
                : sprintf('%s.%04d.%04d.oaipmh.xml', $this->baseName, $this->harvest->id(), $pageIndex);
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
