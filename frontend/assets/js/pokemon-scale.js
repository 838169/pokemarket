/* Preserve the familiar size for small species; taller companions receive only a modest boost. */
window.PokePokemonScale = (() => {
    function metres(value) {
        const height = Number(value);
        return Number.isFinite(height) && height > 0 ? height : 1;
    }
    function factor(value) {
        return 1 + Math.min(0.25, Math.max(0, 0.12 * Math.log2(metres(value) / 1.5)));
    }
    return { factor, metres };
})();
