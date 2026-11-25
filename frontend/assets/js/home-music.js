window.PokeHomeMusic = (() => {
    const sync = PokeAudioMixer.button('homeMusic', (enabled) => music.toggle(enabled));
    const music = PokeAudioMixer.create({
        volume: 0.25,
        onStatus(status) {
            sync(music.isEnabled());
            document.getElementById('homeMusicStatus').textContent =
                status === 'error'
                    ? 'No se pudo reproducir el sonido. Pulsa el icono para reintentar.'
                    : '';
        },
    });
    return music;
})();
