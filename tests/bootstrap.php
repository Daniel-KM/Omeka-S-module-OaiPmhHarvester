<?php declare(strict_types=1);

/**
 * Bootstrap file for OaiPmhHarvester module tests.
 *
 * Use Common module Bootstrap helper for test setup.
 * The Bootstrap automatically registers:
 * - CommonTest\ namespace (test utilities like AbstractHttpControllerTestCase)
 * - Module namespaces from composer.json (autoload and autoload-dev)
 */

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        '?Mapper',  // Optional: Mapper-based formats only available when installed
        'OaiPmhHarvester',
    ],
    'OaiPmhHarvesterTest',
    __DIR__ . '/OaiPmhHarvesterTest'
);
