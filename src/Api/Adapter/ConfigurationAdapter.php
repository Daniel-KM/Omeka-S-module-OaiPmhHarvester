<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ConfigurationAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'name' => 'name',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'name' => 'name',
    ];

    public function getEntityClass()
    {
        return \OaiPmhHarvester\Entity\Configuration::class;
    }

    public function getResourceName()
    {
        return 'oaipmhharvester_configurations';
    }

    public function getRepresentationClass()
    {
        return \OaiPmhHarvester\Api\Representation\ConfigurationRepresentation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        if (isset($query['name'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.name',
                $this->createNamedParameter($qb, $query['name']))
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
        /** @var \OaiPmhHarvester\Entity\Configuration $entity */

        if ($this->shouldHydrate($request, 'o:name')) {
            $entity->setName($request->getValue('o:name'));
        }

        if ($this->shouldHydrate($request, 'o:converter_name')) {
            $entity->setConverterName($request->getValue('o:converter_name'));
        }

        if ($this->shouldHydrate($request, 'o:settings')) {
            $entity->setSettings($request->getValue('o:settings', []));
        }
    }
}
