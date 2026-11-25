const formulario = document.getElementById('formLogin');
const mensaje = document.getElementById('mensaje');
const boton = formulario.querySelector('button[type="submit"]');
const byId = (id) => document.getElementById(id);
const dialog = byId('accountLoginDialog');
const securityForm = byId('accountLoginForm');
const securityButton = securityForm.querySelector('button');
const code = byId('accountCode');
const status = byId('accountLoginStatus');
let step;
code.addEventListener('verification-corrected', () => {
    status.textContent = '';
    delete status.dataset.state;
});
dialog.addEventListener('cancel', (event) => {
    if (securityButton.disabled) event.preventDefault();
});
dialog.querySelector('form[method="dialog"]').addEventListener('submit', (event) => {
    if (securityButton.disabled) event.preventDefault();
});

function showStep(next, method) {
    step = next;
    const loginCode = step === 'login-code';
    const reset = step === 'reset-password';
    const help = byId('accountLoginHelp');
    help.textContent =
        loginCode && method === 'autenticador'
            ? 'Abre tu app autenticadora e ingresa el código de seis dígitos.'
            : '';
    help.hidden = !help.textContent;
    byId('accountLoginTitle').textContent = loginCode ? 'Verificación en dos pasos' : 'Recuperar contraseña';
    byId('accountCodeLabel').textContent = loginCode && method === 'autenticador' ? 'Código de tu autenticador' : 'Código del correo';
    for (const [group, visible] of [
        ['recoveryEmailGroup', step === 'reset-request'],
        ['recoveryCodeGroup', loginCode || step === 'reset-verify'],
        ['recoveryPasswordGroup', reset],
        ['recoveryConfirmGroup', reset],
    ]) {
        const element = byId(group);
        element.hidden = !visible;
        const input = element.querySelector('input');
        input.disabled = !visible;
        input.required = visible;
    }
    securityButton.textContent = reset ? 'Cambiar contraseña' : step === 'reset-request' ? 'Enviar código' : 'Verificar código';
}
byId('openRecovery').onclick = () => {
    securityForm.reset();
    byId('recoveryEmail').value = byId('email').value;
    status.textContent = '';
    delete status.dataset.state;
    showStep('reset-request');
    dialog.showModal();
};
const google = new URLSearchParams(location.search).get('google');
if (google === 'correo' || google === 'autenticador') {
    showStep('login-code', google);
    if (google === 'correo') status.textContent = 'Revisa tu correo. El código vence en 15 minutos.';
    dialog.showModal();
    code.focus();
} else if (google === 'error' || google === 'cuenta') {
    mensaje.textContent = google === 'cuenta'
        ? 'Este correo ya tiene una cuenta de PokéMarket. Verifica esa cuenta antes de entrar con Google.'
        : 'No se pudo completar el acceso con Google. Intenta de nuevo.';
    mensaje.className = 'form-message error';
} else if (google === 'registro') {
    const signupDialog = byId('googleSignupDialog');
    const signupForm = byId('googleSignupForm');
    const username = byId('googleUsername');
    const signupStatus = byId('googleSignupStatus');
    const submit = signupForm.querySelector('button');
    signupForm.onsubmit = async (event) => {
        event.preventDefault();
        if (submit.disabled) return;
        submit.disabled = true;
        delete signupStatus.dataset.state;
        signupStatus.textContent = 'Creando tu cuenta…';
        try {
            await PokeMarket.trainer('/auth/google-signup', { method: 'POST', body: { usuario: username.value } });
            location.replace('catalogo.php');
        } catch (error) {
            signupStatus.dataset.state = 'error';
            signupStatus.textContent = error.message;
        } finally {
            submit.disabled = false;
        }
    };
    signupDialog.showModal();
    username.focus();
}
securityForm.onsubmit = async (event) => {
    event.preventDefault();
    if (securityButton.disabled) return;
    securityButton.disabled = true;
    delete status.dataset.state;
    const password = byId('newAccountPassword').value;
    const confirmacion = byId('confirmAccountPassword').value;
    if (step === 'reset-password' && password !== confirmacion) {
        status.textContent = 'Las contraseñas deben coincidir.';
        status.dataset.state = 'error';
        byId('confirmAccountPassword').focus();
        securityButton.disabled = false;
        return;
    }
    status.textContent = 'Procesando…';
    try {
        const result = await PokeMarket.trainer(`/auth/${step}`, {
            method: 'POST',
            body: step === 'reset-request' ? { email: byId('recoveryEmail').value }
                : step === 'reset-password' ? { password, confirmacion }
                : { codigo: byId('accountCode').value },
        });
        if (step === 'login-code') return (location.href = 'catalogo.php');
        if (step === 'reset-request') {
            showStep('reset-verify');
            byId('accountCode').focus();
            status.textContent = result.message;
        } else if (step === 'reset-verify') {
            byId('accountCode').value = '';
            showStep('reset-password');
            status.textContent = '';
            byId('newAccountPassword').focus();
        } else {
            securityForm.reset();
            dialog.close();
            mensaje.textContent = result.message;
            mensaje.className = 'form-message success';
        }
    } catch (error) {
        status.textContent = error.message;
        status.dataset.state = 'error';
        if ((step === 'login-code' || step === 'reset-verify') && error.status === 400) {
            code.setAttribute('aria-invalid', 'true');
            code.focus();
        }
    } finally {
        securityButton.disabled = false;
    }
};

formulario.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (boton.disabled) return;

    boton.disabled = true;
    const textoBoton = boton.textContent;
    boton.textContent = 'Conectando…';
    mensaje.className = 'form-message pending';
    mensaje.textContent = 'Comprobando tus datos…';
    const conexion = new AbortController();
    const espera = setTimeout(() => conexion.abort(), 20000);

    try {
        const resultado = await PokeMarket.trainer('/auth/login', {
            method: 'POST',
            signal: conexion.signal,
            body: {
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
            },
        });

        if (resultado.requiere_codigo) {
            mensaje.textContent = '';
            mensaje.className = 'form-message';
            formulario.querySelector('#password').value = '';
            securityForm.reset();
            status.textContent = '';
            delete status.dataset.state;
            showStep('login-code', resultado.metodo);
            if (resultado.metodo === 'correo') status.textContent = 'Revisa tu correo. El código vence en 15 minutos.';
            dialog.showModal();
            byId('accountCode').focus();
            return;
        }
        mensaje.textContent = resultado.message;
        mensaje.className = 'form-message success';
        await new Promise((resolve) => setTimeout(resolve, 700));
        window.location.href = 'catalogo.php';
    } catch (error) {
        mensaje.textContent = error.name === 'AbortError' || error.code === 'SERVICE_OFFLINE'
            ? 'No pudimos conectar con Entrenadores. Comprueba que el puerto 8002 esté activo en la misma red del celular.'
            : error.message || 'No fue posible conectar con el servidor.';
        mensaje.className = 'form-message error';
    } finally {
        clearTimeout(espera);
        boton.textContent = textoBoton;
        boton.disabled = false;
    }
});
