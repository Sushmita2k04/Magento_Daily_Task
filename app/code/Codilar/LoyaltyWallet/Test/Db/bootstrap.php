<?php
declare(strict_types=1);

/**
 * Bootstraps the real Magento application for the DB tests (see README in this folder's phpunit.xml).
 * These are NOT Magento integration-framework tests: they run against the configured database, and every
 * test is wrapped in a transaction that is rolled back, so no data is left behind.
 */
require_once dirname(__DIR__, 6) . '/app/bootstrap.php';

\Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
