/* Six authored standing places per landscape. Image and label share one reserved box. */
window.PokeHomeLayout = (() => {
    function plan(
        scene,
        width,
        height,
        residents = Array.from({ length: 6 }, () => ({ altura: 1 }))
    ) {
        const people = residents.slice(0, 6),
            count = people.length;
        if (!count) return { capacity: 6, slots: [] };
        const compact = width < 600;
        const cols = 3,
            rows = 2;
        const shore = scene === 'laguna' && !compact;
        const left = shore ? 0.05 : 0.04,
            right = shore ? 0.76 : 0.96;
        const top = Math.max(64, height * 0.17),
            bottom = height * 0.91;
        const gap = compact ? 10 : 22,
            label = 26;
        const cellWidth = (width * (right - left)) / cols;
        const rowHeight = (bottom - top) / rows;
        const available = Math.max(1, Math.min(cellWidth - gap, rowHeight - label - gap, 640));
        const baseline = Math.min(
            available,
            260,
            width * (compact ? 0.27 : width < 1000 ? 0.25 : 0.14),
            height * 0.34
        );
        const slots = people.map((p, i) => {
            const row = Math.floor(i / cols),
                remaining = cols;
            let center =
                width *
                (left + (right - left) * (((i % cols) + 0.5 + (cols - remaining) / 2) / cols));
            let foot = top + rowHeight * (row + 1) - gap / 2;
            let size = Math.min(
                available,
                Math.max(
                    Math.min(48, available),
                    baseline * window.PokePokemonScale.factor(p.altura)
                )
            );
            return {
                left: center - size / 2,
                top: foot - size - label,
                width: size,
                height: size + label,
                imageSize: size,
            };
        });
        return { capacity: 6, slots };
    }
    function page(items, index, capacity = 6) {
        const pages = Math.max(1, Math.ceil(items.length / capacity));
        const current = Math.min(Math.max(0, index), pages - 1);
        return {
            current,
            pages,
            offset: current * capacity,
            items: items.slice(current * capacity, (current + 1) * capacity),
        };
    }
    return { plan, page };
})();
