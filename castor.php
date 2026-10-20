<?php

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\exit_code;
use function Castor\run;

Castor\import(__DIR__ . '/website/castor.php');

#[AsTask(description: 'Install dependencies')]
function install()
{
    run(['composer', 'install']);
}

#[AsTask(description: 'Run tests')]
function tests(): int
{
    return exit_code(['vendor/bin/phpunit']);
}

#[AsTask(description: 'Fix coding standards')]
function cs(): int
{
    return exit_code(['php-cs-fixer', 'fix']);
}
