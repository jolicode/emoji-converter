<?php

namespace app;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use PhpWasm\Stack;
use Symfony\Component\Console\Input\InputOption;

use function Castor\context;
use function Castor\exit_code;
use function Castor\fs;
use function Castor\io;
use function Castor\run;
use function PhpWasm\build as build_wasm;
use function PhpWasm\check_stack;
use function PhpWasm\test_page;

#[AsTask(description: 'Install dependencies')]
function install(): void
{
    run(['composer', 'install']);
}

#[AsTask(description: 'Run tests')]
function tests(): int
{
    install_if_needed();

    return exit_code(['vendor/bin/phpunit']);
}

/**
 * @param list<string> $set
 */
#[AsTask(description: 'Build PHP WebAssembly with the application, in public/build')]
function build(
    #[AsOption(description: 'Override a value of the bake file, as "docker buildx bake --set" does (the CI gives the cache this way), can be repeated', mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY)]
    array $set = [],
): void {
    io()->note('Building PHP WebAssembly');

    $root = \dirname(__DIR__);
    $app = "{$root}/build/app";
    $public = __DIR__ . '/public/build';

    fs()->remove([$app, $public]);

    fs()->copy("{$root}/composer.json", "{$app}/composer.json");
    fs()->copy("{$root}/composer.lock", "{$app}/composer.lock");
    fs()->mirror("{$root}/src", "{$app}/src");
    fs()->mirror(__DIR__ . '/src', "{$app}/website/src");

    run(['composer', 'install', '--no-dev', '--classmap-authoritative', '--no-progress'], context: context()->withWorkingDirectory($app));

    // The catalogs of symfony/emoji (~37 MB) are not embedded with the
    // application: the website loads the ones it needs on demand. They are
    // served as text files, that web servers do not execute and GitHub Pages
    // compresses.
    fs()->mkdir("{$public}/emoji");
    $catalogs = [];
    foreach (glob("{$app}/vendor/symfony/emoji/Resources/data/*.php") as $file) {
        $catalog = basename($file, '.php');
        fs()->rename($file, "{$public}/emoji/{$catalog}.txt");
        $catalogs[] = $catalog;
    }
    fs()->dumpFile("{$public}/emoji/index.json", json_encode($catalogs, \JSON_THROW_ON_ERROR));

    build_wasm([wasm_stack()], $set);
}

#[AsTask(description: 'Check that what is built boots, and that the page converts')]
function smoke(): void
{
    build_if_needed();

    check_stack(wasm_stack());

    test_page('website/tests/php.test.mjs');
}

#[AsTask(description: 'Run the server')]
function serve(string $address = 'localhost:9999'): void
{
    build_if_needed();

    io()->note("Serving on http://{$address}");

    run(['php', '-S', $address, '-t', __DIR__ . '/public']);
}

/**
 * For the tasks that need vendor/: install() when it is missing.
 */
function install_if_needed(): void
{
    if (!is_dir('vendor')) {
        install();
    }
}

/**
 * For the tasks that need what is built: build() when it is missing.
 */
function build_if_needed(): void
{
    if (!is_file(wasm_stack()->artifact('web', 'wasm'))) {
        build();
    }
}

function wasm_stack(): Stack
{
    return Stack::named('website')
        ->php('8.5.12')
        ->extensions('intl')
        // symfony/emoji only needs the \Transliterator class, the conversion
        // itself is done with strtr()
        ->icuData('none')
        ->embed('build/app', at: '/app')
        ->targets('web')
        ->outputDir('website/public/build')
    ;
}
