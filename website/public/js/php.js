import createPhpModule from '../build/php-web.mjs';

// Keep in sync with website/src/convert.php and website/src/info.php
const CONVERT_SCRIPT = '/app/website/src/convert.php';
const INFO_SCRIPT = '/app/website/src/info.php';
const REQUEST_FILE = '/tmp/request.json';
const RESPONSE_FILE = '/tmp/response.txt';

// The catalogs of symfony/emoji are not embedded with the application (see
// website/castor.php): they are fetched when needed, and written where
// symfony/emoji looks for them.
const CATALOG_DIR = '/app/vendor/symfony/emoji/Resources/data';

const buildUrl = (path) => new URL(`../build/${path}`, import.meta.url);

// The catalogs that symfony/emoji can convert back to emoji, the others are
// converted back with their names. Keep in sync with src/EmojiConverter.php
export const SHORTCODE_CATALOGS = ['github', 'gitlab', 'slack', 'text'];

// The scripts write their result in a file, so the outputs only get PHP errors.
// phpw() ends each run with an empty line on both, to flush them.
const php = await createPhpModule({
    print: (line) => line && console.error(line),
    printErr: (line) => line && console.error(line),
});

php.FS.mkdirTree(CATALOG_DIR);

const catalogFiles = new Map();

// Updated on each run, importers see the new values
export let requestCount = 0;
export let lastDuration = 0;

export const wasmSize = performance.getEntriesByName(buildUrl('php-web.wasm').href)[0].decodedBodySize;

export const catalogFileNames = await fetchJson(buildUrl('emoji/index.json'));

export const versions = JSON.parse(run(INFO_SCRIPT));

export async function fromEmoji(catalog, text) {
    await loadCatalogFile(`emoji-${catalog}`);

    return convert({ direction: 'from-emoji', catalog, text });
}

export async function toEmoji(catalog, text) {
    await loadCatalogFile(SHORTCODE_CATALOGS.includes(catalog) ? `${catalog}-emoji` : `emoji-${catalog}`);

    return convert({ direction: 'to-emoji', catalog, text });
}

function convert(request) {
    php.FS.writeFile(REQUEST_FILE, JSON.stringify(request));

    return run(CONVERT_SCRIPT);
}

function run(script) {
    const start = performance.now();
    const status = php.ccall('phpw', 'number', ['string'], [script]);
    lastDuration = performance.now() - start;
    requestCount++;

    if (status !== 0) {
        throw new Error(`PHP exited with status ${status}, see the console for details.`);
    }

    return php.FS.readFile(RESPONSE_FILE, { encoding: 'utf8' });
}

function loadCatalogFile(name) {
    if (!catalogFiles.has(name)) {
        const loading = fetch(buildUrl(`emoji/${name}.txt`))
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`Unable to load the "${name}" catalog (HTTP ${response.status}).`);
                }

                php.FS.writeFile(`${CATALOG_DIR}/${name}.php`, new Uint8Array(await response.arrayBuffer()));
            })
            .catch((error) => {
                // Allow to retry
                catalogFiles.delete(name);

                throw error;
            })
        ;
        catalogFiles.set(name, loading);
    }

    return catalogFiles.get(name);
}

async function fetchJson(url) {
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`Unable to load ${url} (HTTP ${response.status}).`);
    }

    return response.json();
}
