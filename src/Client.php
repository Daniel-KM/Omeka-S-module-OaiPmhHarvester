<?php

namespace OaiPmhHarvester;

use DOMDocument;
use DOMXPath;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Response;
use Laminas\Log\Logger;
use OaiPmhHarvester\OaiPmh\ListRecordsDocument;
use OaiPmhHarvester\OaiPmh\ListSetsDocument;
use OaiPmhHarvester\OaiPmh\ListMetadataFormatsDocument;

class Client
{
    protected HttpClient $httpClient;
    protected Logger $logger;

    protected int $maxTries = 1;

    public function __construct(HttpClient $httpClient, Logger $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function setMaxTries(int $maxTries)
    {
        if ($maxTries > 0) {
            $this->maxTries = $maxTries;
        }
    }

    public function listRecords(string $baseUrl, array $args): ListRecordsDocument
    {
        $resumptionToken = $args['resumptionToken'] ?? null;
        $metadataPrefix = $args['metadataPrefix'] ?? null;
        if (!$resumptionToken && !$metadataPrefix) {
            throw new \Exception('Missing required argument metadataPrefix or resumptionToken');
        }

        $query = [
            'verb' => 'ListRecords',
            'metadataPrefix' => $metadataPrefix,
            'set' => $args['set'] ?? null,
            'from' => $args['from'] ?? null,
            'until' => $args['until'] ?? null,
        ];

        if ($resumptionToken) {
            $query = ['verb' => 'ListRecords', 'resumptionToken' => $resumptionToken];
        } else {
            $query = [
                'verb' => 'ListRecords',
                'metadataPrefix' => $metadataPrefix,
                'set' => $args['set'] ?? null,
                'from' => $args['from'] ?? null,
                'until' => $args['until'] ?? null,
            ];
        }
        $response = $this->request($baseUrl, $query);
        $xml = $response->getBody();
        $dom = new ListRecordsDocument();
        if (false === $dom->loadXML($xml)) {
            throw new \Exception(sprintf('Failed to parse response body as XML: %s', $xml));
        }

        return $dom;
    }

    public function listSets(string $baseUrl, string $resumptionToken = null): ListSetsDocument
    {
        $response = $this->request($baseUrl, ['verb' => 'ListSets', 'resumptionToken' => $resumptionToken]);
        $xml = $response->getBody();
        $dom = new ListSetsDocument();
        if (false === $dom->loadXML($xml)) {
            throw new \Exception(sprintf('Failed to parse response body as XML: %s', $xml));
        }

        return $dom;
    }

    public function listMetadataFormats(string $baseUrl, string $resumptionToken = null): ListMetadataFormatsDocument
    {
        $response = $this->request($baseUrl, ['verb' => 'ListMetadataFormats', 'resumptionToken' => $resumptionToken]);
        $xml = $response->getBody();
        $dom = new ListMetadataFormatsDocument();
        if (false === $dom->loadXML($xml)) {
            throw new \Exception(sprintf('Failed to parse response body as XML: %s', $xml));
        }

        return $dom;
    }

    protected function request(string $baseUrl, array $query = []): Response
    {
        $uri = new \Laminas\Uri\Http($baseUrl);

        $uri->setQuery(array_merge($uri->getQueryAsArray(), $query));

        $request = new \Laminas\Http\Request();
        $request->setUri($uri);

        $tries = 0;
        do {
            $tries++;

            if ($tries > 1) {
                // Wait 30s before 2nd try, then 60s, 90s, ...
                $sleepSeconds = ($tries - 1) * 30;
                $logger->info(sprintf('HTTP request failed (URL: %s): %s. Retrying in %d seconds', $uri->toString(), $response->renderStatusLine(), $sleepSeconds));
                sleep($sleepSeconds);
            }

            $response = $this->httpClient->send($request);
        } while (!$response->isOk() && $tries < $this->maxTries);

        if (!$response->isOk()) {
            throw new \Exception(sprintf('HTTP request failed (URL: %s): %s', $uri->toString(), $response->renderStatusLine()));
        }

        return $response;
    }
}
