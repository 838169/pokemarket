/* In-place green-screen key: preserve reds, whites, blacks and the success flash. */
window.PokePurchaseChroma = {
    apply(pixels) {
        for (let i = 0; i < pixels.length; i += 4) {
            const r = pixels[i],
                g = pixels[i + 1],
                b = pixels[i + 2];
            const excess = g - Math.max(r, b);
            if (g <= 30 || excess <= 18) continue;
            const key = Math.min(1, (excess - 18) / 55);
            pixels[i + 3] = Math.round(pixels[i + 3] * (1 - key));
            // Suppress reflected green on partially transparent edges.
            if (key < 1) pixels[i + 1] = Math.min(g, Math.max(r, b));
        }
        return pixels;
    },
};
