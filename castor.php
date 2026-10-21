<?php

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function app\install_if_needed;
use function Castor\PHPQa\php_cs_fixer;
use function Castor\PHPQa\phpstan;

Castor\import(__DIR__ . '/website/castor.php');

#[AsTask(name: 'cs', namespace: 'qa', description: 'Fix coding standards')]
function qa_cs(
    #[AsOption(description: 'Only show what would be fixed, as the CI does')]
    bool $dryRun = false,
): void {
    php_cs_fixer(arguments: ['fix', ...($dryRun ? ['--dry-run', '--diff'] : [])], version: '3.95.27');
}

#[AsTask(name: 'phpstan', namespace: 'qa', description: 'Run PHPStan')]
function qa_phpstan(): void
{
    // It reads the classes of vendor/
    install_if_needed();

    phpstan(arguments: ['analyse'], version: '2.3.1');
}
