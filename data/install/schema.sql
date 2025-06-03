CREATE TABLE oaipmhharvester_configuration (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    converter_name VARCHAR(255) NOT NULL,
    settings LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE `oaipmhharvester_harvest` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `job_id` INT NOT NULL,
    `undo_job_id` INT DEFAULT NULL,
    `item_set_id` INT DEFAULT NULL,
    `message` LONGTEXT DEFAULT NULL,
    `endpoint` VARCHAR(190) NOT NULL,
    `entity_name` VARCHAR(190) NOT NULL,
    `metadata_prefix` VARCHAR(190) NOT NULL,
    `mode_harvest` VARCHAR(190) NOT NULL,
    `mode_delete` VARCHAR(190) NOT NULL,
    `from` DATETIME DEFAULT NULL,
    `until` DATETIME DEFAULT NULL,
    `set_spec` VARCHAR(190) DEFAULT NULL,
    `set_name` LONGTEXT DEFAULT NULL,
    `set_description` LONGTEXT DEFAULT NULL,
    `has_err` TINYINT(1) NOT NULL,
    `stats` LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
    `resumption_token` VARCHAR(190) DEFAULT NULL,
    UNIQUE INDEX UNIQ_929CA732BE04EA9 (`job_id`),
    UNIQUE INDEX UNIQ_929CA7324C276F75 (`undo_job_id`),
    INDEX IDX_929CA732960278D7 (`item_set_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE `oaipmhharvester_entity` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `harvest_id` INT NOT NULL,
    `entity_id` INT NOT NULL,
    `entity_name` VARCHAR(190) NOT NULL,
    `identifier` LONGTEXT NOT NULL,
    `created` DATETIME NOT NULL,
    INDEX IDX_FE902C0E9079E5F6 (`harvest_id`),
    INDEX `identifier_idx` (`identifier`(767)),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

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
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE oaipmhharvester_source (
    id INT AUTO_INCREMENT NOT NULL,
    configuration_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    metadata_prefix VARCHAR(255) NOT NULL,
    sets LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
    INDEX IDX_AF32171573F32DD8 (configuration_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE oaipmhharvester_source_job (
    source_id INT NOT NULL,
    job_id INT NOT NULL,
    INDEX IDX_63040152953C1C61 (source_id),
    UNIQUE INDEX UNIQ_63040152BE04EA9 (job_id),
    PRIMARY KEY(source_id, job_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

ALTER TABLE `oaipmhharvester_harvest` ADD CONSTRAINT FK_929CA732BE04EA9 FOREIGN KEY (`job_id`) REFERENCES `job` (`id`) ON DELETE CASCADE;
ALTER TABLE `oaipmhharvester_harvest` ADD CONSTRAINT FK_929CA7324C276F75 FOREIGN KEY (`undo_job_id`) REFERENCES `job` (`id`) ON DELETE SET NULL;
ALTER TABLE `oaipmhharvester_harvest` ADD CONSTRAINT FK_929CA732960278D7 FOREIGN KEY (`item_set_id`) REFERENCES `item_set` (`id`) ON DELETE SET NULL;
ALTER TABLE `oaipmhharvester_entity` ADD CONSTRAINT FK_FE902C0E9079E5F6 FOREIGN KEY (`harvest_id`) REFERENCES `oaipmhharvester_harvest` (`id`) ON DELETE CASCADE;
ALTER TABLE oaipmhharvester_source_record ADD CONSTRAINT FK_87DB1BED126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE;
ALTER TABLE oaipmhharvester_source_record ADD CONSTRAINT FK_87DB1BED953C1C61 FOREIGN KEY (source_id) REFERENCES oaipmhharvester_source (id) ON DELETE CASCADE;
ALTER TABLE oaipmhharvester_source ADD CONSTRAINT FK_AF32171573F32DD8 FOREIGN KEY (configuration_id) REFERENCES oaipmhharvester_configuration (id);
ALTER TABLE oaipmhharvester_source_job ADD CONSTRAINT FK_63040152953C1C61 FOREIGN KEY (source_id) REFERENCES oaipmhharvester_source (id) ON DELETE CASCADE;
ALTER TABLE oaipmhharvester_source_job ADD CONSTRAINT FK_63040152BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE CASCADE
