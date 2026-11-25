const formulario = document.getElementById('formRegistro');
const mensaje = document.getElementById('mensaje');
const boton = formulario.querySelector('button[type="submit"]');
const verificacion = document.getElementById('formVerificarRegistro');
const codigo = document.getElementById('codigoRegistro');
const verificar = verificacion.querySelector('button[type="submit"]');
const reenviar = document.getElementById('reenviarCodigoRegistro');
codigo.addEventListener('verification-corrected', () => {
    mensaje.className = 'form-message';
    mensaje.textContent = '';
});

formulario.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (boton.disabled) return;

    boton.disabled = true;
    mensaje.className = 'form-message';
    mensaje.textContent = '';

    try {
        const resultado = await PokeMarket.trainer('/auth/register', {
            method: 'POST',
            body: {
                nombre: document.getElementById('nombre').value.trim(),
                usuario: document.getElementById('usuario').value.trim(),
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
            },
        });

        formulario.reset();
        formulario.hidden = true;
        verificacion.hidden = false;
        document.getElementById('tituloRegistro').textContent = 'Verificar correo';
        document.getElementById('descripcionRegistro').textContent =
            'Confirma tu correo para completar el registro.';
        mensaje.textContent = resultado.codigo_enviado
            ? 'Revisa tu correo e ingresa el código de 6 dígitos.'
            : 'No pudimos enviar el código. Puedes solicitar otro en un minuto.';
        mensaje.classList.add(resultado.codigo_enviado ? 'success' : 'error');
        codigo.focus();
    } catch (error) {
        mensaje.textContent = error.message || 'No fue posible conectar con el servidor.';
        mensaje.classList.add('error');
    } finally {
        boton.disabled = false;
    }
});

async function verificarRegistro(ruta, body) {
    if (verificar.disabled) return;
    verificar.disabled = reenviar.disabled = true;
    mensaje.className = 'form-message';
    mensaje.textContent = '';
    try {
        const resultado = await PokeMarket.trainer(ruta, { method: 'POST', body });
        if (ruta === '/auth/register-code') {
            window.location.href = 'catalogo.php';
            return;
        }
        mensaje.textContent = resultado.message;
        mensaje.classList.add('success');
        codigo.focus();
    } catch (error) {
        mensaje.textContent = error.message || 'No fue posible completar la verificación.';
        mensaje.classList.add('error');
        if (ruta === '/auth/register-code' && error.status === 400) {
            codigo.setAttribute('aria-invalid', 'true');
            codigo.focus();
        }
    } finally {
        verificar.disabled = reenviar.disabled = false;
    }
}

verificacion.addEventListener('submit', (event) => {
    event.preventDefault();
    verificarRegistro('/auth/register-code', { codigo: codigo.value });
});
reenviar.addEventListener('click', () => verificarRegistro('/auth/register-resend', {}));
