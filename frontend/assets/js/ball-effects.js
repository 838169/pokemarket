window.PokeBallEffects = {
    mount(stage) {
        if (stage.querySelector('.ball-impact')) return;
        const impact = document.createElement('span');
        impact.className = 'ball-impact';
        impact.setAttribute('aria-hidden', 'true');
        impact.innerHTML =
            '<span class="impact-flash"></span><span class="impact-wave"></span><span class="impact-net"></span>' +
            [
                [-90, -45],
                [-55, -90],
                [0, -110],
                [55, -90],
                [90, -45],
                [85, 15],
                [-85, 15],
                [0, 65],
            ]
                .map(
                    ([x, y], i) =>
                        `<i class="impact-particle" style="--dx:${x}px;--dy:${y}px;--spin:${i * 45}deg"></i>`
                )
                .join('');
        stage.append(impact);
    },
};
