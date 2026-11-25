window.PokeWelcome = (() => {
    const steps = [
        [
            'Tu aventura empieza en casa.',
            'Bienvenido a PokéMarket. Elige compañeros, cuídalos y aprende a combatir a tu ritmo.',
            'Un hogar que crecerá contigo',
            'assets/img/pokemon-home.png',
        ],
        [
            'Encuentra a tu primer compañero.',
            'En el Catálogo encontrarás precios, tipos y habilidades. Elige cualquier Pokémon que alcance tu saldo. Primeros compañeros reúne algunas recomendaciones para empezar.',
            'Mira sus detalles antes de elegir',
            'assets/img/adventure/pradera.png',
            'assets/img/adventure/bienvenida-companero.mp4',
        ],
        [
            'Cuídalos. Creced juntos.',
            'En tu Hogar puedes alimentar a tus Pokémon y fortalecer el vínculo. Desbloquea Pradera, Bosque y Laguna con monedas para ampliar su mundo.',
            'La energía se recupera con comida',
            'assets/img/adventure/bosque.png',
            'assets/img/adventure/bienvenida-cuidados.mp4',
        ],
        [
            'Tu primera batalla tiene guía.',
            'Empieza por Práctica guiada. Después prueba un duelo o un equipo completo. Las apuestas son opcionales: ganar devuelve el doble; perder o abandonar consume la entrada.',
            'Practicar no cuesta monedas',
            'assets/img/adventure/modo-practica.png',
            'assets/img/adventure/bienvenida-batalla.mp4',
        ],
    ];
    async function run(wallet, update) {
        if (!wallet.necesita_bienvenida) return wallet;
        const dialog = document.createElement('dialog');
        dialog.className = 'adventure-dialog welcome-dialog';
        dialog.setAttribute('aria-labelledby', 'welcomeTitle');
        document.body.append(dialog);
        let introPhase = true,
            step = 0,
            claiming = false,
            claimed = false;
        return new Promise((resolve) => {
            let introURL = null,
                introDownload = null,
                introController = null,
                introPlayback = null,
                sceneryPlayback = null;
            const sceneryCache = new Map();
            const loadScenery = (file) => {
                if (sceneryCache.has(file)) return sceneryCache.get(file).promise;
                const entry = { controller: new AbortController(), url: null };
                sceneryCache.set(file, entry);
                const timeout = setTimeout(() => entry.controller.abort(), 60000);
                entry.promise = (async () => {
                    try {
                        const response = await fetch(file, {
                            signal: entry.controller.signal,
                            cache: 'force-cache',
                        });
                        if (!response.ok) throw new Error('Scene unavailable');
                        const blob = await response.blob();
                        if (entry.controller.signal.aborted || !blob.size)
                            throw new Error('Scene incomplete');
                        entry.url = URL.createObjectURL(blob);
                        return entry.url;
                    } catch (error) {
                        if (sceneryCache.get(file) === entry) sceneryCache.delete(file);
                        throw error;
                    } finally {
                        clearTimeout(timeout);
                    }
                })();
                return entry.promise;
            };
            const releaseScenery = () => {
                sceneryPlayback?.stop();
                sceneryPlayback = null;
                for (const entry of sceneryCache.values()) {
                    entry.controller.abort();
                    if (entry.url) URL.revokeObjectURL(entry.url);
                }
                sceneryCache.clear();
            };
            const mountScenery = (file, nextFile) => {
                const video = dialog.querySelector('[data-scene-video]');
                const status = dialog.querySelector('[data-scene-status]');
                let active = true,
                    wantsToPlay = !matchMedia('(prefers-reduced-motion: reduce)').matches;
                let suspended = document.hidden;
                const current = () =>
                    active && dialog.querySelector('[data-scene-video]') === video;
                video.muted = true;
                video.loop = true;
                video.controls = false;
                const fail = () => {
                    if (!current()) return;
                    wantsToPlay = false;
                    video.pause();
                    video.hidden = true;
                    status.hidden = false;
                    status.textContent = 'Escenario ilustrado';
                };
                const play = async () => {
                    try {
                        const source = await loadScenery(file);
                        if (!current()) return;
                        if (video.src !== source) {
                            video.src = source;
                            video.load();
                        }
                        if (!wantsToPlay || suspended || document.hidden) {
                            status.hidden = true;
                            return;
                        }
                        await video.play();
                        if (!current() || !wantsToPlay || suspended || document.hidden)
                            video.pause();
                    } catch (error) {
                        if (!current()) return;
                        if (sceneryCache.get(file)?.url) {
                            wantsToPlay = false;
                            status.hidden = true;
                        } else fail();
                    }
                };
                video.addEventListener('playing', () => {
                    if (!current()) return;
                    if (!wantsToPlay || suspended || document.hidden) {
                        video.pause();
                        return;
                    }
                    video.classList.add('scene-ready');
                    status.hidden = true;
                    if (nextFile) loadScenery(nextFile).catch(() => {});
                });
                video.addEventListener('error', fail);
                sceneryPlayback = {
                    stop() {
                        active = false;
                        wantsToPlay = false;
                        video.pause();
                    },
                    suspend() {
                        if (current() && wantsToPlay && !suspended) {
                            suspended = true;
                            video.pause();
                        }
                    },
                    resume() {
                        if (current() && wantsToPlay && suspended) {
                            suspended = false;
                            play();
                        }
                    },
                };
                play();
            };
            const releaseIntro = () => {
                introController?.abort();
                introController = null;
                if (introURL) URL.revokeObjectURL(introURL);
                introURL = null;
                introDownload = null;
            };
            const loadIntro = () => {
                if (introURL) return Promise.resolve(introURL);
                if (introDownload) return introDownload;
                const controller = new AbortController();
                introController = controller;
                const timeout = setTimeout(() => controller.abort(), 60000);
                introDownload = (async () => {
                    try {
                        const response = await fetch('assets/img/adventure/Intro.mp4', {
                            signal: controller.signal,
                            cache: 'force-cache',
                        });
                        if (!response.ok) throw new Error('Intro unavailable');
                        // Download the complete file before the player receives a source.
                        const blob = await response.blob();
                        if (controller.signal.aborted || !blob.size)
                            throw new Error('Intro incomplete');
                        introURL = URL.createObjectURL(blob);
                        return introURL;
                    } catch (error) {
                        introDownload = null;
                        throw error;
                    } finally {
                        clearTimeout(timeout);
                    }
                })();
                return introDownload;
            };
            const pauseIntro = () => {
                introPlayback?.stop();
                dialog.querySelector('video')?.pause();
            };
            const onHide = () => {
                introPlayback?.suspend();
                sceneryPlayback?.suspend();
            };
            const onShow = () => {
                if (!document.hidden) {
                    introPlayback?.resume();
                    sceneryPlayback?.resume();
                }
            };
            const onVisibility = () => {
                if (document.hidden) onHide();
                else onShow();
            };
            document.addEventListener('visibilitychange', onVisibility);
            window.addEventListener('pagehide', onHide);
            window.addEventListener('pageshow', onShow);

            const finish = () => {
                pauseIntro();
                releaseIntro();
                releaseScenery();
                document.removeEventListener('visibilitychange', onVisibility);
                window.removeEventListener('pagehide', onHide);
                window.removeEventListener('pageshow', onShow);
                dialog.close();
                dialog.remove();
                resolve(wallet);
            };
            async function claimWelcome() {
                if (claiming || claimed) return;
                claiming = true;
                introPhase = false;
                step = steps.length - 1;
                render();
                try {
                    const { cartera } = await PokeMarket.trainer('/onboarding/complete', {
                        method: 'POST',
                        body: {},
                    });
                    wallet = cartera;
                    update(wallet);
                    claimed = true;
                } catch (error) {
                    claiming = false;
                    render(error.message);
                    return;
                }
                claiming = false;
                render();
            }
            dialog.addEventListener('cancel', (event) => {
                event.preventDefault();
                if (claimed) finish();
                else void claimWelcome();
            });
            function renderIntro() {
                dialog.classList.add('welcome-intro');
                dialog.innerHTML = `<h2 id="welcomeTitle" class="sr-only">Tu aventura Pokémon comienza aquí</h2><video class="welcome-video" playsinline preload="auto" disablepictureinpicture disableremoteplayback tabindex="-1" aria-hidden="true"></video><div class="intro-loading" data-loading role="status"><span class="brand-ball" aria-hidden="true"></span><h2>Preparando tu aventura…</h2><p>Cargando la intro completa para que puedas disfrutarla de principio a fin.</p></div><div class="intro-toolbar"><div class="intro-tools"><button type="button" class="market-secondary" data-sound aria-pressed="true" disabled>Silenciar</button><button type="button" class="market-secondary" data-pause disabled>Pausar intro</button></div><button type="button" class="market-secondary" data-skip>Saltar y recibir regalo</button></div><div class="intro-end" data-ending hidden><div class="intro-end-card"><p>Tu próxima aventura te espera</p><h2 data-intro-title>¿Listo para comenzar?</h2><p data-intro-status>Vuelve a ver la intro o continúa para conocer tu Hogar y recibir tu regalo.</p><div class="welcome-actions"><button type="button" class="market-secondary" data-replay>Volver a ver</button><button type="button" class="market-primary" data-continue>Terminar y continuar</button></div></div></div>`;
                const video = dialog.querySelector('video');
                const ending = dialog.querySelector('[data-ending]');
                const replay = dialog.querySelector('[data-replay]');
                const loading = dialog.querySelector('[data-loading]');
                const pauseButton = dialog.querySelector('[data-pause]');
                const leave = () => {
                    introPhase = false;
                    render();
                };
                let wantsToPlay = true,
                    suspended = document.hidden,
                    playVersion = 0;
                const isActive = () => introPhase && dialog.querySelector('video') === video;
                let restoreMusic;
                const releaseMusic = () => {
                    restoreMusic?.();
                    restoreMusic = undefined;
                };
                const syncMusic = () => {
                    if (isActive() && !video.paused && !video.ended && !video.muted && !document.hidden) {
                        restoreMusic ??= window.parent?.PokeGameAudio?.duck(0);
                    } else releaseMusic();
                };
                video.muted = false;
                video.controls = false;
                video.addEventListener('contextmenu', (event) => event.preventDefault());
                video.addEventListener('playing', () => {
                    if (!isActive()) return;
                    if (!wantsToPlay || suspended || document.hidden) {
                        video.pause();
                        return;
                    }
                    loading.hidden = true;
                    syncMusic();
                    dialog.classList.add('intro-playing');
                    pauseButton.disabled = false;
                    pauseButton.textContent = 'Pausar intro';
                    dialog.querySelector('[data-sound]').disabled = false;
                });
                video.addEventListener('pause', () => {
                    releaseMusic();
                    pauseButton.textContent = 'Continuar intro';
                });
                const showEnding = (title, status, button) => {
                    if (!isActive()) return;
                    releaseMusic();
                    wantsToPlay = false;
                    playVersion++;
                    loading.hidden = true;
                    ending.hidden = false;
                    dialog.querySelector('[data-intro-title]').textContent = title;
                    dialog.querySelector('[data-intro-status]').textContent = status;
                    replay.textContent = button;
                    dialog.querySelector('[data-continue]').focus();
                };
                const play = async () => {
                    const version = ++playVersion;
                    ending.hidden = true;
                    loading.hidden = Boolean(introURL);
                    try {
                        const source = await loadIntro();
                        if (!isActive() || version !== playVersion || !wantsToPlay) return;
                        if (video.src !== source) {
                            video.src = source;
                            video.load();
                        }
                        if (document.hidden || suspended) {
                            loading.hidden = true;
                            pauseButton.disabled = false;
                            pauseButton.textContent = 'Continuar intro';
                            return;
                        }
                        await video.play();
                        if (!isActive() || !wantsToPlay || suspended || document.hidden)
                            video.pause();
                    } catch (error) {
                        if (
                            !isActive() ||
                            version !== playVersion ||
                            !wantsToPlay ||
                            suspended ||
                            document.hidden
                        )
                            return;
                        showEnding(
                            introURL ? 'La intro está lista' : 'Preparemos la aventura',
                            introURL
                                ? 'Pulsa comenzar para reproducir la intro con sonido o continúa con la bienvenida.'
                                : 'No se pudo preparar la intro completa. Puedes reintentar o continuar con la bienvenida.',
                            introURL ? 'Comenzar con sonido' : 'Reintentar'
                        );
                    }
                };
                introPlayback = {
                    stop() {
                        releaseMusic();
                        wantsToPlay = false;
                        suspended = false;
                        playVersion++;
                    },
                    suspend() {
                        if (
                            !isActive() ||
                            !wantsToPlay ||
                            suspended ||
                            video.ended ||
                            !ending.hidden
                        )
                            return;
                        suspended = true;
                        playVersion++;
                        video.pause();
                    },
                    resume() {
                        if (
                            !isActive() ||
                            !wantsToPlay ||
                            !suspended ||
                            video.ended ||
                            !ending.hidden
                        )
                            return;
                        suspended = false;
                        play();
                    },
                };
                pauseButton.onclick = () => {
                    if (wantsToPlay) {
                        wantsToPlay = false;
                        playVersion++;
                        video.pause();
                    } else {
                        wantsToPlay = true;
                        suspended = false;
                        play();
                    }
                };
                video.addEventListener('ended', () =>
                    showEnding(
                        '¿Listo para comenzar?',
                        'Puedes volver a ver la intro o continuar con tu bienvenida.',
                        'Volver a ver'
                    )
                );
                const onError = () =>
                    showEnding(
                        'Continuemos la aventura',
                        'No se pudo cargar el video. Puedes reintentarlo o continuar con la bienvenida.',
                        'Reintentar video'
                    );
                video.addEventListener('error', onError);
                video.addEventListener('volumechange', () => {
                    if (dialog.querySelector('video') !== video) return;
                    syncMusic();
                    const sound = dialog.querySelector('[data-sound]');
                    sound.textContent = video.muted ? 'Activar sonido' : 'Silenciar';
                    sound.setAttribute('aria-pressed', String(!video.muted));
                });
                dialog.querySelector('[data-sound]').onclick = () => {
                    video.muted = !video.muted;
                };
                dialog.querySelector('[data-skip]').onclick = claimWelcome;
                dialog.querySelector('[data-continue]').onclick = leave;
                replay.onclick = () => {
                    if (video.error) video.load();
                    video.currentTime = 0;
                    wantsToPlay = true;
                    suspended = false;
                    play();
                };
                play();
            }
            function renderCelebration() {
                const animated = !matchMedia('(prefers-reduced-motion: reduce)').matches;
                dialog.classList.add('welcome-celebration');
                if (animated) dialog.classList.add('is-celebrating');
                const confetti = animated
                    ? Array.from(
                          { length: 24 },
                          (_, i) =>
                              `<i style="--piece:${i};--left:${5 + ((i * 37) % 90)}%;--drift:${((i % 7) - 3) * 24}px;--turn:${(i % 2 ? 1 : -1) * (180 + i * 23)}deg"></i>`
                      ).join('')
                    : '';
                dialog.innerHTML = `<section class="welcome-reward" data-celebration aria-labelledby="welcomeTitle"><div class="reward-confetti" aria-hidden="true">${confetti}</div><div class="reward-layout"><div class="reward-art" aria-hidden="true"><img class="reward-character" src="assets/img/adventure/pikachu-welcome.png" width="512" height="512" alt=""><span class="reward-spark reward-spark-one">✦</span><span class="reward-spark reward-spark-two">✦</span></div><div class="reward-copy"><p class="reward-kicker"><svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor"/><path d="m6 10 3 3 5-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>Regalo recibido</p><h2 id="welcomeTitle">¡Bienvenido,<br>entrenador!</h2><p class="reward-description">Tu primer compañero te espera.</p><div class="reward-grants"><div class="reward-coins"><span class="reward-coin" aria-hidden="true">P</span><div><strong>+1.000</strong><span>monedas</span></div></div><div class="reward-berries"><svg viewBox="0 0 32 32" width="32" height="32" aria-hidden="true"><path d="M16 10C10 2 7 5 6 6c0 6 5 7 10 4zm0 0c1-7 7-7 10-5-1 5-5 7-10 5z" fill="#91b29a"/><path d="M16 11c-9-5-15 7-8 14 5 5 17 1 17-6 0-6-4-10-9-8z" fill="#88a7cd"/><path d="M10 15c-2 1-3 4-2 6" fill="none" stroke="#dce9f6" stroke-width="2" stroke-linecap="round"/></svg><div><strong>3 cestas</strong><span>de bayas</span></div></div></div><button type="button" class="market-primary reward-next" data-next>Elegir mi primer Pokémon</button><p class="reward-confirmation" role="status">El regalo ya está en tu cartera.</p></div></div></section>`;
                const next = dialog.querySelector('[data-next]');
                next.onclick = () => {
                    finish();
                    if (!location.pathname.endsWith('/catalogo.php'))
                        location.href = 'catalogo.php';
                };
                next.focus();
            }
            function render(error = '') {
                sceneryPlayback?.stop();
                sceneryPlayback = null;
                pauseIntro();
                if (introPhase) {
                    renderIntro();
                    return;
                }
                releaseIntro();
                introPlayback = null;
                dialog.classList.remove('welcome-intro');
                dialog.classList.remove('intro-playing');
                if (claimed) {
                    releaseScenery();
                    renderCelebration();
                    return;
                }
                const item = steps[Math.min(step, steps.length - 1)];
                const scene =
                    item[4] && !claimed
                        ? `<video class="welcome-scene-video" data-scene-video muted loop playsinline preload="auto" disablepictureinpicture disableremoteplayback tabindex="-1" aria-hidden="true"></video><div class="welcome-scene-tools"><span data-scene-status role="status">Preparando escena…</span></div>`
                        : '';
                dialog.innerHTML = `<div class="welcome-art" style="background-image:url('${item[3]}')">${scene}<span class="welcome-stamp">${claimed ? 'Tu aventura ya tiene comienzo' : 'Escuela de entrenadores'}</span></div><div class="welcome-copy"><p class="welcome-progress">${claimed ? '¡Felicidades, entrenador!' : `Paso ${step + 1} de ${steps.length}`}</p><div class="welcome-dots" aria-hidden="true">${steps.map((_, i) => `<span class="${i <= step ? 'complete' : ''}"></span>`).join('')}</div><h2 id="welcomeTitle">${claimed ? '1.000 monedas. Un mundo por descubrir.' : item[0]}</h2><p>${claimed ? 'Recibiste 1.000 monedas de cortesía y 3 cestas de bayas. Tu siguiente paso: comprar tu primer compañero en el Catálogo.' : item[1]}</p><p class="welcome-tip">${claimed ? 'El regalo ya está en tu cartera.' : item[2]}</p><p role="status" class="adventure-error">${PokeMarket.escaparHTML(error)}</p><div class="welcome-actions"><button type="button" class="market-secondary" data-back ${step === 0 || claimed || claiming ? 'hidden' : ''}>Anterior</button><button type="button" class="market-primary" data-next ${claiming ? 'disabled' : ''}>${claimed ? 'Elegir mi primer Pokémon' : claiming ? 'Preparando tu regalo…' : step === steps.length - 1 ? 'Recibir mis 1.000 monedas' : 'Continuar'}</button></div><button type="button" class="welcome-later" data-later ${claiming || claimed ? 'hidden' : ''}>Omitir guía y recibir regalo</button></div>`;
                if (item[4] && !claimed) mountScenery(item[4], steps[step + 1]?.[4]);
                else if (!claimed && steps[step + 1]?.[4])
                    loadScenery(steps[step + 1][4]).catch(() => {});
                dialog.querySelector('[data-back]').onclick = () => {
                    step--;
                    render();
                };
                dialog.querySelector('[data-later]').onclick = claimWelcome;
                dialog.querySelector('[data-next]').onclick = async () => {
                    if (claimed) {
                        finish();
                        if (!location.pathname.endsWith('/catalogo.php'))
                            location.href = 'catalogo.php';
                        return;
                    }
                    if (step < steps.length - 1) {
                        step++;
                        render();
                        return;
                    }
                    await claimWelcome();
                };
                dialog.querySelector('[data-next]').focus();
            }
            render();
            dialog.showModal();
        });
    }
    return { run };
})();
