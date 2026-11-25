const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

let dialog,
    video,
    hiddenHandler,
    claims = 0,
    blockPlay = false,
    reducedMotion = true,
    failClaim = false;
const downloads = [],
    revoked = [];
let fetches = 0;
const pageEvents = {};
const requestedFiles = [];
const document = {
    hidden: false,
    body: {
        append(el) {
            dialog = el;
        },
    },
    addEventListener(name, fn) {
        if (name === 'visibilitychange') hiddenHandler = fn;
    },
    removeEventListener(name, fn) {
        if (hiddenHandler === fn) hiddenHandler = null;
    },
    createElement() {
        const nodes = new Map(),
            classes = new Set();
        return {
            classList: {
                add(name) {
                    classes.add(name);
                },
                remove(name) {
                    classes.delete(name);
                },
                contains(name) {
                    return classes.has(name);
                },
            },
            animate() {},
            setAttribute() {},
            addEventListener() {},
            showModal() {},
            close() {},
            remove() {},
            set innerHTML(html) {
                this.lastHTML = html;
                nodes.clear();
                for (const [, name] of html.matchAll(/data-([a-z-]+)(?:[ >])/g))
                    nodes.set(`[data-${name}]`, {
                        focus() {},
                        setAttribute() {},
                        hidden: name === 'ending',
                    });
                if (html.includes('<video')) {
                    const videoTag = /<video[^>]+>/.exec(html)[0];
                    assert.ok(
                        !/\scontrols(?:\s|>)/.test(videoTag),
                        'No native timeline or seek controls'
                    );
                    assert.ok(
                        !videoTag.includes('autoplay'),
                        'Do not play before the full download'
                    );
                    video = {
                        classList: { add() {} },
                        pauses: 0,
                        plays: 0,
                        currentTime: 0,
                        paused: true,
                        listeners: {},
                        pause() {
                            this.pauses++;
                            this.paused = true;
                            this.listeners.pause?.();
                        },
                        play() {
                            this.plays++;
                            if (!blockPlay) {
                                this.paused = false;
                                this.listeners.playing?.();
                            }
                            return blockPlay
                                ? Promise.reject(new Error('Autoplay blocked'))
                                : Promise.resolve();
                        },
                        load() {},
                        addEventListener(name, fn) {
                            this.listeners[name] = fn;
                        },
                    };
                    nodes.set('video', video);
                    if (html.includes('data-scene-video')) nodes.set('[data-scene-video]', video);
                }
            },
            querySelector(selector) {
                return nodes.get(selector) || null;
            },
        };
    },
};
let musicHolds = 0;
const context = vm.createContext({
    window: {
        parent: { PokeGameAudio: { duck(factor) {
            assert.equal(factor, 0);
            musicHolds++;
            return () => { musicHolds--; };
        } } },
        addEventListener(name, fn) {
            pageEvents[name] = fn;
        },
        removeEventListener(name, fn) {
            if (pageEvents[name] === fn) delete pageEvents[name];
        },
    },
    AbortController,
    setTimeout,
    clearTimeout,
    URL: {
        createObjectURL(blob) {
            assert.ok(blob.size > 0);
            return `blob:intro-${fetches}`;
        },
        revokeObjectURL(url) {
            revoked.push(url);
        },
    },
    fetch: async (url, options) => {
        fetches++;
        requestedFiles.push(url);
        assert.ok(fs.existsSync('frontend/' + url));
        return {
            ok: true,
            blob: () =>
                new Promise((resolve, reject) => {
                    if (options.signal.aborted) {
                        reject(new Error('Aborted'));
                        return;
                    }
                    downloads.push({ resolve, reject, signal: options.signal });
                    options.signal.addEventListener('abort', () => reject(new Error('Aborted')), {
                        once: true,
                    });
                }),
        };
    },
    document,
    location: { pathname: '/catalogo.php' },
    matchMedia: () => ({ matches: reducedMotion }),
    PokeMarket: {
        escaparHTML: (value) => value,
        trainer: async () => {
            if (failClaim) throw new Error('No se pudo recibir el regalo. Reintenta.');
            claims++;
            return { cartera: { necesita_bienvenida: false, monedas: 1000 } };
        },
    },
});
vm.runInContext(fs.readFileSync('frontend/assets/js/welcome.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/welcome.js') });
const welcome = context.window.PokeWelcome;
const flush = async () => {
    for (let i = 0; i < 12; i++) await Promise.resolve();
};
const completeDownload = async () => {
    await flush();
    downloads.at(-1).resolve({ size: 17170392 });
    await flush();
};
async function run() {
    const pending = welcome.run({ necesita_bienvenida: true }, () => {});
    const first = video;
    assert.equal(first.muted, false, 'Begin with sound enabled');
    assert.equal(first.plays, 0, 'Wait for the complete file before starting');
    assert.equal(dialog.querySelector('[data-loading]').hidden, false);
    await completeDownload();
    assert.equal(first.plays, 1, 'Start automatically after the full download');
    assert.equal(dialog.querySelector('[data-loading]').hidden, true);
    assert.equal(first.controls, false);
    first.currentTime = 12;
    document.hidden = true;
    hiddenHandler();
    const beforeResume = first.plays;
    document.hidden = false;
    hiddenHandler();
    await flush();
    assert.equal(first.plays, beforeResume + 1, 'Resume automatically after switching tabs');
    assert.equal(first.currentTime, 12, 'Resume at the same time');
    dialog.querySelector('[data-pause]').onclick();
    const manualPausePlays = first.plays;
    document.hidden = true;
    hiddenHandler();
    document.hidden = false;
    hiddenHandler();
    await flush();
    assert.equal(first.plays, manualPausePlays, 'Do not resume a manual pause');
    dialog.querySelector('[data-pause]').onclick();
    await flush();
    const beforeRestore = first.plays;
    pageEvents.pagehide();
    pageEvents.pageshow();
    await flush();
    assert.equal(first.plays, beforeRestore + 1, 'Resume a page restored by the browser');
    assert.equal(first.currentTime, 12);

    assert.equal(dialog.classList.contains('welcome-intro'), true);
    assert.equal(musicHolds, 1, 'La intro con sonido silencia la música del juego');
    assert.equal(dialog.querySelector('[data-ending]').hidden, true);
    first.listeners.ended();
    assert.equal(musicHolds, 0, 'La música vuelve al terminar la intro');
    assert.equal(
        dialog.querySelector('[data-ending]').hidden,
        false,
        'Offer a choice after the movie ends'
    );
    const finishedPlays = first.plays;
    document.hidden = true;
    hiddenHandler();
    document.hidden = false;
    hiddenHandler();
    await flush();
    assert.equal(first.plays, finishedPlays, 'A completed intro stays completed');
    first.currentTime = 30;
    dialog.querySelector('[data-replay]').onclick();
    assert.equal(first.currentTime, 0);
    await flush();
    assert.equal(first.plays, finishedPlays + 1);
    assert.equal(musicHolds, 1, 'Repetir la intro vuelve a silenciar la música');
    assert.equal(fetches, 1, 'Replay reuses the complete file in memory');
    assert.equal(dialog.querySelector('[data-ending]').hidden, true);
    dialog.querySelector('[data-sound]').onclick();
    assert.equal(first.muted, true, 'Allow the user to mute');
    const pausedBeforeSwitch = first.pauses;
    document.hidden = true;
    hiddenHandler();
    assert.equal(first.pauses, pausedBeforeSwitch + 1);
    document.hidden = false;
    hiddenHandler();
    await flush();
    assert.equal(first.muted, true, 'Keep the user mute choice when resuming');
    dialog.querySelector('[data-continue]').onclick();
    assert.equal(first.pauses, pausedBeforeSwitch + 2, 'Continuing stops the movie');
    assert.equal(dialog.querySelector('video'), null);
    assert.equal(dialog.classList.contains('welcome-intro'), false, 'Restore the centered guide');
    dialog.querySelector('[data-next]').onclick();
    dialog.querySelector('[data-back]').onclick();
    assert.equal(dialog.querySelector('video'), null, 'Back does not force another intro');
    assert.equal(claims, 0, 'Watching the intro does not claim coins');
    await dialog.querySelector('[data-later]').onclick();
    dialog.querySelector('[data-next]').onclick();
    assert.equal((await pending).monedas, 1000);
    assert.equal(hiddenHandler, null);
    assert.equal(Object.keys(pageEvents).length, 0, 'Remove page lifecycle listeners');
    assert.deepEqual(revoked, ['blob:intro-1'], 'Release downloaded media when leaving');

    blockPlay = true;
    const blocked = welcome.run({ necesita_bienvenida: true }, () => {});
    await completeDownload();
    assert.equal(
        dialog.querySelector('[data-ending]').hidden,
        false,
        'Autoplay rejection has a playable fallback'
    );
    assert.equal(video.muted, false, 'Autoplay rejection must not silently mute');
    assert.equal(dialog.querySelector('[data-replay]').textContent, 'Comenzar con sonido');
    blockPlay = false;
    dialog.querySelector('[data-replay]').onclick();
    await flush();
    video.listeners.error();
    assert.equal(
        dialog.querySelector('[data-ending]').hidden,
        false,
        'Playback errors never block onboarding'
    );
    dialog.querySelector('[data-continue]').onclick();
    await dialog.querySelector('[data-later]').onclick();
    assert.ok(dialog.querySelector('[data-celebration]'), 'Skipping the guide receives the gift');
    dialog.querySelector('[data-next]').onclick();
    assert.equal((await blocked).monedas, 1000);

    const background = welcome.run({ necesita_bienvenida: true }, () => {});
    document.hidden = true;
    hiddenHandler();
    await completeDownload();
    assert.equal(video.plays, 0, 'No playback while downloading in the background');
    document.hidden = false;
    hiddenHandler();
    await flush();
    assert.equal(video.plays, 1, 'Start when returning after a background download');
    await dialog.querySelector('[data-skip]').onclick();
    dialog.querySelector('[data-next]').onclick();
    assert.equal((await background).monedas, 1000);

    let skippedWallet;
    const skipped = welcome.run({ necesita_bienvenida: true }, wallet => { skippedWallet = wallet; });
    await flush();
    const pendingDownload = downloads.at(-1);
    await dialog.querySelector('[data-skip]').onclick();
    assert.equal(pendingDownload.signal.aborted, true, 'Skipping cancels an unfinished download');
    assert.equal(dialog.querySelector('video'), null, 'The intro can be skipped');
    assert.equal(skippedWallet.monedas, 1000, 'Skipping the intro updates the wallet');
    dialog.querySelector('[data-next]').onclick();
    assert.equal((await skipped).monedas, 1000);
    assert.equal(claims, 4);
    await flush();
    reducedMotion = false;
    const filesBefore = requestedFiles.length;
    const scenic = welcome.run({ necesita_bienvenida: true }, () => {});
    await flush();
    dialog.querySelector('[data-continue]').onclick();
    await completeDownload(); // preload the first companion scene while reading step one
    dialog.querySelector('[data-next]').onclick();
    await flush();
    const companion = video;
    assert.equal(companion.loop, true);
    assert.equal(companion.muted, true);
    assert.equal(companion.plays, 1, 'Companion scene starts from the prefetched complete file');
    document.hidden = true;
    hiddenHandler();
    document.hidden = false;
    hiddenHandler();
    await flush();
    assert.equal(companion.plays, 2, 'Scenery resumes after switching tabs');
    assert.equal(dialog.querySelector('[data-scene-pause]'), null, 'Scenery has no pause buttons');
    dialog.querySelector('[data-next]').onclick();
    await completeDownload();
    const care = video;
    assert.ok(companion.pauses > 0, 'Changing steps stops the previous scene');
    assert.equal(care.loop, true);
    assert.equal(care.muted, true);
    assert.equal(care.plays, 1);
    dialog.querySelector('[data-next]').onclick();
    await completeDownload();
    const battle = video;
    assert.equal(battle.loop, true);
    assert.equal(battle.muted, true);
    assert.equal(battle.plays, 1);
    const fetchesBeforeBack = fetches;
    dialog.querySelector('[data-back]').onclick();
    await flush();
    assert.equal(fetches, fetchesBeforeBack, 'Returning to a scene reuses its download');
    dialog.querySelector('[data-next]').onclick();
    await flush();
    const sceneRequests = requestedFiles
        .slice(filesBefore)
        .filter((file) => file.includes('bienvenida-'));
    assert.equal(sceneRequests.length, 3);
    for (const name of ['companero', 'cuidados', 'batalla']) {
        assert.ok(sceneRequests.some((file) => file.endsWith(`bienvenida-${name}.mp4`)));
    }
    assert.equal(
        dialog.querySelector('[data-celebration]'),
        null,
        'Do not celebrate before server confirmation'
    );
    failClaim = true;
    await dialog.querySelector('[data-next]').onclick();
    assert.equal(claims, 4, 'A failed claim does not update the wallet');
    assert.equal(
        dialog.querySelector('[data-celebration]'),
        null,
        'A failed claim never celebrates'
    );
    failClaim = false;
    await dialog.querySelector('[data-next]').onclick();
    assert.equal(claims, 5, 'The gift is still claimed once at the final step');
    assert.ok(
        dialog.querySelector('[data-celebration]'),
        'Successful claim displays the reward screen'
    );
    assert.equal(dialog.classList.contains('welcome-celebration'), true);
    const rewardImage = /<img[^>]+src="([^"]+)"/.exec(dialog.lastHTML);
    assert.ok(
        rewardImage && fs.existsSync('frontend/' + rewardImage[1]),
        'The celebration artwork exists locally'
    );
    assert.equal(dialog.classList.contains('is-celebrating'), true);
    assert.equal(
        (dialog.lastHTML.match(/<i style="--piece:/g) || []).length,
        24,
        'A single short confetti burst'
    );
    assert.equal(dialog.querySelector('video'), null, 'The celebration stops the scenery');
    await dialog.querySelector('[data-next]').onclick();
    await scenic;
    assert.equal(hiddenHandler, null);
    assert.equal(Object.keys(pageEvents).length, 0);
    reducedMotion = true;
    const still = welcome.run({ necesita_bienvenida: true }, () => {});
    await flush();
    await dialog.querySelector('[data-skip]').onclick();
    assert.ok(dialog.querySelector('[data-celebration]'));
    assert.equal(
        dialog.classList.contains('is-celebrating'),
        false,
        'Reduced motion keeps a static celebration'
    );
    assert.equal((dialog.lastHTML.match(/<i style="--piece:/g) || []).length, 0);
    await dialog.querySelector('[data-next]').onclick();
    await still;
    await flush();
    console.log(
        'OK: welcome media, reward confirmation, failed claim retry, celebration and reduced motion'
    );
}
run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
