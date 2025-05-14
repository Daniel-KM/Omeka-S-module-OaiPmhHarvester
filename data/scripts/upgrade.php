<?php declare(strict_types=1);

namespace OaiPmhHarvester;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.0.3', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE oai_pmh_harvester_harvest_job
            CHANGE undo_job_id undo_job_id INT DEFAULT NULL,
            CHANGE comment comment VARCHAR(255) DEFAULT NULL,
            CHANGE has_err has_err TINYINT(1) NOT NULL,
            CHANGE resource_type resource_type VARCHAR(255) NOT NULL AFTER has_err,
            CHANGE collection_id collection_id INT NOT NULL,
            CHANGE base_url base_url VARCHAR(255) NOT NULL,
            CHANGE metadata_prefix metadata_prefix VARCHAR(255) NOT NULL,
            CHANGE set_spec set_spec VARCHAR(255) DEFAULT NULL,
            CHANGE set_name set_name VARCHAR(255) NOT NULL,
            CHANGE set_description set_description VARCHAR(255) DEFAULT NULL,
            CHANGE initiated initiated INT DEFAULT NULL,
            CHANGE completed completed INT DEFAULT NULL,
            CHANGE start_from start_from VARCHAR(255) DEFAULT NULL,
            CHANGE resumption_token resumption_token VARCHAR(255) DEFAULT NULL;
        
        ALTER TABLE oai_pmh_harvester_harvest_job ADD CONSTRAINT FK_FC86A2F2BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);
        ALTER TABLE oai_pmh_harvester_harvest_job ADD CONSTRAINT FK_FC86A2F24C276F75 FOREIGN KEY (undo_job_id) REFERENCES job (id);
        
        DROP INDEX uniq_17b50881be04ea9 ON oai_pmh_harvester_harvest_job;
        CREATE UNIQUE INDEX UNIQ_FC86A2F2BE04EA9 ON oai_pmh_harvester_harvest_job (job_id);
        
        DROP INDEX uniq_17b508814c276f75 ON oai_pmh_harvester_harvest_job;
        CREATE UNIQUE INDEX UNIQ_FC86A2F24C276F75 ON oai_pmh_harvester_harvest_job (undo_job_id);
        
        ALTER TABLE oai_pmh_harvester_entity
            CHANGE resource_type resource_type VARCHAR(255) NOT NULL;
        
        ALTER TABLE oai_pmh_harvester_entity ADD CONSTRAINT FK_EEA09D7FBE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);
        
        DROP INDEX idx_84d382f4be04ea9 ON oai_pmh_harvester_entity;
        CREATE INDEX IDX_EEA09D7FBE04EA9 ON oai_pmh_harvester_entity (job_id);
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.0.6', '<')) {
    // It's simpler to fill the new table than to manage complex drop/alter/change…

    $sql = <<<'SQL'
        CREATE TABLE oaipmhharvester_harvest (
            id INT AUTO_INCREMENT NOT NULL,
            job_id INT NOT NULL,
            undo_job_id INT DEFAULT NULL,
            item_set_id INT DEFAULT NULL,
            `comment` LONGTEXT DEFAULT NULL,
            endpoint VARCHAR(190) NOT NULL,
            resource_type VARCHAR(190) NOT NULL,
            metadata_prefix VARCHAR(190) NOT NULL,
            set_spec VARCHAR(190) DEFAULT NULL,
            set_name LONGTEXT DEFAULT NULL,
            set_description LONGTEXT DEFAULT NULL,
            has_err TINYINT(1) NOT NULL,
            resumption_token VARCHAR(190) DEFAULT NULL,
            UNIQUE INDEX UNIQ_929CA732BE04EA9 (job_id),
            UNIQUE INDEX UNIQ_929CA7324C276F75 (undo_job_id),
            INDEX IDX_929CA732960278D7 (item_set_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        CREATE TABLE oaipmhharvester_entity (
            id INT AUTO_INCREMENT NOT NULL,
            job_id INT NOT NULL,
            entity_id INT NOT NULL,
            resource_type VARCHAR(190) NOT NULL,
            INDEX IDX_FE902C0EBE04EA9 (job_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        ALTER TABLE oaipmhharvester_harvest ADD CONSTRAINT FK_929CA732BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);
        ALTER TABLE oaipmhharvester_harvest ADD CONSTRAINT FK_929CA7324C276F75 FOREIGN KEY (undo_job_id) REFERENCES job (id);
        ALTER TABLE oaipmhharvester_harvest ADD CONSTRAINT FK_929CA732960278D7 FOREIGN KEY (item_set_id) REFERENCES item_set (id) ON DELETE SET NULL;
        ALTER TABLE oaipmhharvester_entity ADD CONSTRAINT FK_FE902C0EBE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);
        SQL;

    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        SET FOREIGN_KEY_CHECKS=0;
        INSERT INTO oaipmhharvester_harvest
           (id, job_id, undo_job_id, item_set_id, `comment`, endpoint, resource_type, metadata_prefix, set_spec, set_name, set_description, has_err, resumption_token)
        SELECT id, job_id, undo_job_id, collection_id, `comment`, base_url, resource_type, metadata_prefix, set_spec, set_name, set_description, has_err, resumption_token
            FROM oai_pmh_harvester_harvest_job;
        SET FOREIGN_KEY_CHECKS=1;
        SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        SET FOREIGN_KEY_CHECKS=0;
        INSERT INTO oaipmhharvester_entity
            (id, job_id, entity_id, resource_type)
        SELECT id, job_id, entity_id, resource_type
            FROM oai_pmh_harvester_entity;
        SET FOREIGN_KEY_CHECKS=1;
        SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        SET FOREIGN_KEY_CHECKS=0;
        DROP TABLE IF EXISTS oai_pmh_harvester_harvest_job;
        DROP TABLE IF EXISTS oai_pmh_harvester_entity;
        SET FOREIGN_KEY_CHECKS=1;
        SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        UPDATE job
            SET class="OaiPmhHarvester\\Job\\Harvest"
            WHERE class="OaiPmhHarvester\\Job\\HarvestJob";
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.0.6.1', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE oaipmhharvester_harvest
            ADD stats LONGTEXT NOT NULL COMMENT '(DC2Type:json_array)' AFTER has_err ;
        SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        UPDATE oaipmhharvester_harvest
            SET stats = "{}";
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.0.7', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `oaipmhharvester_harvest`
            CHANGE `stats` `stats` LONGTEXT NOT NULL COMMENT '(DC2Type:json)';
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.0.10', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `oaipmhharvester_harvest` CHANGE `comment` `message` LONGTEXT DEFAULT NULL;
        ALTER TABLE `oaipmhharvester_harvest` CHANGE `resource_type` `entity_name` VARCHAR(190) NOT NULL;
        ALTER TABLE `oaipmhharvester_entity` CHANGE `resource_type` `entity_name` VARCHAR(190) NOT NULL;
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.0.12', '<')) {
    // Fix keys on some database.
    $sql = <<<'SQL'
        ALTER TABLE `oaipmhharvester_harvest` DROP FOREIGN KEY FK_929CA7324C276F75;
        ALTER TABLE `oaipmhharvester_harvest` DROP FOREIGN KEY FK_929CA732BE04EA9;
        ALTER TABLE `oaipmhharvester_harvest` ADD CONSTRAINT FK_929CA7324C276F75 FOREIGN KEY (`undo_job_id`) REFERENCES `job` (`id`) ON DELETE SET NULL;
        ALTER TABLE `oaipmhharvester_harvest` ADD CONSTRAINT FK_929CA732BE04EA9 FOREIGN KEY (`job_id`) REFERENCES `job` (`id`) ON DELETE CASCADE;
        ALTER TABLE `oaipmhharvester_entity` DROP FOREIGN KEY FK_FE902C0EBE04EA9;
        ALTER TABLE `oaipmhharvester_entity` ADD CONSTRAINT FK_FE902C0EBE04EA9 FOREIGN KEY (`job_id`) REFERENCES `job` (`id`) ON DELETE CASCADE;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Nothing.
    }

    $sql = <<<'SQL'
        ALTER TABLE `oaipmhharvester_entity`
            ADD `identifier` LONGTEXT NOT NULL AFTER `entity_name`,
            ADD `created` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `identifier`;
        ALTER TABLE `oaipmhharvester_entity` CHANGE `created` `created` DATETIME NOT NULL AFTER `identifier`;
        CREATE INDEX identifier_idx ON `oaipmhharvester_entity` (`identifier`(767));
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.17', '<')) {
    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    if (!is_dir($basePath) || !is_readable($basePath) || !is_writeable($basePath)) {
        $message = new Message(
            'The directory "%s" is not writeable, so the oai-pmh xml responses won’t be storable.', // @translate
            $basePath
        );
        $messenger->addWarning($message);
    }
    $dir = $basePath . '/oai-pmh-harvest';
    if (!file_exists($dir)) {
        mkdir($dir);
    }

    $message = new Message(
        'It is now possible to store the oai-pmh xml files received from the repository, in particular to check filters.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.18', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `oaipmhharvester_harvest`
            ADD `from` DATETIME DEFAULT NULL AFTER `metadata_prefix`,
            ADD `until` DATETIME DEFAULT NULL AFTER `from`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already updated.
    }

    try {
        $sql = <<<'SQL'
            ALTER TABLE `oaipmhharvester_entity`
                ADD `harvest_id` INT DEFAULT NULL AFTER `id`;
            UPDATE `oaipmhharvester_entity`
                INNER JOIN `oaipmhharvester_harvest` ON `oaipmhharvester_harvest`.`job_id` = `oaipmhharvester_entity`.`job_id`
                SET `harvest_id` = `oaipmhharvester_harvest`.`id`;
            ALTER TABLE `oaipmhharvester_entity`
                CHANGE `harvest_id` `harvest_id` INT NOT NULL AFTER `id`;
            SQL;
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already updated.
    }

    try {
        $sql = <<<'SQL'
            ALTER TABLE `oaipmhharvester_entity`
                DROP FOREIGN KEY `FK_FE902C0EBE04EA9`;
        SQL;
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already updated.
    }

    try {
        $sql = <<<'SQL'
            ALTER TABLE `oaipmhharvester_entity`
                DROP `job_id`,
                ADD CONSTRAINT FK_FE902C0E9079E5F6 FOREIGN KEY (`harvest_id`) REFERENCES `oaipmhharvester_harvest` (`id`) ON DELETE CASCADE;
            SQL;
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already updated.
    }

    $message = new Message(
        'It is now possible to set the period (date from/until) to harvest, when supported by the repository.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'Harvests are now run sequencially when there are multiple sets.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.19', '<')) {
    // Old mode was duplicate. New one should be set manually and is "skip".
    $sql = <<<'SQL'
        ALTER TABLE `oaipmhharvester_harvest`
            ADD `mode_harvest` VARCHAR(190) NOT NULL DEFAULT "duplicate" AFTER `metadata_prefix`;
        ALTER TABLE `oaipmhharvester_harvest`
            CHANGE `mode_harvest` `mode_harvest` VARCHAR(190) NOT NULL AFTER `metadata_prefix`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already updated.
    }

    $sql = <<<'SQL'
        ALTER TABLE `oaipmhharvester_harvest`
            ADD `mode_delete` VARCHAR(190) NOT NULL DEFAULT "skip" AFTER `mode_harvest`;
        ALTER TABLE `oaipmhharvester_harvest`
            CHANGE `mode_delete` `mode_delete` VARCHAR(190) NOT NULL AFTER `mode_harvest`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already updated.
    }

    // Module resources are not available during upgrade.
    // Clean existing stats (remove empty data).
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'stats')
        ->from('oaipmhharvester_harvest', 'oaipmhharvester_harvest')
        ->where('stats IS NOT NULL')
        ->andWhere('stats != "[]"')
        ->andWhere('stats != "{}"')
        ->orderBy('id', 'asc');
    $harvestStats = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($harvestStats as $id => $stats) {
        $stats = json_decode($stats, true) ?: [];
        $stats = array_filter($stats);
        $connection->update(
            '`oaipmhharvester_harvest`',
            ['`stats`' => json_encode($stats, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
            ['`id`' => $id]
        );
    }

    $connection->update(
        '`job`',
        ['`class`' => 'OaiPmhHarvester\Job\DeleteHarvestedEntities'],
        ['`class`' => 'OaiPmhHarvester\Job\Undo']
    );

    $advancedSearchFields = $settings->get('advancedsearch_search_fields', []) ?: [];
    $advancedSearchFields[] = 'common/advanced-search/harvests';
    $settings->set('advancedsearch_search_fields', $advancedSearchFields);

    $message = new Message(
        'It is now possible to search items by harvest.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'It is now possible to define the item set for harvested resources. It may be an existing one, a new one or none.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'It is now possible to define the harvest mode (skip, append, update, replace, duplicate) instead of always duplicating resources when a harvest is run a second time.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'The new default harvest mode is "skip", no more "duplicate".' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.21', '<')) {
    $sql = <<<'SQL'
        CREATE TABLE oaipmhharvester_configuration (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            converter_name VARCHAR(255) NOT NULL,
            settings LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
    SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        CREATE TABLE oaipmhharvester_source_record (
            id INT AUTO_INCREMENT NOT NULL,
            item_id INT NOT NULL,
            source_id INT NOT NULL,
            identifier VARCHAR(255) NOT NULL,
            UNIQUE INDEX UNIQ_87DB1BED126F525E (item_id),
            INDEX IDX_87DB1BED953C1C61 (source_id),
            INDEX IDX_87DB1BED953C1C61772E836A (source_id, identifier),
            UNIQUE INDEX UNIQ_87DB1BED126F525E953C1C61772E836A (item_id, source_id, identifier),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
    SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        CREATE TABLE oaipmhharvester_source (
            id INT AUTO_INCREMENT NOT NULL,
            configuration_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            base_url VARCHAR(255) NOT NULL,
            metadata_prefix VARCHAR(255) NOT NULL,
            sets LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
            INDEX IDX_AF32171573F32DD8 (configuration_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
    SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        CREATE TABLE oaipmhharvester_source_job (
            source_id INT NOT NULL,
            job_id INT NOT NULL,
            INDEX IDX_63040152953C1C61 (source_id),
            UNIQUE INDEX UNIQ_63040152BE04EA9 (job_id),
            PRIMARY KEY(source_id, job_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
    SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        ALTER TABLE oaipmhharvester_source_record
        ADD CONSTRAINT FK_87DB1BED126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE;
    SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        ALTER TABLE oaipmhharvester_source_record
        ADD CONSTRAINT FK_87DB1BED953C1C61 FOREIGN KEY (source_id) REFERENCES oaipmhharvester_source (id) ON DELETE CASCADE;
    SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        ALTER TABLE oaipmhharvester_source
        ADD CONSTRAINT FK_AF32171573F32DD8 FOREIGN KEY (configuration_id) REFERENCES oaipmhharvester_configuration (id);
    SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        ALTER TABLE oaipmhharvester_source_job
        ADD CONSTRAINT FK_63040152953C1C61 FOREIGN KEY (source_id) REFERENCES oaipmhharvester_source (id) ON DELETE CASCADE;
    SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        ALTER TABLE oaipmhharvester_source_job
        ADD CONSTRAINT FK_63040152BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE CASCADE
    SQL;
    $connection->executeStatement($sql);

    $connection->insert('oaipmhharvester_configuration', [
        'name' => 'Built-in mappings for oai_dc (not configurable)',
        'converter_name' => 'oai_dc',
        'settings' => '{}',
    ]);

    $connection->insert('oaipmhharvester_configuration', [
        'name' => 'Built-in mappings for oai_dcterms (not configurable)',
        'converter_name' => 'oai_dcterms',
        'settings' => '{}',
    ]);

    $connection->insert('oaipmhharvester_configuration', [
        'name' => 'Built-in mappings for mets (not configurable)',
        'converter_name' => 'mets',
        'settings' => '{}',
    ]);

    $dcProperties = [
        'contributor', 'coverage', 'creator', 'date', 'description', 'format', 'identifier', 'language',
        'publisher', 'relation', 'rights', 'source', 'subject', 'title', 'type'
    ];
    $dcMappings = array_map(function ($name) {
        return ['name' => 'xpath', 'xpath' => ".//dc:$name", 'property' => "dcterms:$name", 'type' => 'literal'];
    }, $dcProperties);

    $connection->insert('oaipmhharvester_configuration', [
        'name' => 'oai_dc',
        'converter_name' => 'xpath',
        'settings' => json_encode([
            'namespaces' => [
                'dc' => 'http://purl.org/dc/elements/1.1/',
            ],
            'mappings' => $dcMappings,
        ]),
    ]);

    $dctermsProperties = [
        'abstract', 'accessRights', 'accrualMethod', 'accrualPeriodicity', 'accrualPolicy', 'alternative',
        'audience', 'available', 'bibliographicCitation', 'conformsTo', 'contributor', 'coverage', 'created',
        'creator', 'date', 'dateAccepted', 'dateCopyrighted', 'dateSubmitted', 'description', 'educationLevel',
        'extent', 'format', 'hasFormat', 'hasPart', 'hasVersion', 'identifier', 'instructionalMethod', 'isFormatOf',
        'isPartOf', 'isReferencedBy', 'isReplacedBy', 'isRequiredBy', 'issued', 'isVersionOf', 'language',
        'license', 'mediator', 'medium', 'modified', 'provenance', 'publisher', 'references', 'relation',
        'replaces', 'requires', 'rights', 'rightsHolder', 'source', 'spatial', 'subject', 'tableOfContents',
        'temporal', 'title', 'type', 'valid'
    ];
    $dctermsMappings = array_map(function ($name) {
        return ['name' => 'xpath', 'xpath' => ".//dcterms:$name", 'property' => "dcterms:$name", 'type' => 'literal'];
    }, $dctermsProperties);

    $connection->insert('oaipmhharvester_configuration', [
        'name' => 'oai_dcterms',
        'converter_name' => 'xpath',
        'settings' => json_encode([
            'namespaces' => [
                'dcterms' => 'http://purl.org/dc/terms/',
            ],
            'mappings' => $dctermsMappings,
        ]),
    ]);
}
