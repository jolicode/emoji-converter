<?php

namespace website;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\fs;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Build PHP WebAssembly with the application, in public/build')]
function build(): void
{
    io()->note('Building PHP WebAssembly');

    fs()->remove(__DIR__ . '/public/build');

    $context = context()
        ->withWorkingDirectory(\dirname(__DIR__))
        ->withEnvironment([
            'BUILDKIT_PROGRESS' => 'plain',
        ])
    ;

    // Keep in sync with .github/workflows/ghpages.yaml
    run(['docker', 'buildx', 'build', '--file', 'website/Dockerfile', '--output', 'website/public/build', '.'], context: $context);
}

#[AsTask(description: 'Run the server')]
function serve(string $address = 'localhost:9999'): void
{
    io()->note("Serving on http://{$address}");

    run(['php', '-S', $address, '-t', __DIR__ . '/public']);
}
