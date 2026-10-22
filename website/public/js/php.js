import createPhp from '../build/php-web.mjs';

// Where website/castor.php embeds them
const CONVERT_SCRIPT = '/app/website/src/convert.php';
const INFO_SCRIPT = '/app/website/src/info.php';

// The catalogs of symfony/emoji are not embedded with the application (see
// website/castor.php): they are fetched when needed, and written where
// symfony/emoji looks for them.
const CATALOG_DIR = '/app/vendor/symfony/emoji/Resources/data';

const buildUrl = (path) => new URL(`../build/${path}`, import.meta.url);

// The catalogs that symfony/emoji can convert back to emoji, the others are
// converted back with their names. Keep in sync with src/EmojiConverter.php
export const SHORTCODE_CATALOGS = ['github', 'gitlab', 'slack', 'text'];

const php = await createPhp();

php.FS.mkdirTree(CATALOG_DIR);

const catalogFiles = new Map();

// Updated on each run, importers see the new values
export let requestCount = 0;
export let lastDuration = 0;

export const wasmSize = performance.getEntriesByName(buildUrl('php-web.wasm').href)[0].decodedBodySize;

export const catalogFileNames = await fetchJson(buildUrl('emoji/index.json'));

export const versions = JSON.parse(await run(INFO_SCRIPT));

export async function fromEmoji(catalog, text) {
    await loadCatalogFile(`emoji-${catalog}`);

    return convert({ direction: 'from-emoji', catalog, text });
}

export async function toEmoji(catalog, text) {
    await loadCatalogFile(SHORTCODE_CATALOGS.includes(catalog) ? `${catalog}-emoji` : `emoji-${catalog}`);

    return convert({ direction: 'to-emoji', catalog, text });
}

function convert(request) {
    return run(CONVERT_SCRIPT, JSON.stringify(request));
}

/**
 * The scripts write their result on stdout, and their errors on stderr.
 */
async function run(script, stdin = '') {
    const start = performance.now();
    const { exitCode, stdout, stderr } = await php.runFile(script, { stdin });
    lastDuration = performance.now() - start;
    requestCount++;

    if (stderr) {
        console.error(stderr);
    }
    if (exitCode !== 0) {
        throw new Error(`PHP exited with status ${exitCode}, see the console for details.`);
    }

    return stdout;
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
