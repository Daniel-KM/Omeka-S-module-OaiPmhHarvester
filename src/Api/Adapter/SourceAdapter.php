<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class SourceAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'name' => 'name',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'name' => 'name',
        'base_url' => 'baseUrl',
        'metadata_prefix' => 'metadataPrefix',
    ];

    public function getEntityClass()
    {
        return \OaiPmhHarvester\Entity\Source::class;
    }

    public function getResourceName()
    {
        return 'oaipmhharvester_sources';
    }

    public function getRepresentationClass()
    {
        return \OaiPmhHarvester\Api\Representation\SourceRepresentation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        if (isset($query['name'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.name',
                $this->createNamedParameter($qb, $query['name']))
            );
        }

        if (isset($query['base_url'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.baseUrl',
                $this->createNamedParameter($qb, $query['base_url']))
            );
        }

        if (isset($query['metadata_prefix'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.metadataPrefix',
                $this->createNamedParameter($qb, $query['metadata_prefix']))
            );
        }

        if (isset($query['configuration_id']) && is_numeric($query['configuration_id'])) {
            $configurationAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.configuration',
                $configurationAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$configurationAlias.id",
                $this->createNamedParameter($qb, $query['configuration_id']))
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \OaiPmhHarvester\Entity\Source $entity */

        if ($this->shouldHydrate($request, 'o:name')) {
            $entity->setName($request->getValue('o:name'));
        }

        if ($this->shouldHydrate($request, 'o:base_url')) {
            $entity->setBaseUrl($request->getValue('o:base_url'));
        }

        if ($this->shouldHydrate($request, 'o:metadata_prefix')) {
            $entity->setMetadataPrefix($request->getValue('o:metadata_prefix', 'oai_dc'));
        }

        if ($this->shouldHydrate($request, 'o:configuration')) {
            $configuration = null;
            $data = $request->getContent();
            if (array_key_exists('o:configuration', $data)
                && is_array($data['o:configuration'])
                && array_key_exists('o:id', $data['o:configuration'])
            ) {
                $newConfigurationId = $data['o:configuration']['o:id'];
                $newConfigurationId = is_numeric($newConfigurationId) ? (int) $newConfigurationId : null;

                $configuration = $newConfigurationId
                    ? $this->getAdapter('oaipmhharvester_configurations')->findEntity($newConfigurationId)
                    : null;
            }
            $entity->setConfiguration($configuration);
        }

        if ($this->shouldHydrate($request, 'o:sets')) {
            $entity->setSets($request->getValue('o:sets', []));
        }
    }
}
