(() => {
    const form = document.querySelector('#friendSearch');
    const username = document.querySelector('#friendUsername');
    const results = document.querySelector('#friendResults');
    const page = document.querySelector('.friends-page');
    const status = document.querySelector('#friendsStatus');
    const pendingText = { solicitar: 'Enviando…', aceptar: 'Aceptando…', rechazar: 'Rechazando…' };
    let query = '', busy = false, pollBusy = false, revision = 0;
    const button = (action, id, label) => '<button type="button" data-friend="' + action + '" data-id="' + Number(id) + '">' + label + '</button>';
    const rows = (list, controls, empty) => list.map((entry) => {
        const name = PokeMarket.escaparHTML(entry.usuario);
        const initial = PokeMarket.escaparHTML(entry.usuario.slice(0, 1).toUpperCase());
        return '<article><strong><span class="friends-avatar" aria-hidden="true">' + initial + '</span>@' + name + '</strong><div class="friends-row-actions">' + controls(entry) + '</div></article>';
    }).join('') || '<p>' + empty + '</p>';

    async function refresh(body, background = false, control = null) {
        if (busy || (background && pollBusy)) return;
        if (background) pollBusy = true;
        else { busy = true; revision++; }
        const currentRevision = revision;
        const label = body ? pendingText[body.accion] : control ? 'Buscando…' : 'Cargando amigos…';
        const restore = control && PokeMarket.esperarBoton(control, label);
        if (!background) status.textContent = label;
        try {
            if (body) await PokeMarket.trainer('/friends', { method: 'POST', body });
            const requested = query;
            const data = await PokeMarket.trainer('/friends' + (requested ? '?usuario=' + encodeURIComponent(requested) : ''));
            if (background && currentRevision !== revision) return;
            if (requested && requested === query) {
                results.innerHTML = rows(data.resultados,
                    (entry) => button('solicitar', entry.id, 'Enviar solicitud'), 'No encontramos ese usuario.');
            }
            document.querySelector('#friendRequests').innerHTML = rows(data.amigos.filter((entry) => !Number(entry.aceptada)),
                (entry) => entry.recibida ? button('aceptar', entry.id, 'Aceptar') + button('rechazar', entry.id, 'Rechazar') : '<small>Pendiente de respuesta</small>',
                'Sin solicitudes pendientes.');
            document.querySelector('#friendList').innerHTML = rows(data.amigos.filter((entry) => Number(entry.aceptada)), () => '', 'Aún no tienes amigos agregados.');
            if (body) status.textContent = { solicitar: 'Solicitud enviada.', aceptar: 'Amigo agregado.', rechazar: 'Solicitud rechazada.' }[body.accion];
            else if (!background) status.textContent = '';
        } catch (error) {
            if (!background || currentRevision === revision) status.textContent = error.message;
        } finally {
            if (background) pollBusy = false;
            else busy = false;
            if (restore && control.isConnected) restore();
        }
    }
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (busy) return;
        query = username.value;
        void refresh(null, false, event.submitter || form.querySelector('button'));
    });
    username.addEventListener('input', () => {
        query = '';
        results.replaceChildren();
    });
    page.addEventListener('click', (event) => {
        const control = event.target.closest('[data-friend]');
        if (control && !control.disabled) void refresh({ accion: control.dataset.friend, id: Number(control.dataset.id) }, false, control);
    });
    void refresh();
    setInterval(() => { if (!document.hidden) void refresh(null, true); }, 3000);
})();
