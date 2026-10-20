<?php

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsTask;

use function Castor\exit_code;

Castor\import(__DIR__ . '/website/castor.php');

#[AsTask(description: 'Fix coding standards')]
function cs(): int
{
    return exit_code(['php-cs-fixer', 'fix']);
}
