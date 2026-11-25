(() => {
    document.addEventListener('DOMContentLoaded', () => {
        if (window.parent !== window) window.parent.PokeGameAudio?.viewReady(window);
    }, { once: true });
    document.addEventListener('DOMContentLoaded', () => {
        const dialog = document.createElement('dialog');
        if (typeof dialog.showModal !== 'function') return;
        dialog.className = 'game-option-dialog game-option-sheet';
        dialog.setAttribute('aria-labelledby', 'gameOptionTitle');
        dialog.innerHTML =
            '<header><h2 id="gameOptionTitle"></h2><form method="dialog"><button aria-label="Cerrar opciones" autofocus>×</button></form></header><div class="game-option-items"></div>';
        const title = dialog.querySelector('h2');
        const items = dialog.querySelector('.game-option-items');
        let source;
        let trigger;
        const controls = new Map();
        function scan() {
            for (const select of controls.keys()) {
                if (!select.isConnected) controls.delete(select);
            }
            document.querySelectorAll('select:not([multiple]):not([size])').forEach((select) => {
                if (controls.has(select)) return;
                const label =
                    select.getAttribute('aria-label') ||
                    Array.from(select.labels || [])
                        .map((element) => {
                            const copy = element.cloneNode(true);
                            copy.querySelectorAll('select, button').forEach((child) =>
                                child.remove()
                            );
                            return copy.textContent.trim();
                        })
                        .filter(Boolean)
                        .join(' ') ||
                    'Opciones';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'game-option-trigger';
                button.setAttribute('aria-haspopup', 'dialog');
                button.addEventListener('click', () => {
                    source = select;
                    trigger = button;
                    title.textContent = label;
                    items.replaceChildren(
                        ...Array.from(select.options, (option, index) => {
                            const choice = document.createElement('button');
                            choice.type = 'button';
                            choice.disabled = option.matches(':disabled');
                            choice.dataset.optionIndex = index;
                            choice.setAttribute('aria-pressed', String(option.selected));
                            choice.textContent = option.label;
                            return choice;
                        })
                    );
                    dialog.showModal();
                    items.querySelector('[aria-pressed="true"]:not(:disabled)')?.focus();
                });
                select.hidden = true;
                select.after(button);
                controls.set(select, { button, label });
            });
            for (const [select, { button, label }] of controls) {
                const value = select.selectedOptions[0]?.label || 'Elegir opción';
                const disabled =
                    select.disabled ||
                    !Array.from(select.options).some((option) => !option.matches(':disabled'));
                if (button.textContent !== value) {
                    button.textContent = value;
                    button.setAttribute('aria-label', `${label}: ${value}`);
                }
                if (button.disabled !== disabled) button.disabled = disabled;
            }
        }
        items.addEventListener('click', (event) => {
            const option = event.target.closest('[data-option-index]');
            if (!option || option.disabled) return;
            const select = source;
            const index = Number(option.dataset.optionIndex);
            dialog.close();
            if (select.selectedIndex === index) return;
            select.selectedIndex = index;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        dialog.addEventListener('close', () => trigger?.focus({ preventScroll: true }));
        document.body.append(dialog);
        scan();
        new MutationObserver((records) => {
            if (
                records.some(
                    ({ target, addedNodes, removedNodes }) =>
                        target.closest?.('select') ||
                        [...addedNodes, ...removedNodes].some(
                            (node) => node.matches?.('select') || node.querySelector?.('select')
                        )
                )
            )
                scan();
        }).observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['disabled', 'selected'],
        });
        document.addEventListener('change', scan);
    });
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.querySelector('.game-menu-toggle');
        if (!toggle) return;
        const profile = document.getElementById('gameProfile');
        const profileTabs = [...profile.querySelectorAll('[data-profile-tab]')];
        function showProfileView(view) {
            profile.dataset.profileView = view;
            profile.querySelector('.profile-body').scrollTop = 0;
            profileTabs.forEach((tab) => {
                tab.setAttribute('aria-pressed', String(tab.dataset.profileTab === view));
            });
        }
        profileTabs.forEach((tab) => {
            tab.onclick = () => showProfileView(tab.dataset.profileTab);
        });
        const get = (id) => document.getElementById(id);
        const security = get('accountSecurity');
        const securityDialog = get('accountSecurityDialog');
        const openVerify = get('openVerifyEmail');
        const openTotp = get('openSetupTotp');
        const verifyEmail = get('verifyAccountEmail');
        const setupTotp = get('setupAccountTotp');
        const enableTotp = get('enableAccountTotp');
        const feedback = get('accountSecurityFeedback');
        securityDialog.addEventListener('verification-corrected', () => {
            feedback.textContent = '';
            delete feedback.dataset.state;
        });
        async function loadSecurity() {
            const state = await window.PokeMarket.trainer('/auth/security');
            get('accountSecurityState').textContent =
                state.verificado ? 'Verificado' : 'Pendiente';
            security.dataset.verified = String(state.verificado);
            security.dataset.totp = String(state.dos_pasos);
            get('accountTotpState').textContent = state.dos_pasos ? 'Activo' : state.verificado ? 'Por configurar' : 'Verifica tu correo';
            openVerify.setAttribute('aria-disabled', String(state.verificado));
            openTotp.setAttribute('aria-disabled', String(!state.verificado || state.dos_pasos));
            get('accountTwoStepState').textContent =
                state.dos_pasos ? 'Segundo paso por app autenticadora'
                    : state.verificado ? 'Segundo paso por correo' : 'Acceso con contraseña';
        }
        function openSecurityStep(title, form) {
            if (securityDialog.open) return;
            get('accountSecurityDialogTitle').textContent = title;
            feedback.textContent = '';
            delete feedback.dataset.state;
            verifyEmail.hidden = verifyEmail !== form;
            setupTotp.hidden = setupTotp !== form;
            enableTotp.hidden = true;
            securityDialog.showModal();
        }
        openVerify.onclick = () => {
            if (openVerify.getAttribute('aria-disabled') === 'false') openSecurityStep('Verificar correo', verifyEmail);
        };
        openTotp.onclick = () => {
            if (openTotp.getAttribute('aria-disabled') === 'false') openSecurityStep('Configurar autenticador', setupTotp);
        };
        let securityBusy = false;
        const keepOpen = (event) => { if (securityBusy) event.preventDefault(); };
        securityDialog.addEventListener('cancel', keepOpen);
        securityDialog.querySelector('form[method="dialog"]').addEventListener('submit', keepOpen);
        async function accountAction(button, action, body, done) {
            if (securityBusy || button.disabled) return;
            securityBusy = true;
            button.disabled = true;
            delete feedback.dataset.state;
            feedback.textContent = 'Procesando…';
            try {
                const result = await window.PokeMarket.trainer(`/auth/${action}`, { method: 'POST', body });
                if (!securityDialog.open) return;
                feedback.textContent = result.message || '';
                await done?.(result);
            } catch (error) {
                feedback.textContent = error.message;
                feedback.dataset.state = 'error';
                if (error.status === 400 && body.codigo !== undefined) {
                    const input = button.form?.querySelector('input[autocomplete="one-time-code"]');
                    input?.setAttribute('aria-invalid', 'true');
                    input?.focus();
                }
            } finally {
                securityBusy = false;
                button.disabled = false;
            }
        }
        const sendCode = get('sendAccountCode');
        sendCode.onclick = () => accountAction(sendCode, 'verify-request', {}, () => {
            sendCode.textContent = 'Reenviar código';
            get('verifyEmailFields').hidden = false;
            get('verifyEmailCode').disabled = false;
            get('verifyEmailCode').focus();
        });
        verifyEmail.onsubmit = (event) => {
            event.preventDefault();
            accountAction(event.submitter, 'verify-email', { codigo: get('verifyEmailCode').value }, async () => {
                securityDialog.close();
                await loadSecurity();
            });
        };
        setupTotp.onsubmit = (event) => {
            event.preventDefault();
            const password = get('accountTotpPassword').value;
            setupTotp.reset();
            accountAction(event.submitter, 'totp-setup', { password }, (result) => {
                get('accountTotpSecret').textContent = result.clave;
                setupTotp.hidden = true;
                get('accountSecurityDialogTitle').textContent = 'Vincular tu app';
                enableTotp.hidden = false;
                get('accountTotpCode').focus();
            });
        };
        enableTotp.onsubmit = (event) => {
            event.preventDefault();
            accountAction(event.submitter, 'totp-enable', { codigo: get('accountTotpCode').value }, async () => {
                securityDialog.close();
                await loadSecurity();
            });
        };
        securityDialog.addEventListener('close', () => {
            sendCode.textContent = 'Enviar código al correo';
            get('accountTotpSecret').textContent = '';
            enableTotp.hidden = true;
            verifyEmail.reset();
            get('verifyEmailFields').hidden = true;
            get('verifyEmailCode').disabled = true;
            setupTotp.reset();
            enableTotp.reset();
        });
        const editor = get('trainerHandleForm');
        const edit = get('editTrainerHandle');
        const handleInput = get('trainerHandleInput');
        const saveHandle = get('saveTrainerHandle');
        const handleFeedback = get('trainerHandleFeedback');
        let currentHandle = '';
        let changesLeft = 0;
        async function loadProfile() {
            get('trainerProfileStatus').textContent = 'Cargando progreso…';
            get('trainerProgress').hidden = true;
            try {
                const { perfil, top } = await window.PokeMarket.trainer('/profile');
                currentHandle = perfil.usuario;
                changesLeft = perfil.tarjetas_cambio_usuario;
                edit.hidden = false;
                handleInput.disabled = saveHandle.disabled = changesLeft === 0;
                saveHandle.dataset.gameLocked = String(changesLeft === 0);
                get('trainerHandleQuota').textContent = changesLeft
                    ? `Tarjetas disponibles · ${changesLeft}. Cada cambio consume una tarjeta.`
                    : 'Consigue una tarjeta de cambio de usuario en las recompensas del Cuaderno del Alba.';
                get('trainerHandle').textContent = `@${perfil.usuario}`;
                get('trainerRank').textContent = perfil.rango;
                get('trainerLevel').textContent = `Nivel ${perfil.nivel}`;
                get('trainerLevelCaption').textContent =
                    `${perfil.puntos % 100} / 100 puntos para el nivel ${perfil.nivel + 1}`;
                get('trainerLevelProgress').value = perfil.puntos % 100;
                get('trainerScore').textContent =
                    `${perfil.especies} especies · ${perfil.niveles_ganados} niveles ganados`;
                get('trainerPosition').textContent = `Puesto ${perfil.puesto}`;
                get('trainerLeaderboard').replaceChildren(
                    ...top.map((entry) => {
                        const item = document.createElement('li');
                        item.value = entry.puesto;
                        item.classList.toggle('is-you', entry.usuario === perfil.usuario);
                        item.innerHTML =
                            '<span class="ranking-place"></span><span class="ranking-player"><strong></strong><small></small></span><b class="ranking-points"></b>';
                        item.querySelector('.ranking-place').textContent = entry.puesto;
                        item.querySelector('strong').textContent =
                            entry.usuario === perfil.usuario
                                ? `@${entry.usuario} (tú)`
                                : `@${entry.usuario}`;
                        item.querySelector('small').textContent = entry.rango;
                        item.querySelector('.ranking-points').textContent =
                            `${Number(entry.puntos).toLocaleString('es-CO')} pts`;
                        return item;
                    })
                );
                get('trainerProfileStatus').textContent = '';
                get('trainerProgress').hidden = false;
            } catch (error) {
                get('trainerProfileStatus').textContent =
                    error.message || 'No pudimos cargar el progreso.';
            }
        }
        edit.onclick = () => {
            editor.hidden = !editor.hidden;
            edit.setAttribute('aria-expanded', String(!editor.hidden));
            if (!editor.hidden) {
                handleInput.value = currentHandle.includes('-') ? '' : currentHandle;
                handleFeedback.textContent = '';
                (changesLeft ? handleInput : get('trainerHandleQuota')).focus();
            }
        };
        editor.onsubmit = async (event) => {
            event.preventDefault();
            const usuario = handleInput.value.trim().toLowerCase();
            if (saveHandle.disabled || usuario === currentHandle) return;
            saveHandle.disabled = true;
            handleFeedback.textContent = 'Guardando…';
            try {
                await window.PokeMarket.trainer('/profile/username', { method: 'POST', body: { usuario } });
                await loadProfile();
                handleFeedback.textContent = 'Nombre de usuario guardado.';
            } catch (error) {
                await loadProfile();
                handleFeedback.textContent = error.message;
            } finally {
                saveHandle.disabled = changesLeft === 0;
            }
        };
        get('openGameProfile').onclick = () => {
            showProfileView('profile');
            editor.hidden = true;
            edit.hidden = true;
            edit.setAttribute('aria-expanded', 'false');
            profile.showModal();
            loadProfile();
            feedback.textContent = '';
            loadSecurity().catch((error) => { get('accountTwoStepState').textContent = error.message; });
        };
        const nav = document.getElementById(toggle.getAttribute('aria-controls'));
        const setOpen = (open) => {
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        };
        toggle.addEventListener('click', () =>
            setOpen(toggle.getAttribute('aria-expanded') !== 'true')
        );
        nav.querySelector('[data-game-settings]').addEventListener('click', (event) => {
            const menuOpen = toggle.getAttribute('aria-expanded') === 'true';
            setOpen(false);
            window.parent.PokeGameAudio?.openSettings(menuOpen ? toggle : event.currentTarget);
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.game-header') || event.target.closest('#gameNavigation a'))
                setOpen(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });
        document.addEventListener('focusin', (event) => {
            if (event.target !== toggle && !nav.contains(event.target)) setOpen(false);
        });
    });
    if (window.parent === window || !window.parent.PokeGameAudio) return;
    document.documentElement.dataset.audioHosted = 'true';
    for (const event of ['pointerdown', 'keydown'])
        document.addEventListener(event, () => window.parent.PokeGameAudio.resume());
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented) return;
        const link = event.target.closest('a[href]');
        if (
            !link ||
            link.target ||
            link.hasAttribute('download') ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey ||
            event.button !== 0
        )
            return;
        const url = new URL(link.href);
        if (url.pathname === location.pathname && url.hash) return;
        if (window.parent.PokeGameAudio.navigate(link.href)) event.preventDefault();
    });
})();
