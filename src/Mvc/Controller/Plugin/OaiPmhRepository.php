<?php declare(strict_types=1);

namespace OaiPmhHarvester\Mvc\Controller\Plugin;

use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Mvc\I18n\Translator;
use OaiPmhHarvester\OaiPmh\HarvesterMap\Manager as HarvesterMapManager;

/**
 * Get infos about an OAI-PMH repository.
 */
class OaiPmhRepository extends AbstractPlugin
{
    /**
     * @var \OaiPmhHarvester\OaiPmh\HarvesterMap\Manager
     */
    protected $harvesterMapManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var bool
     */
    protected $isStoreXml = false;

    /**
     * List of managed metadata prefixes.
     *
     * The order is used to set the default format in the second form.
     * Full Dublin Core is preferred.
     *
     * @var array
     */
    protected $managedMetadataPrefixes = [];

    /**
     * @var int
     */
    protected $maxListSets = 1000;

    public function __construct(
        HarvesterMapManager $harvesterMapManager,
        Logger $logger,
        Translator $translator,
        string $basePath,
        string $baseUri
    ) {
        $this->harvesterMapManager = $harvesterMapManager;
        $this->logger = $logger;
        $this->translator = $translator;
        $this->managedMetadataPrefixes = $harvesterMapManager->getRegisteredNames();
        $this->basePath = $basePath;
        $this->baseUri = $baseUri;
    }

    /**
     * Prepare the helper.
     *
     * It does not use http client, but direct simplexml_load_file().
     */
    public function __invoke(?string $endpoint = null): self
    {
        if ($endpoint !== null) {
            $this->endpoint = $endpoint;
        }
        return $this;
    }

    public function getTranslator(): Translator
    {
        return $this->translator;
    }

    public function getStoreXml(): bool
    {
        return $this->isStoreXml;
    }

    public function setStoreXml(bool $storeXml): self
    {
        $this->isStoreXml = $storeXml;
        return $this;
    }

    public function listManagedPrefixes(): array
    {
        return $this->managedMetadataPrefixes;
    }

    public function hasNoQueryAndNoFragment(?string $endpoint = null): bool
    {
        $endpoint ??= $this->endpoint;
        if (!$endpoint) {
            return false;
        }
        return $endpoint
            && strpos($endpoint, '?') === false
            && strpos($endpoint, '#') === false;
    }

    public function isXmlEndpoint(?string $endpoint = null): bool
    {
        $endpoint ??= $this->endpoint;
        if (!$endpoint) {
            return false;
        }

        $url = $endpoint . '?verb=Identify';
        $response = @\simplexml_load_file($url);
        return (bool) $response;
    }

    public function hasOaiPmhManagedFormats(?string $endpoint = null): bool
    {
        $endpoint ??= $this->endpoint;
        if (!$endpoint) {
            return false;
        }
        return (bool) $this->listOaiPmhFormats($endpoint);
    }

    public function getRepositoryName(?string $endpoint = null): ?string
    {
        $endpoint ??= $this->endpoint;
        if (!$endpoint) {
            return null;
        }
        $url = $endpoint . '?verb=Identify';
        $response = @\simplexml_load_file($url);
        if (!$response) {
            return null;
        }
        return (string) $response->Identify->repositoryName;
    }

    /**
     * Prepare the list of formats of an OAI-PMH repository.
     *
     * @return string[] Associative array of format prefix and name.
     */
    public function listOaiPmhFormats(?string $endpoint = null): array
    {
        $endpoint ??= $this->endpoint;
        if (!$endpoint) {
            return [];
        }

        $formats = [];

        $url = $endpoint . '?verb=ListMetadataFormats';
        $response = @\simplexml_load_file($url);
        if ($response) {
            if ($response && $this->isStoreXml) {
                $this->storeXml($response, 'ListMetadataFormats');
            }
            foreach ($response->ListMetadataFormats->metadataFormat as $format) {
                $prefix = (string) $format->metadataPrefix;
                if (in_array($prefix, $this->managedMetadataPrefixes)) {
                    $formats[$prefix] = $prefix;
                } else {
                    $formats[$prefix] = sprintf($this->translator->translate('%s [unmanaged]'), $prefix); // @translate
                }
            }
        }

        return $formats;
    }

    /**
     * Prepare the list of sets of an OAI-PMH repository.
     */
    public function listOaiPmhSets(?string $endpoint = null): array
    {
        $endpoint ??= $this->endpoint;
        if (!$endpoint) {
            return [];
        }

        $sets = [];

        $baseListSetUrl = $endpoint . '?verb=ListSets';
        $resumptionToken = false;
        $totalSets = null;
        $index = 0;
        do {
            ++$index;
            $url = $baseListSetUrl;
            if ($resumptionToken) {
                $url = $baseListSetUrl . '&resumptionToken=' . rawurlencode($resumptionToken);
            }

            /** @var \SimpleXMLElement $response */
            $response = @\simplexml_load_file($url);
            if (!$response || !isset($response->ListSets)) {
                break;
            }

            if ($this->isStoreXml) {
                $this->storeXml($response, 'ListSets', $index);
            }

            if ($totalSets === null) {
                $totalSets = isset($response->ListSets->resumptionToken)
                    ? (int) $response->ListSets->resumptionToken['completeListSize']
                    : count($response->ListSets->set);
            }

            foreach ($response->ListSets->set as $set) {
                $sets[(string) $set->setSpec] = (string) $set->setName;
                if (count($sets) >= $this->maxListSets) {
                    break 2;
                }
            }

            $resumptionToken = isset($response->ListSets->resumptionToken)
                && !empty((string) $response->ListSets->resumptionToken)
                ? (string) $response->ListSets->resumptionToken
                : false;
        } while ($resumptionToken && count($sets) <= $this->maxListSets);

        return [
            'total' => $totalSets,
            'sets' => array_slice($sets, 0, $this->maxListSets, true),
        ];
    }

    protected function storeXml(\SimpleXMLElement $xml, string $part, ?int $index = null): void
    {
        if (!is_dir($this->basePath) || !is_readable($this->basePath) || !is_writeable($this->basePath)) {
            $this->logger->err(
                'The directory "{path}" is not writeable, so the oai-pmh xml responses are not storable.', // @translate
                ['path' => $this->basePath]
            );
            return;
        }
        $dir = $this->basePath . '/oai-pmh-harvest';
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        $baseName = $this->slugify(parse_url($this->endpoint, PHP_URL_HOST));
        $date = (new \DateTime('now'))->format('Ymd-His');

        $filename = $index === null
            ? sprintf('%s.%s.%s.oaipmh.xml', $baseName, $part, $date)
            : sprintf('%s.%s.%s.%04d.oaipmh.xml', $baseName, $part, $date, $index);

        $filepath = $this->basePath . '/oai-pmh-harvest/' . $filename;
        // dom_import_simplexml($response);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $resultSave = $dom->save($filepath);
        // $resultSave = $xml->saveXML($filepath);
        if (!$resultSave) {
            $this->logger->err(
                'Unable to store xml (verb {verb}).', // @translate
                ['verb' => $part]
            );
        } else {
            $this->logger->notice(
                'The xml response (verb {verb}) was stored as {url}.', // @translate
                ['verb' => $part, 'url' => $this->baseUri . '/oai-pmh-harvest/' . $filename]
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
