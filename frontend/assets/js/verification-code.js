(() => {
    const reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    for (const input of document.querySelectorAll('input[autocomplete="one-time-code"]')) {
        const grupo = document.createElement('div');
        const vista = document.createElement('div');
        grupo.className = 'verification-code';
        vista.className = 'verification-code-slots';
        vista.setAttribute('aria-hidden', 'true');
        input.before(grupo);
        input.classList.add('verification-code-input');
        grupo.append(input, vista);
        const casillas = Array.from({ length: input.maxLength }, () => {
            const casilla = document.createElement('span');
            casilla.className = 'verification-code-slot';
            casilla.append(document.createElement('span'));
            vista.append(casilla);
            return casilla;
        });

        function dibujar() {
            const enfocado = document.activeElement === input;
            const activo = Math.min(input.selectionStart, casillas.length - 1);
            grupo.dataset.complete = String(input.value.length === casillas.length);
            casillas.forEach((casilla, indice) => {
                const digito = input.value[indice] || '';
                const texto = casilla.firstElementChild;
                if (texto.textContent !== digito) {
                    texto.textContent = digito;
                    texto.getAnimations().forEach((animacion) => animacion.cancel());
                    if (digito && !reducirMovimiento.matches) {
                        texto.animate(
                            [
                                { opacity: 0, transform: 'translateY(3px) scale(0.94)' },
                                { opacity: 1, transform: 'none' },
                            ],
                            { duration: 140, easing: 'ease-out' },
                        );
                    }
                }
                casilla.classList.toggle('is-filled', Boolean(digito));
                casilla.classList.toggle('is-active', enfocado && indice === activo);
                casilla.classList.toggle(
                    'is-selected',
                    enfocado && indice >= input.selectionStart && indice < input.selectionEnd,
                );
            });
        }

        input.addEventListener('input', () => {
            const valor = input.value;
            const digitos = valor.replace(/[^0-9]/g, '').slice(0, casillas.length);
            if (valor !== digitos) {
                const posicion = valor.slice(0, input.selectionStart).replace(/[^0-9]/g, '').length;
                input.value = digitos;
                input.setSelectionRange(posicion, posicion);
            }
            if (input.getAttribute('aria-invalid') === 'true') {
                input.removeAttribute('aria-invalid');
                input.dispatchEvent(new Event('verification-corrected', { bubbles: true }));
            }
            dibujar();
        });

        input.addEventListener('paste', (event) => {
            const digitos = (event.clipboardData?.getData('text') || '').replace(/[^0-9]/g, '');
            if (digitos.length !== casillas.length) return;
            event.preventDefault();
            input.value = digitos;
            input.setSelectionRange(digitos.length, digitos.length);
            input.dispatchEvent(new Event('input'));
        });

        input.addEventListener('click', (event) => {
            if (event.detail === 1 && casillas[0].offsetWidth &&
                input.selectionStart === input.selectionEnd) {
                const destino = casillas.findIndex((casilla) => event.clientX < casilla.getBoundingClientRect().right);
                const posicion = destino < 0 ? input.value.length : Math.min(destino, input.value.length);
                input.setSelectionRange(posicion, posicion);
            }
            dibujar();
        });

        for (const evento of ['focus', 'blur', 'keyup', 'select']) input.addEventListener(evento, dibujar);
        input.form?.addEventListener('reset', () => {
            input.removeAttribute('aria-invalid');
            queueMicrotask(dibujar);
        });
        dibujar();
    }
})();
