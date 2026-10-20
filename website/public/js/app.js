const statusElt = document.getElementById('status');
const modeElts = document.querySelectorAll('input[name="mode"]');
const localeElt = document.getElementById('locale');
const emojiElt = document.getElementById('emoji');
const textElt = document.getElementById('text');
const textTitleElt = document.getElementById('text-title');
const engineElt = document.getElementById('engine');
const arrowElt = document.getElementById('arrow');

// The only catalogs that can convert back to emoji
const SHORTCODE_CATALOGS = ['github', 'gitlab', 'slack', 'text'];

let php;
try {
    php = await import('./php.js');
} catch (error) {
    statusElt.textContent = '💥 PHP refused to boot. See the console, and blame WebAssembly.';
    statusElt.classList.add('status--error');

    throw error;
}

addLocales();

let lastConversion = 0;

for (const modeElt of modeElts) {
    modeElt.addEventListener('change', changeCatalog);
}
// A click on the select does not check the radio of its label
localeElt.addEventListener('pointerdown', selectNames);
localeElt.addEventListener('change', selectNames);
emojiElt.addEventListener('input', convertEmoji);
textElt.addEventListener('input', convertText);

for (const sampleElt of document.querySelectorAll('.sample')) {
    sampleElt.addEventListener('click', () => {
        emojiElt.value = sampleElt.textContent;
        convertEmoji();
    });
}

for (const copyElt of document.querySelectorAll('[data-copy]')) {
    copyElt.addEventListener('click', async () => {
        await navigator.clipboard.writeText(document.getElementById(copyElt.dataset.copy).value);
        copyElt.textContent = 'Copied!';
        setTimeout(() => copyElt.textContent = 'Copy', 1500);
    });
}

for (const elt of document.querySelectorAll('input, select, textarea, button')) {
    elt.disabled = false;
}
document.body.classList.remove('is-loading');
statusElt.textContent = '';

document.getElementById('stat-php').textContent = php.versions.php;
document.getElementById('icu-version').textContent = `ICU ${php.versions.icu}`;
document.getElementById('stat-wasm').textContent = `${(php.wasmSize / 1e6).toFixed(1)} MB`;

changeCatalog();

function addLocales() {
    const languageNames = new Intl.DisplayNames(['en'], { type: 'language', languageDisplay: 'standard' });

    const locales = php.catalogFileNames
        .filter((name) => name.startsWith('emoji-'))
        .map((name) => name.slice('emoji-'.length))
        // "root" is the CLDR root locale, its names are codes like "E10-494"
        .filter((catalog) => catalog !== 'root' && catalog !== 'strip' && !SHORTCODE_CATALOGS.includes(catalog))
        .map((catalog) => new Option(languageNames.of(catalog.replaceAll('_', '-')), catalog))
        .sort((a, b) => a.text.localeCompare(b.text))
    ;

    localeElt.append(...locales);
    localeElt.value = preferredLocale(locales.map((option) => option.value));
}

// The first language of the browser that has a catalog, like "fr_ca" or "fr"
function preferredLocale(catalogs) {
    for (const language of navigator.languages) {
        const tag = language.toLowerCase().replaceAll('-', '_');
        const catalog = [tag, tag.split('_')[0]].find((candidate) => catalogs.includes(candidate));
        if (catalog) {
            return catalog;
        }
    }

    return 'en';
}

function selectNames() {
    document.querySelector('input[name="mode"][value="names"]').checked = true;
    changeCatalog();
}

function currentCatalog() {
    const mode = document.querySelector('input[name="mode"]:checked').value;

    return mode === 'names' ? localeElt.value : mode;
}

function changeCatalog() {
    const catalog = currentCatalog();

    textElt.readOnly = !SHORTCODE_CATALOGS.includes(catalog);
    if (SHORTCODE_CATALOGS.includes(catalog)) {
        textTitleElt.textContent = 'Shortcodes';
    } else if (catalog === 'strip') {
        textTitleElt.textContent = 'The void';
    } else {
        textTitleElt.textContent = 'Names';
    }

    convertEmoji();
}

function convertEmoji() {
    update(textElt, '→', () => php.fromEmoji(currentCatalog(), emojiElt.value));
}

function convertText() {
    update(emojiElt, '←', () => php.toEmoji(currentCatalog(), textElt.value));
}

async function update(targetElt, arrow, convert) {
    const conversion = ++lastConversion;

    let result;
    try {
        result = await convert();
    } catch (error) {
        if (conversion === lastConversion) {
            statusElt.textContent = `💥 ${error.message}`;
            statusElt.classList.add('status--error');
        }

        throw error;
    } finally {
        updateStats();
    }

    // Another conversion started while this one was loading its catalog
    if (conversion !== lastConversion) {
        return;
    }

    targetElt.value = result;
    arrowElt.textContent = arrow;
    statusElt.textContent = '';
    statusElt.classList.remove('status--error');
    shakeEngine();
}

function updateStats() {
    document.getElementById('stat-requests').textContent = php.requestCount.toLocaleString('en');
    document.getElementById('stat-duration').textContent = `${php.lastDuration.toFixed(1)} ms`;
}

function shakeEngine() {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    engineElt.animate([
        { transform: 'scale(1) rotate(0)' },
        { transform: 'scale(1.25) rotate(-12deg)' },
        { transform: 'scale(1) rotate(0)' },
    ], { duration: 300, easing: 'ease-out' });
}
