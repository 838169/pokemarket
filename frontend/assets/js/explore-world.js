/* Small isometric renderer. No network, game rules or user data live here. */
window.PokeExploreWorld = (() => {
    const TW = 72,
        TH = 36;
    const project = (x, y) => ({ x: ((x - y) * TW) / 2, y: ((x + y) * TH) / 2 });
    const unproject = (x, y) => ({
        x: Math.round(x / TW + y / TH),
        y: Math.round(y / TH - x / TW),
    });
    const noise = (x, y) => {
        const n = Math.sin(x * 127.1 + y * 311.7) * 43758.5453;
        return n - Math.floor(n);
    };
    const walkable = (grid, x, y) =>
        Boolean(grid[y]?.[x] !== undefined && ![0, 3].includes(grid[y][x]));
    const environmentAt = (seconds, config = {}) => {
        const day = config.dia_segundos || 480,
            night = config.noche_segundos || 240;
        const fade = Math.min(config.transicion_segundos || 35, day / 2, night / 2);
        const phase = ((seconds % (day + night)) + day + night) % (day + night);
        const smooth = (t) => t * t * (3 - 2 * t);
        let darkness = 0,
            label = 'Día';
        if (phase >= day - fade && phase < day) {
            darkness = smooth((phase - day + fade) / fade);
            label = 'Atardecer';
        } else if (phase >= day && phase < day + night - fade) {
            darkness = 1;
            label = 'Noche';
        } else if (phase >= day + night - fade) {
            darkness = 1 - smooth((phase - day - night + fade) / fade);
            label = 'Amanecer';
        }
        return { darkness, label, remaining: Math.ceil((phase < day ? day : day + night) - phase) };
    };
    const wildlifeMotion = (sighting, seconds) => {
        const route = sighting.ruta;
        const duration = route.reduce((sum, p) => sum + p.duracion_ms, 0);
        const elapsed = Math.max(0, (seconds - sighting.inicio) * 1000);
        let phase =
            sighting.repetir === false
                ? Math.min(elapsed, Math.max(0, duration - 0.001))
                : elapsed % Math.max(1, duration);
        let previous = sighting.repetir === false ? route[0] : route.at(-1);
        for (const frame of route) {
            if (phase < frame.duracion_ms) {
                const t = phase / frame.duracion_ms,
                    dx = frame.x - previous.x,
                    dy = frame.y - previous.y;
                return {
                    x: previous.x + dx * t,
                    y: previous.y + dy * t,
                    dx,
                    dy,
                    moving: dx !== 0 || dy !== 0,
                    hidden:
                        sighting.modo !== 'huyendo' &&
                        Boolean(sighting.escondite) &&
                        dx === 0 &&
                        dy === 0 &&
                        frame.x === sighting.escondite[0] &&
                        frame.y === sighting.escondite[1],
                    phase: t,
                };
            }
            phase -= frame.duracion_ms;
            previous = frame;
        }
        return { x: route[0].x, y: route[0].y, dx: 0, dy: 0, moving: false, phase: 0 };
    };
    const wildlifeFrame = (meta, milliseconds, reduced = false) => {
        if (reduced) return 0;
        const durations = meta.durations.map((v) => (v * 1000) / 60);
        let phase = Math.max(0, milliseconds) % durations.reduce((sum, v) => sum + v, 0);
        for (let i = 0; i < durations.length; i++) {
            if (phase < durations[i]) return i;
            phase -= durations[i];
        }
        return 0;
    };
    // SpriteCollab rows: S, SW, W, NW, N, NE, E, SE.
    const wildlifeDirection = (dx, dy) => {
        const p = project(dx, dy);
        return (Math.round(Math.atan2(-p.x, p.y) / (Math.PI / 4)) + 8) % 8;
    };
    class World {
        constructor(canvas, map, position, onMove) {
            this.canvas = canvas;
            this.ctx = canvas.getContext('2d');
            this.map = map;
            this.actor = [...position];
            this.destination = [...position];
            this.onMove = onMove;
            this.zoom = innerWidth < 600 ? 0.85 : 1;
            this.camera = project(...position);
            this.busy = false;
            this.active = true;
            this.sites = [];
            this.explored = new Set(map.explorado || []);
            this.path = [];
            this.routeEnds = [];
            this.scheduledSteps = 0;
            this.completedSteps = 0;
            this.pathDurations = [];
            this.clockOffset = 0;
            this.stepMs = map.paso_ms || 55;
            this.facing = [1, 0];
            this.queued = null;
            this.hover = null;
            this.wildlife = null;
            this.wildlifeSprites = new Map();
            this.trainerSprite = new Image();
            this.trainerSprite.src = 'assets/img/trainer-adventure-3d.png';
            this.trainerLayer = document.createElement('canvas');
            this.trainerLayer.width = this.trainerLayer.height = 172;
            this.trainerMotion = 0;
            this.reduced = matchMedia('(prefers-reduced-motion: reduce)');
            this.resizeObserver = new ResizeObserver(() => this.resize());
            this.resizeObserver.observe(canvas);
            this.click = (e) => {
                if (!this.active) return;
                if (this.dragged) {
                    this.dragged = false;
                    return;
                }
                canvas.focus({ preventScroll: true });
                this.overviewMode = false;
                const wildlife = this.wildlifeAt(e.clientX, e.clientY);
                if (wildlife) {
                    this.wildlifeReactionUntil = Date.now() + 1200;
                    this.onWildlife?.(wildlife.id);
                    return;
                }
            };
            this.dragStart = (e) => {
                if (e.button !== 0) return;
                this.drag = { x: e.clientX, y: e.clientY, camera: { ...this.camera } };
                this.dragged = false;
                canvas.setPointerCapture(e.pointerId);
                canvas.focus({ preventScroll: true });
            };
            this.dragEnd = () => {
                this.drag = null;
            };
            this.dragCancel = () => {
                this.drag = null;
                this.dragged = false;
            };
            this.pointer = (e) => {
                if (this.drag) {
                    const dx = e.clientX - this.drag.x,
                        dy = e.clientY - this.drag.y;
                    if (Math.hypot(dx, dy) > 6) this.dragged = true;
                    if (this.dragged) {
                        this.manualCamera = true;
                        this.camera = {
                            x: this.drag.camera.x - dx / this.zoom,
                            y: this.drag.camera.y - dy / this.zoom,
                        };
                        canvas.style.cursor = 'grabbing';
                        return;
                    }
                }
                this.hover = this.cellAt(e.clientX, e.clientY);
                canvas.style.cursor = this.wildlifeAt(e.clientX, e.clientY) ? 'pointer' : 'grab';
            };
            this.leave = () => {
                this.hover = null;
            };
            canvas.addEventListener('pointerdown', this.dragStart);
            canvas.addEventListener('pointerup', this.dragEnd);
            canvas.addEventListener('pointercancel', this.dragCancel);
            canvas.addEventListener('click', this.click);
            canvas.addEventListener('pointermove', this.pointer);
            canvas.addEventListener('pointerleave', this.leave);
            this.onVisibilityChange = () => {
                if (document.hidden) {
                    cancelAnimationFrame(this.frame);
                    this.frame = null;
                } else if (!this.frame) {
                    this.last = performance.now();
                    this.frame = requestAnimationFrame((t) => this.tick(t));
                }
            };
            document.addEventListener('visibilitychange', this.onVisibilityChange);
            this.resize();
            this.last = performance.now();
            this.draw(0);
            this.frame = requestAnimationFrame((t) => this.tick(t));
        }
        resize() {
            const rect = this.canvas.getBoundingClientRect();
            this.width = rect.width;
            this.height = rect.height;
            this.dpr = Math.min(devicePixelRatio || 1, 2);
            this.canvas.width = Math.round(rect.width * this.dpr);
            this.canvas.height = Math.round(rect.height * this.dpr);
        }
        cellAt(x, y) {
            const r = this.canvas.getBoundingClientRect();
            return unproject(
                (x - r.left - this.width / 2) / this.zoom + this.camera.x,
                (y - r.top - this.height * 0.54) / this.zoom + this.camera.y
            );
        }
        visibility(x, y) {
            if (this.map.modo !== 'expedicion') return 2;
            const near =
                Math.abs(x - this.actor[0]) + Math.abs(y - this.actor[1]) <= (this.map.vision || 3);
            return near ? 2 : this.explored?.has(`${x},${y}`) ? 1 : 0;
        }
        inView(x, y, padding = 220) {
            const p = project(x, y);
            const sx = (p.x - this.camera.x) * this.zoom + this.width / 2;
            const sy = (p.y - this.camera.y) * this.zoom + this.height * 0.54;
            return (
                sx > -padding &&
                sx < this.width + padding &&
                sy > -padding &&
                sy < this.height + padding
            );
        }
        fog() {
            if (this.map.modo !== 'expedicion') return;
            const revision = `${this.map.terreno.length}:${this.explored?.size || 0}`;
            if (this.fogRevision !== revision) {
                this.fogRevision = revision;
                const bounds = this.groundBounds;
                const hard = (this.fogHard ??= document.createElement('canvas'));
                const width = Math.ceil(bounds.w / 2),
                    height = Math.ceil(bounds.h / 2);
                if (hard.width !== width || hard.height !== height) {
                    hard.width = width;
                    hard.height = height;
                }
                const ctx = hard.getContext('2d');
                ctx.setTransform(0.5, 0, 0, 0.5, -bounds.x / 2, -bounds.y / 2);
                ctx.clearRect(bounds.x, bounds.y, bounds.w, bounds.h);
                const original = this.ctx;
                this.ctx = ctx;
                this.map.terreno.forEach((row, y) =>
                    row.forEach((_, x) => {
                        if (this.explored?.has(`${x},${y}`)) return;
                        const p = project(x, y);
                        this.diamond(p.x, p.y, '#d8e6df');
                    })
                );
                this.ctx = original;
                this.fogLayer ??= document.createElement('canvas');
                if (this.fogLayer.width !== hard.width || this.fogLayer.height !== hard.height) {
                    this.fogLayer.width = hard.width;
                    this.fogLayer.height = hard.height;
                }
                const soft = this.fogLayer.getContext('2d');
                soft.clearRect(0, 0, hard.width, hard.height);
                soft.filter = 'blur(12px)';
                soft.drawImage(hard, 0, 0);
            }
            const b = this.groundBounds;
            this.ctx.save();
            this.ctx.globalAlpha = 0.84;
            this.ctx.drawImage(this.fogLayer, b.x, b.y, b.w, b.h);
            this.ctx.restore();
        }
        environment() {
            return environmentAt((Date.now() + (this.clockOffset || 0)) / 1000, this.map.ciclo);
        }
        atmosphere(clock) {
            const c = this.ctx,
                light = this.environment();
            c.save();
            c.fillStyle = `rgba(12,22,61,${light.darkness * 0.52})`;
            c.fillRect(0, 0, this.width, this.height);
            // Slow translucent mist is separate from the persistent exploration mask.
            for (let i = 0; i < 5; i++) {
                const x =
                    ((i * 0.29 * this.width + clock * 0.008) % (this.width * 1.6)) -
                    this.width * 0.3;
                const y = this.height * (0.24 + i * 0.15) + Math.sin(clock / 11000 + i) * 18;
                const radius = this.width * 0.32;
                const haze = c.createRadialGradient(x, y, 0, x, y, radius);
                haze.addColorStop(0, `rgba(218,238,229,${light.darkness ? 0.045 : 0.075})`);
                haze.addColorStop(1, 'rgba(218,238,229,0)');
                c.fillStyle = haze;
                c.fillRect(x - radius, y - radius, radius * 2, radius * 2);
            }
            if (light.darkness > 0.1) {
                const camp = project(...(this.map.campamento || this.map.inicio || [5, 12]));
                const x = (camp.x - this.camera.x) * this.zoom + this.width / 2;
                const y = (camp.y - this.camera.y) * this.zoom + this.height * 0.54;
                const glow = c.createRadialGradient(x, y - 24, 0, x, y - 24, 100 * this.zoom);
                glow.addColorStop(0, `rgba(255,190,102,${light.darkness * 0.28})`);
                glow.addColorStop(1, 'rgba(255,190,102,0)');
                c.fillStyle = glow;
                c.fillRect(x - 150, y - 175, 300, 300);
            }
            c.restore();
        }
        setWildlife(sighting) {
            this.wildlife = sighting || null;
            if (!sighting) return;
            this.wildlifeSprites ??= new Map();
            const id = sighting.pokemon.id;
            if (!this.wildlifeSprites.has(id)) {
                const entry = { sheets: {}, fallback: null };
                const fallback = () => {
                    if (entry.fallback) return;
                    const image = new Image();
                    image.decoding = 'async';
                    image.src = PokeMarket.imagenPokemon(id);
                    entry.fallback = image;
                };
                const catalog = window.PokeWildlifeSprites?.[id];
                if (catalog) {
                    for (const kind of ['walk', 'idle']) {
                        const meta = catalog[kind];
                        if (!meta) continue;
                        const image = new Image();
                        image.decoding = 'async';
                        image.onerror = fallback;
                        image.src = meta.src;
                        entry.sheets[kind] = { image, meta };
                    }
                } else fallback();
                this.wildlifeSprites.set(id, entry);
                if (this.wildlifeSprites.size > 12)
                    this.wildlifeSprites.delete(this.wildlifeSprites.keys().next().value);
            }
        }

        wildlifePosition() {
            if (!this.wildlife) return null;
            const m = wildlifeMotion(this.wildlife, (Date.now() + (this.clockOffset || 0)) / 1000);
            return [m.x, m.y];
        }
        isWildlifeHidden() {
            return this.wildlife
                ? wildlifeMotion(this.wildlife, (Date.now() + (this.clockOffset || 0)) / 1000)
                      .hidden
                : false;
        }
        wildlifeAt(x, y) {
            const sighting = this.wildlife;
            if (
                !sighting ||
                sighting.modo === 'huyendo' ||
                sighting.vence <= (Date.now() + (this.clockOffset || 0)) / 1000
            )
                return null;
            const motion = wildlifeMotion(sighting, (Date.now() + (this.clockOffset || 0)) / 1000);
            if (this.visibility(Math.round(motion.x), Math.round(motion.y)) !== 2) return null;
            const p = project(motion.x, motion.y),
                r = this.canvas.getBoundingClientRect();
            const sx = (p.x - this.camera.x) * this.zoom + this.width / 2 + r.left;
            const sy = (p.y - this.camera.y) * this.zoom + this.height * 0.54 + r.top;
            return Math.abs(x - sx) < 34 * this.zoom &&
                y > sy - 64 * this.zoom &&
                y < sy + 12 * this.zoom
                ? sighting
                : null;
        }
        drawWildlife(motion, clock) {
            const sighting = this.wildlife;
            const c = this.ctx,
                p = project(motion.x, motion.y);
            if (motion.hidden) {
                const shake =
                    this.reduced.matches || clock % 1900 > 650 ? 0 : Math.sin(clock / 65) * 2.5;
                this.ellipse(p.x, p.y + 5, 25, 8, '#173e3d44');
                c.save();
                c.translate(p.x + shake, p.y);
                for (const [x, y, color] of [
                    [-13, -7, '#39754d'],
                    [12, -8, '#4c9460'],
                    [0, -16, '#65ad73'],
                    [-8, -18, '#71b97d'],
                    [9, -20, '#86c78a'],
                ]) {
                    this.ellipse(x, y, 14, 12, color);
                }
                c.font = '600 11px Outfit,sans-serif';
                c.textAlign = 'center';
                c.fillStyle = '#142b3fed';
                c.fillRect(-53, -58, 106, 18);
                c.fillStyle = '#ffe16a';
                c.fillText('Algo se mueve…', 0, -45);
                c.restore();
                return;
            }
            const runner = sighting.perfil === 'corredor',
                hopper = sighting.perfil === 'saltarín';
            const stride =
                motion.moving && !this.reduced.matches ? Math.sin(motion.phase * Math.PI * 2) : 0;
            const lift = hopper ? Math.abs(stride) * 9 : Math.abs(stride) * (runner ? 4 : 2);
            const face = project(motion.dx, motion.dy).x;
            if (face) this.wildlifeFacing = face < 0 ? -1 : 1;
            this.ellipse(p.x, p.y + 3, 18 - lift * 0.4, 6, '#173e3d44');
            if (runner && motion.moving && !this.reduced.matches) {
                this.ellipse(p.x - (this.wildlifeFacing || 1) * 22, p.y + 1, 5, 2, '#eedca55c');
            }
            const sprite = this.wildlifeSprites.get(sighting.pokemon.id);
            const sheet = sprite?.sheets[motion.moving ? 'walk' : 'idle'] || sprite?.sheets.walk;
            if (motion.moving) this.wildlifeRow = wildlifeDirection(motion.dx, motion.dy);
            c.save();
            c.translate(p.x, p.y - lift);
            if (sheet?.image.complete && sheet.image.naturalWidth) {
                const meta = sheet.meta,
                    scale = 1.9;
                const elapsed =
                    ((Date.now() + (this.clockOffset || 0)) / 1000 - sighting.inicio) *
                    1000 *
                    (runner && motion.moving ? 1.7 : 1);
                const frame = wildlifeFrame(meta, elapsed, this.reduced.matches);
                const row = meta.directions === 1 ? 0 : this.wildlifeRow || 0;
                c.imageSmoothingEnabled = false;
                c.drawImage(
                    sheet.image,
                    frame * meta.width,
                    row * meta.height,
                    meta.width,
                    meta.height,
                    (-meta.width * scale) / 2,
                    -meta.height * scale * 0.8,
                    meta.width * scale,
                    meta.height * scale
                );
            } else if (sprite?.fallback?.complete && sprite.fallback.naturalWidth) {
                c.scale(this.wildlifeFacing || 1, 1 + stride * 0.035);
                c.rotate(runner ? stride * 0.035 : stride * 0.012);
                c.drawImage(sprite.fallback, -32, -57, 64, 64);
            } else {
                this.ellipse(0, -15, 13, 15, '#a2e5a2');
                this.ellipse(4, -22, 3, 3, '#244b44');
            }
            c.restore();
            c.save();
            c.font = '600 11px Outfit,sans-serif';
            c.textAlign = 'center';
            const startled = Date.now() < (this.wildlifeReactionUntil || 0);
            const label =
                sighting.modo === 'huyendo'
                    ? '¡Huyendo!'
                    : startled
                      ? '¡Con cuidado!'
                      : sighting.pokemon.nombre;
            const width = c.measureText(label).width + 18;
            c.fillStyle = sighting.modo === 'huyendo' ? '#743e31ed' : '#142b3fed';
            c.fillRect(p.x - width / 2, p.y - 78, width, 18);
            c.fillStyle = sighting.modo === 'huyendo' ? '#ffd0ad' : '#baf1c5';
            c.fillText(label, p.x, p.y - 65);
            c.restore();
        }
        markerAt(x, y) {
            const rect = this.canvas.getBoundingClientRect();
            const wx = (x - rect.left - this.width / 2) / this.zoom + this.camera.x;
            const wy = (y - rect.top - this.height * 0.54) / this.zoom + this.camera.y;
            return this.map.puntos.find((point, i) => {
                const p = project(...point);
                return (
                    (!this.sites[i] || this.sites[i] <= Date.now() / 1000) &&
                    Math.abs(wx - p.x) <= 18 &&
                    wy >= p.y - 54 &&
                    wy <= p.y - 10
                );
            });
        }
        setPosition(position) {
            this.actor = [...position];
            this.destination = [...position];
            this.path = [];
            this.pathDurations = [];
            this.segment = null;
            for (const end of this.routeEnds || []) end.resolve();
            this.routeEnds = [];
            this.completedSteps = this.scheduledSteps = 0;
        }
        previewStep(from, to, stepMs) {
            return this.travel([from, to], stepMs);
        }
        async travel(path, stepMs = this.map.paso_ms || 180) {
            if (!path?.length) return;
            this.overviewMode = false;
            this.manualCamera = false;
            this.busy = true;
            this.stepMs = stepMs;
            this.routeEnds ??= [];
            this.pathDurations ??= [];
            this.completedSteps ??= 0;
            this.scheduledSteps ??= 0;
            if (this.reduced.matches) {
                await new Promise((resolve) => setTimeout(resolve, (path.length - 1) * stepMs));
                this.setPosition(path.at(-1));
                this.busy = false;
                return;
            }
            this.path.push(...path.slice(1));
            this.pathDurations.push(...path.slice(1).map(() => stepMs));
            this.scheduledSteps += path.length - 1;
            if (path.length > 1)
                await new Promise((resolve) =>
                    this.routeEnds.push({ step: this.scheduledSteps, resolve })
                );
            this.busy = Boolean(this.path.length || this.segment);
        }
        changeZoom(delta) {
            this.zoom = Math.max(0.55, Math.min(1.65, this.zoom + delta));
        }
        center() {
            this.overviewMode = false;
            this.manualCamera = false;
            this.zoom = innerWidth < 600 ? 0.95 : 1.2;
            this.camera = project(...this.actor);
        }
        overview() {
            this.overviewMode = true;
            const cols = this.map.terreno[0].length,
                rows = this.map.terreno.length;
            this.camera = project((cols - 1) / 2, (rows - 1) / 2);
            this.zoom = Math.max(
                0.1,
                Math.min(
                    this.width / ((cols + rows) * 36 + 100),
                    this.height / ((cols + rows) * 18 + 160)
                )
            );
        }
        tick(now) {
            this.frame = null;
            if (!this.active) return;
            const dt = Math.max(0, Math.min(100, now - this.last));
            this.last = now;
            let budget = dt;
            // Carry unused frame time through corners; never wait one extra frame per tile.
            while (budget > 0 && (this.path.length || this.segment)) {
                if (!this.segment) {
                    this.segment = {
                        from: [...this.actor],
                        to: this.path.shift(),
                        elapsed: 0,
                        duration: this.pathDurations?.shift() || this.stepMs || 180,
                    };
                    this.facing = this.segment.to.map((v, i) => v - this.segment.from[i]);
                }
                const part = this.segment;
                const amount = Math.min(budget, part.duration - part.elapsed);
                part.elapsed += amount;
                budget -= amount;
                const t = Math.min(1, part.elapsed / part.duration);
                this.actor = this.reduced.matches
                    ? [...part.to]
                    : part.from.map((v, i) => v + (part.to[i] - v) * t);
                if (t === 1) {
                    this.segment = null;
                    this.completedSteps = (this.completedSteps || 0) + 1;
                    while (this.routeEnds?.length && this.routeEnds[0].step <= this.completedSteps)
                        this.routeEnds.shift().resolve();
                    if (!this.path.length) {
                        this.arrival?.();
                        this.arrival = null;
                    }
                }
            }
            const dest =
                this.overviewMode || this.manualCamera ? this.camera : project(...this.actor);
            const follow = this.reduced.matches ? 1 : 1 - Math.exp(-dt / 85);
            this.camera.x += (dest.x - this.camera.x) * follow;
            this.camera.y += (dest.y - this.camera.y) * follow;
            const moving = Boolean(this.segment || this.path.length);
            this.trainerMotion = this.reduced.matches
                ? 0
                : (this.trainerMotion || 0) +
                  ((moving ? 1 : 0) - (this.trainerMotion || 0)) * (1 - Math.exp(-dt / 70));
            this.draw(now);
            this.lastDraw = now;
            this.frame = requestAnimationFrame((t) => this.tick(t));
        }
        ellipse(x, y, rx, ry, color) {
            const c = this.ctx;
            c.fillStyle = color;
            c.beginPath();
            c.ellipse(x, y, rx, ry, 0, 0, Math.PI * 2);
            c.fill();
        }
        polygon(points, color, stroke = null) {
            const c = this.ctx;
            c.beginPath();
            points.forEach(([x, y], i) => (i ? c.lineTo(x, y) : c.moveTo(x, y)));
            c.closePath();
            c.fillStyle = color;
            c.fill();
            if (stroke) {
                c.strokeStyle = stroke;
                c.stroke();
            }
        }
        diamond(x, y, color) {
            this.polygon(
                [
                    [x, y - 18],
                    [x + 36, y],
                    [x, y + 18],
                    [x - 36, y],
                ],
                color
            );
        }
        buildGround() {
            const original = this.ctx;
            this.ground = document.createElement('canvas');
            const cols = this.map.terreno[0].length,
                rows = this.map.terreno.length;
            this.groundBounds = {
                x: -rows * 36 - 90,
                y: -130,
                w: (cols + rows) * 36 + 180,
                h: (cols + rows) * 18 + 260,
            };
            this.ground.width = Math.ceil(this.groundBounds.w);
            this.ground.height = Math.ceil(this.groundBounds.h);
            this.ctx = this.ground.getContext('2d');
            const c = this.ctx,
                grid = this.map.terreno;
            c.translate(-this.groundBounds.x, -this.groundBounds.y);
            const sea = c.createLinearGradient(-700, 0, 800, 700);
            sea.addColorStop(0, '#62b9bd');
            sea.addColorStop(0.5, '#3b94a7');
            sea.addColorStop(1, '#2b718d');
            c.fillStyle = sea;
            c.fillRect(
                this.groundBounds.x,
                this.groundBounds.y,
                this.groundBounds.w,
                this.groundBounds.h
            );
            for (let y = 0; y < grid.length; y++)
                for (let x = 0; x < grid[y].length; x++) {
                    if (!grid[y][x]) continue;
                    const p = project(x, y);
                    this.ellipse(p.x, p.y + 3, 46, 24, '#b9c99b55');
                }
            for (let y = 0; y < grid.length; y++)
                for (let x = 0; x < grid[y].length; x++) {
                    const tile = grid[y][x];
                    if (!tile) continue;
                    const p = project(x, y),
                        n = noise(x, y);
                    if (grid[y + 1]?.[x] === 0)
                        this.polygon(
                            [
                                [p.x - 36, p.y],
                                [p.x, p.y + 18],
                                [p.x, p.y + 26],
                                [p.x - 36, p.y + 8],
                            ],
                            '#7e8a5b'
                        );
                    if (grid[y]?.[x + 1] === 0)
                        this.polygon(
                            [
                                [p.x, p.y + 18],
                                [p.x + 36, p.y],
                                [p.x + 36, p.y + 8],
                                [p.x, p.y + 26],
                            ],
                            '#586d4e'
                        );
                    this.diamond(
                        p.x,
                        p.y,
                        tile === 4
                            ? '#ae8958'
                            : tile === 2
                              ? '#edbd7d'
                              : ['#67b77b', '#75c387', '#86cc8b', '#59a978'][Math.floor(n * 4)]
                    );
                    if ([1, 3, 5].includes(tile)) {
                        for (let i = 0; i < 28; i++) {
                            const dx = (noise(x * 31 + i, y) - 0.5) * 68,
                                dy = (noise(y * 13 + i, x) - 0.5) * 32;
                            if (Math.abs(dx) / 36 + Math.abs(dy) / 18 > 0.94) continue;
                            this.ellipse(
                                p.x + dx,
                                p.y + dy,
                                1.5 + noise(i + x, y) * 3,
                                0.5 + noise(i + y, x),
                                i % 3 ? '#b3c48248' : '#65865035'
                            );
                        }
                        if (tile === 5)
                            for (let i = 0; i < 8; i++) {
                                const gx = p.x + (noise(x + i, y) - 0.5) * 46,
                                    gy = p.y + (noise(y + i, x) - 0.5) * 12;
                                c.strokeStyle = '#587e45';
                                c.lineWidth = 1.6;
                                c.beginPath();
                                c.moveTo(gx - 3, gy - 3);
                                c.lineTo(gx, gy + 2);
                                c.lineTo(gx + 3, gy - 6);
                                c.stroke();
                            }
                        if (n > 0.67) this.flowers(p.x + 7, p.y, n);
                    } else if (tile === 2) {
                        for (let i = 0; i < 7; i++)
                            this.ellipse(
                                p.x + (noise(i + x, y) - 0.5) * 40,
                                p.y + (noise(i + y, x) - 0.5) * 12,
                                0.7,
                                0.5,
                                '#9d8c6250'
                            );
                    } else if (tile === 4) {
                        c.strokeStyle = '#e1bd84';
                        c.lineWidth = 2;
                        for (let i = -24; i < 30; i += 8) {
                            c.beginPath();
                            c.moveTo(p.x + i, p.y - 11 + Math.abs(i) * 0.25);
                            c.lineTo(p.x + i, p.y + 11 - Math.abs(i) * 0.25);
                            c.stroke();
                        }
                    }
                }
            this.ctx = original;
        }
        flowers(x, y, n) {
            const c = this.ctx;
            for (let i = 0; i < 5; i++) {
                const fx = x + (i - 2) * 3,
                    fy = y + Math.sin(i * 3) * 3;
                c.strokeStyle = '#66864a';
                c.lineWidth = 1;
                c.beginPath();
                c.moveTo(fx, fy + 4);
                c.lineTo(fx, fy - 2);
                c.stroke();
                this.ellipse(fx, fy - 2, 2.3, 1.7, n > 0.85 ? '#e8d4dc' : '#f6e4a7');
                this.ellipse(fx, fy - 2, 0.7, 0.7, '#cc9c55');
            }
        }
        water(clock) {
            const c = this.ctx,
                grid = this.map.terreno;
            for (let y = 0; y < grid.length; y++)
                for (let x = 0; x < grid[y].length; x++) {
                    if (grid[y][x] !== 0 || !this.inView(x, y) || noise(x, y) < 0.3) continue;
                    const p = project(x, y),
                        off = Math.sin(clock / 1900 + x + y) * 5;
                    c.strokeStyle = '#c7ead13a';
                    c.lineWidth = 1.2;
                    c.beginPath();
                    c.moveTo(p.x - 13 + off, p.y);
                    c.bezierCurveTo(p.x - 4, p.y + 3, p.x + 4, p.y - 3, p.x + 13 + off, p.y);
                    c.stroke();
                    if (grid[y]?.[x + 1] || grid[y + 1]?.[x]) {
                        this.ellipse(p.x + 8, p.y + 3, 4, 1.5, '#a0bda279');
                    }
                }
        }
        bridge(x, y) {
            const p = project(x, y),
                c = this.ctx;
            for (const side of [-1, 1]) {
                c.strokeStyle = '#866940';
                c.lineWidth = 2.5;
                c.beginPath();
                c.moveTo(p.x - 31, p.y + side * 9 - 15);
                c.lineTo(p.x + 31, p.y - side * 9 - 15);
                c.stroke();
                for (const end of [-1, 1]) {
                    c.fillStyle = '#a68452';
                    c.fillRect(p.x + end * 29 - 1, p.y - side * end * 9 - 18, 3, 20);
                    this.ellipse(p.x + end * 29, p.y - side * end * 9 - 18, 2.5, 1.5, '#d7b881');
                }
            }
        }
        scenery(x, y, time) {
            const p = project(x, y),
                c = this.ctx,
                n = noise(x, y);
            if (n > 0.5) {
                this.ellipse(p.x + 4, p.y + 3, 15, 5, '#31543b33');
                this.polygon(
                    [
                        [p.x - 12, p.y],
                        [p.x - 9, p.y - 9],
                        [p.x + 1, p.y - 14],
                        [p.x + 12, p.y - 6],
                        [p.x + 13, p.y + 2],
                        [p.x, p.y + 7],
                    ],
                    '#8f9f8a'
                );
                this.polygon(
                    [
                        [p.x - 12, p.y],
                        [p.x - 9, p.y - 9],
                        [p.x + 1, p.y - 14],
                        [p.x, p.y - 2],
                    ],
                    '#bdc5a1'
                );
                this.ellipse(p.x - 2, p.y - 2, 9, 2, '#719061');
            } else {
                for (let i = -1; i <= 1; i++)
                    this.ellipse(
                        p.x + i * 6,
                        p.y - 5 - Math.abs(i) * 2,
                        8,
                        6,
                        i === 0 ? '#8db36d' : '#658d56'
                    );
                this.flowers(p.x, p.y - 7, 0.9);
                if (!this.reduced.matches) {
                    const b = Math.sin(time / 300 + x) * 3;
                    this.ellipse(p.x + 8, p.y - 23 + b, 3, 1.2, '#f8d581');
                }
            }
        }
        minimap() {
            const mini = document.getElementById('exploreMinimap');
            if (!mini) return;
            const c = mini.getContext('2d'),
                grid = this.map.terreno;
            const scale = Math.min(
                132 / ((grid.length + grid[0].length) * 36),
                80 / ((grid.length + grid[0].length) * 18)
            );
            const originX = 72 - (grid[0].length - grid.length) * 18 * scale;
            c.clearRect(0, 0, 144, 100);
            c.fillStyle = '#101c35';
            c.fillRect(0, 0, 144, 100);
            for (let y = 0; y < grid.length; y++)
                for (let x = 0; x < grid[y].length; x++) {
                    const p = project(x, y);
                    c.fillStyle =
                        this.visibility(x, y) === 0
                            ? '#0b1428'
                            : grid[y][x] === 0
                              ? '#3d7e80'
                              : grid[y][x] === 2
                                ? '#c4b58b'
                                : '#87a166';
                    c.fillRect(
                        originX + p.x * scale,
                        10 + p.y * scale,
                        Math.max(2, 72 * scale),
                        Math.max(1.5, 36 * scale)
                    );
                }
            this.map.puntos.forEach((point, i) => {
                const p = project(...point);
                c.fillStyle = this.sites[i] > Date.now() / 1000 ? '#57785c' : '#f5d88d';
                c.beginPath();
                c.arc(originX + p.x * scale, 11 + p.y * scale, 2, 0, Math.PI * 2);
                c.fill();
            });
            if (
                this.wildlife &&
                this.wildlife.vence > (Date.now() + (this.clockOffset || 0)) / 1000
            ) {
                const [x, y] = this.wildlifePosition();
                if (this.visibility(Math.round(x), Math.round(y)) === 2) {
                    const point = project(x, y);
                    c.beginPath();
                    c.fillStyle = this.isWildlifeHidden() ? '#ffe16a' : '#a2e5a2';
                    c.arc(originX + point.x * scale, 11 + point.y * scale, 3, 0, Math.PI * 2);
                    c.fill();
                }
            }
            const p = project(...this.actor);
            c.fillStyle = '#ffffff';
            c.strokeStyle = '#263c35';
            c.lineWidth = 1;
            c.beginPath();
            c.arc(originX + p.x * scale, 11 + p.y * scale, 3, 0, Math.PI * 2);
            c.fill();
            c.stroke();
        }
        draw(time) {
            const c = this.ctx,
                grid = this.map.terreno,
                calm = this.reduced.matches,
                clock = calm ? 0 : time;
            c.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
            const bg = c.createLinearGradient(0, 0, this.width, this.height);
            bg.addColorStop(0, '#67b5bd');
            bg.addColorStop(1, '#397c9b');
            c.fillStyle = bg;
            c.fillRect(0, 0, this.width, this.height);
            c.translate(this.width / 2, this.height * 0.54);
            c.scale(this.zoom, this.zoom);
            c.translate(-this.camera.x, -this.camera.y);
            if (!this.ground) this.buildGround();
            const ground = this.groundBounds;
            c.drawImage(this.ground, ground.x, ground.y, ground.w, ground.h);
            this.water(clock);
            if (this.hover && walkable(grid, this.hover.x, this.hover.y) && !this.busy) {
                const p = project(this.hover.x, this.hover.y);
                this.diamond(p.x, p.y, '#fff3b53b');
                c.strokeStyle = '#fff1b4bb';
                c.lineWidth = 1.5;
                c.stroke();
            }
            if (this.queued) {
                const target = project(...this.queued);
                this.ellipse(target.x, target.y, 14, 7, '#fff2c890');
            }
            for (const pos of this.path) {
                const p = project(...pos);
                this.ellipse(p.x, p.y, 2, 1.5, '#ffedb9b3');
            }
            const objects = [];
            if (!this.landmarks) {
                const blocked = [];
                grid.forEach((row, y) =>
                    row.forEach((tile, x) => {
                        if (tile === 3) blocked.push([x, y]);
                    })
                );
                const nearest = (tx, ty) =>
                    blocked.reduce(
                        (best, p) =>
                            !best ||
                            Math.hypot(p[0] - tx, p[1] - ty) <
                                Math.hypot(best[0] - tx, best[1] - ty)
                                ? p
                                : best,
                        null
                    );
                this.landmarks = [nearest(4, 6), nearest(20, 11)].filter(Boolean);
            }
            for (let y = 0; y < grid.length; y++)
                for (let x = 0; x < grid[y].length; x++)
                    if (grid[y][x] === 3 && this.inView(x, y) && this.visibility(x, y) > 0) {
                        const landmark = this.landmarks.findIndex((p) => p[0] === x && p[1] === y);
                        objects.push({
                            depth: x + y,
                            draw: () =>
                                landmark >= 0
                                    ? this.landmark(x, y, landmark, clock)
                                    : this.tree(x, y),
                        });
                    }
            objects.push({ depth: 16, draw: () => this.camp(4, 12) });
            for (let y = 1; y < grid.length - 1; y++)
                for (let x = 1; x < grid[y].length - 1; x++) {
                    if (
                        [1, 5].includes(grid[y][x]) &&
                        this.inView(x, y) &&
                        this.visibility(x, y) > 0 &&
                        noise(x + 19, y) > 0.88
                    )
                        objects.push({ depth: x + y - 0.1, draw: () => this.scenery(x, y, clock) });
                }
            if (this.visibility(15, 9) > 0)
                objects.push({ depth: 24.1, draw: () => this.bridge(15, 9) });
            this.map.puntos.forEach((point, i) =>
                objects.push({
                    depth: point[0] + point[1] + 0.05,
                    draw: () => this.marker(point, i, clock),
                })
            );
            if (
                this.wildlife &&
                this.wildlife.vence > (Date.now() + (this.clockOffset || 0)) / 1000
            ) {
                const motion = wildlifeMotion(
                    this.wildlife,
                    (Date.now() + (this.clockOffset || 0)) / 1000
                );
                if (
                    this.visibility(Math.round(motion.x), Math.round(motion.y)) === 2 &&
                    this.inView(motion.x, motion.y)
                )
                    objects.push({
                        depth: motion.x + motion.y + 0.1,
                        draw: () => this.drawWildlife(motion, clock),
                    });
            }
            objects.push({ depth: 1000, draw: () => this.fog() });
            objects.push({
                depth: this.actor[0] + this.actor[1] + 0.2,
                draw: () => this.trainer(clock),
            });
            objects.sort((a, b) => a.depth - b.depth).forEach((o) => o.draw());
            for (const region of this.map.regiones || []) {
                if (this.visibility(region.x, region.y) !== 2 || !this.inView(region.x, region.y))
                    continue;
                const p = project(region.x, region.y);
                c.save();
                c.font = '600 12px Outfit, sans-serif';
                c.textAlign = 'center';
                c.lineJoin = 'round';
                c.lineWidth = 4;
                c.strokeStyle = '#e7efdc';
                c.fillStyle = '#234a3e';
                c.strokeText(region.nombre, p.x, p.y - 60);
                c.fillText(region.nombre, p.x, p.y - 60);
                c.restore();
            }
            if (!calm)
                for (let i = 0; i < 12; i++) {
                    const p = project(3 + ((i * 7.9) % 17), 3 + ((i * 4.3) % 13));
                    this.ellipse(
                        p.x + Math.sin(clock / 2500 + i) * 20,
                        p.y - 15 + Math.sin(clock / 1800 + i) * 10,
                        1.4,
                        1.4,
                        '#fff6ba88'
                    );
                }
            c.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
            const vignette = c.createRadialGradient(
                this.width / 2,
                this.height / 2,
                this.width * 0.2,
                this.width / 2,
                this.height / 2,
                Math.max(this.width, this.height) * 0.75
            );
            vignette.addColorStop(0, '#0c372300');
            vignette.addColorStop(1, '#163c321c');
            c.fillStyle = vignette;
            c.fillRect(0, 0, this.width, this.height);
            this.atmosphere(clock);
            if (time - (this.lastMinimap ?? -Infinity) >= 120) {
                this.minimap();
                this.lastMinimap = time;
            }
        }
        landmark(x, y, kind, time) {
            const p = project(x, y),
                c = this.ctx;
            c.save();
            c.translate(p.x, p.y);
            this.ellipse(4, 4, 35, 12, '#21493655');
            if (kind === 0) {
                this.polygon(
                    [
                        [-26, 0],
                        [-26, -38],
                        [7, -48],
                        [30, -32],
                        [30, 9],
                        [0, 19],
                    ],
                    '#b5a383'
                );
                this.polygon(
                    [
                        [0, 19],
                        [0, -29],
                        [30, -32],
                        [30, 9],
                    ],
                    '#8c826d'
                );
                this.polygon(
                    [
                        [-34, -34],
                        [0, -70],
                        [37, -35],
                        [0, -20],
                    ],
                    '#ba684d',
                    '#704d3b'
                );
                this.polygon(
                    [
                        [-34, -34],
                        [0, -70],
                        [0, -20],
                    ],
                    '#e3a071'
                );
                c.fillStyle = '#4c6255';
                c.fillRect(-16, -23, 10, 15);
                c.fillStyle = '#f7dc9a';
                c.fillRect(-14, -21, 6, 11);
                c.fillStyle = '#465442';
                c.fillRect(11, -18, 11, 25);
                c.fillStyle = '#bfac80';
                c.fillRect(-5, -72, 7, 17);
                if (!this.reduced.matches)
                    for (let i = 0; i < 3; i++) {
                        const t = (time / 1600 + i / 3) % 1;
                        this.ellipse(
                            -2 + t * 10,
                            -75 - t * 25,
                            4 + t * 5,
                            3 + t * 4,
                            `rgba(232,231,206,${(1 - t) * 0.25})`
                        );
                    }
            } else {
                for (const dx of [-23, 17]) {
                    this.polygon(
                        [
                            [dx, 3],
                            [dx, -46],
                            [dx + 12, -48],
                            [dx + 12, 0],
                        ],
                        '#a8b8a4',
                        '#738e7b'
                    );
                    c.fillStyle = '#ced2b1';
                    c.fillRect(dx - 2, -49, 16, 7);
                    c.fillStyle = '#61865d';
                    c.fillRect(dx, -28, 4, 20);
                }
                this.polygon(
                    [
                        [-25, -47],
                        [-12, -64],
                        [12, -64],
                        [31, -47],
                        [18, -42],
                        [8, -53],
                        [-5, -53],
                        [-14, -42],
                    ],
                    '#d0cfac',
                    '#7d9781'
                );
                this.flowers(-12, 9, 0.9);
            }
            c.font = '600 10px sans-serif';
            c.textAlign = 'center';
            c.fillStyle = '#143b30e8';
            c.fillRect(-40, 23, 80, 17);
            c.fillStyle = '#eddda6';
            c.fillText(kind === 0 ? 'Refugio del Alba' : 'Ruinas del río', 0, 35);
            c.textAlign = 'left';
            c.restore();
        }
        tree(x, y) {
            this.treeSprites ??= new Map();
            const key = Math.floor(noise(x, y) * 14),
                p = project(x, y);
            if (!this.treeSprites.has(key)) {
                const sprite = document.createElement('canvas');
                sprite.width = 240;
                sprite.height = 240;
                const original = this.ctx;
                this.ctx = sprite.getContext('2d');
                this.ctx.scale(2, 2);
                this.ctx.translate(60, 100);
                this.paintTree(key);
                this.ctx = original;
                this.treeSprites.set(key, sprite);
            }
            this.ctx.drawImage(this.treeSprites.get(key), p.x - 60, p.y - 100, 120, 120);
        }
        paintTree(seed) {
            const c = this.ctx,
                n = noise(seed, 9),
                size = 24 + n * 10;
            this.ellipse(12, 7, size * 1.05, size * 0.37, '#24482b35');
            this.polygon(
                [
                    [-4, 2],
                    [5, 0],
                    [6, -41],
                    [-4, -44],
                ],
                '#6b6540'
            );
            this.polygon(
                [
                    [-4, 2],
                    [-1, -2],
                    [0, -43],
                    [-4, -44],
                ],
                '#b29a65'
            );
            if (seed % 4 === 0) {
                for (let i = 0; i < 3; i++) {
                    const y = -21 - i * 19,
                        r = 30 - i * 7;
                    this.polygon(
                        [
                            [-r, y + 8],
                            [0, y - 36],
                            [r, y + 8],
                            [0, y + 17],
                        ],
                        i === 2 ? '#68986a' : '#48775c'
                    );
                    this.polygon(
                        [
                            [-r, y + 8],
                            [0, y - 36],
                            [0, y + 17],
                        ],
                        i === 2 ? '#9cbd83' : '#6c9a73'
                    );
                }
            } else {
                const leaf = (x, y, r, color) => {
                    const g = c.createRadialGradient(x - r * 0.35, y - r * 0.5, 1, x, y, r);
                    g.addColorStop(0, color);
                    g.addColorStop(1, '#3e6849');
                    this.ellipse(x, y, r, r * 0.8, g);
                };
                leaf(-17, -35, size * 0.65, '#779e61');
                leaf(17, -34, size * 0.75, '#73985c');
                leaf(0, -48, size, '#93b56d');
                leaf(-14, -61, size * 0.64, '#b2c77c');
                leaf(12, -64, size * 0.64, '#a3c077');
                leaf(0, -73, size * 0.6, '#bbce86');
                for (let i = 0; i < 15; i++) {
                    const x = (noise(i, seed) - 0.5) * size * 1.5,
                        y = -40 - noise(seed, i) * 35;
                    this.ellipse(x, y, 2, 1, i % 2 ? '#d5dda14d' : '#345c3b25');
                }
            }
            this.ellipse(-2, 4, 9, 3, '#86a268');
        }
        camp(x, y) {
            const p = project(x, y),
                c = this.ctx;
            this.ellipse(p.x, p.y + 3, 44, 15, '#2c4c3440');
            this.polygon(
                [
                    [p.x - 34, p.y],
                    [p.x - 8, p.y - 47],
                    [p.x + 31, p.y - 28],
                    [p.x + 9, p.y + 14],
                ],
                '#e3c37b'
            );
            this.polygon(
                [
                    [p.x - 34, p.y],
                    [p.x - 8, p.y - 47],
                    [p.x + 9, p.y + 14],
                ],
                '#f4d995'
            );
            this.polygon(
                [
                    [p.x - 21, p.y + 2],
                    [p.x - 8, p.y - 27],
                    [p.x - 1, p.y + 10],
                ],
                '#344d3c'
            );
            c.strokeStyle = '#b39666';
            c.lineWidth = 2;
            c.beginPath();
            c.moveTo(p.x - 9, p.y - 49);
            c.lineTo(p.x + 11, p.y + 18);
            c.stroke();
            c.fillStyle = '#516644';
            c.fillRect(p.x + 32, p.y - 22, 3, 29);
            this.polygon(
                [
                    [p.x + 21, p.y - 26],
                    [p.x + 52, p.y - 26],
                    [p.x + 52, p.y - 13],
                    [p.x + 21, p.y - 13],
                ],
                '#e6d9ad'
            );
            c.fillStyle = '#5e704c';
            c.font = '7px sans-serif';
            c.fillText('ALBA', p.x + 26, p.y - 17);
        }
        marker(point, i, time) {
            const p = project(...point),
                ready = !this.sites[i] || this.sites[i] <= Date.now() / 1000;
            if (!ready) {
                this.ellipse(p.x, p.y, 12, 5, '#eddd9d50');
                return;
            }
            const bob = Math.sin(time / 650 + i) * 3;
            this.ellipse(p.x, p.y, 17, 7, '#f4db8a40');
            this.ellipse(p.x, p.y, 10, 4, '#e8da9b60');
            this.polygon(
                [
                    [p.x, p.y - 20 + bob],
                    [p.x - 9, p.y - 33 + bob],
                    [p.x, p.y - 46 + bob],
                    [p.x + 9, p.y - 33 + bob],
                ],
                '#ffe2a0',
                '#6b6d3c'
            );
            const c = this.ctx;
            c.font = 'bold 13px sans-serif';
            c.fillStyle = '#5e683b';
            c.textAlign = 'center';
            c.fillText('?', p.x, p.y - 28 + bob);
            c.textAlign = 'left';
        }
        trainer(time) {
            const c = this.ctx,
                p = project(...this.actor),
                moving = Boolean(this.segment || this.path.length);
            if (this.trainerSprite.complete && this.trainerSprite.naturalWidth) {
                const direction = wildlifeDirection(...this.facing);
                const progress =
                    this.completedSteps +
                    (this.segment ? this.segment.elapsed / this.segment.duration : 0);
                const phase = progress * 2,
                    frame = Math.floor(phase) % 4;
                const fraction = phase - Math.floor(phase);
                const mix = fraction * fraction * (3 - 2 * fraction);
                const motion = this.trainerMotion || 0;
                const sheet = this.trainerSprite,
                    sw = sheet.naturalWidth / 4,
                    sh = sheet.naturalHeight / 8;
                this.ellipse(p.x, p.y + 3, 12, 5, '#183e3566');
                const layer = this.trainerLayer,
                    pose = layer.getContext('2d');
                pose.clearRect(0, 0, 172, 172);
                pose.globalCompositeOperation = 'lighter';
                for (const [column, alpha] of [
                    [frame, (1 - mix) * motion],
                    [(frame + 1) % 4, mix * motion],
                    [1, 1 - motion],
                ]) {
                    if (alpha < 0.001) continue;
                    pose.globalAlpha = alpha;
                    pose.drawImage(sheet, column * sw, direction * sh, sw, sh, 0, 0, 172, 172);
                }
                pose.globalAlpha = 1;
                pose.globalCompositeOperation = 'source-over';
                const sway = Math.sin(progress * Math.PI) * 0.8 * motion;
                const bob = Math.pow(Math.sin(progress * Math.PI), 2) * 1.2 * motion;
                c.drawImage(layer, p.x - 43 + sway, p.y - 76 - bob, 86, 86);
                return;
            }
            const stride =
                (this.completedSteps || 0) +
                (this.segment ? this.segment.elapsed / this.segment.duration : 0);
            const step = moving ? Math.sin(stride * Math.PI) * 4.5 : 0;
            const facing = this.facing || [1, 0],
                v = project(...facing),
                back = v.y < 0;
            this.ellipse(p.x + 2, p.y + 4, 12, 5, '#183e3566');
            if (moving && !this.reduced.matches)
                this.ellipse(p.x - 9, p.y + 7, 3 + Math.abs(step), 1.5, '#e8d9b457');
            c.save();
            c.translate(p.x, p.y - (moving ? Math.abs(step) * 0.3 : 0));
            c.scale(1.3, 1.3);
            if (v.x < 0) c.scale(-1, 1);
            c.lineWidth = 5;
            c.lineCap = 'round';
            c.strokeStyle = '#314656';
            c.beginPath();
            c.moveTo(-4, -12);
            c.lineTo(-4 + step, -1);
            c.moveTo(4, -12);
            c.lineTo(4 - step, 0);
            c.stroke();
            this.ellipse(-4 + step, 1, 4, 2.3, '#263c40');
            this.ellipse(5 - step, 1, 4, 2.3, '#263c40');
            this.polygon(
                [
                    [-9, -29],
                    [7, -29],
                    [9, -11],
                    [-8, -11],
                ],
                '#487b86'
            );
            this.polygon(
                [
                    [-8, -28],
                    [-2, -27],
                    [-3, -12],
                    [-8, -11],
                ],
                '#6ca1a2'
            );
            c.strokeStyle = '#e8bd8e';
            c.lineWidth = 4;
            c.beginPath();
            c.moveTo(-9, -27);
            c.lineTo(-11 - step * 0.6, -16);
            c.moveTo(8, -26);
            c.lineTo(11 + step * 0.6, -16);
            c.stroke();
            this.ellipse(0, -36, 8, 9, '#f1c79a');
            if (back) {
                this.ellipse(0, -32, 7, 3, '#705438');
                this.ellipse(-1, -21, 7, 9, '#c7a66c');
                this.polygon(
                    [
                        [-5, -26],
                        [4, -26],
                        [4, -18],
                        [-5, -18],
                    ],
                    '#e2c58c'
                );
            } else {
                this.ellipse(4, -36, 1.1, 1.3, '#314448');
                this.ellipse(8, -33, 2.3, 1.8, '#edc292');
            }
            this.ellipse(0, -43, 10, 5, '#c95742');
            this.ellipse(4, -40, 10, 2.4, '#ec9470');
            c.strokeStyle = '#f2d7ab';
            c.lineWidth = 2;
            c.beginPath();
            c.moveTo(-4, -44);
            c.lineTo(4, -44);
            c.stroke();
            c.restore();
            c.font = '600 11px sans-serif';
            c.textAlign = 'center';
            c.fillStyle = '#102e2be8';
            c.fillRect(p.x - 17, p.y - 83, 34, 19);
            c.fillStyle = '#ffe4a0';
            c.fillText('Tú', p.x, p.y - 70);
            c.textAlign = 'left';
        }
        destroy() {
            this.active = false;
            cancelAnimationFrame(this.frame);
            this.arrival?.();
            this.resizeObserver.disconnect();
            this.canvas.removeEventListener('pointerdown', this.dragStart);
            this.canvas.removeEventListener('pointerup', this.dragEnd);
            this.canvas.removeEventListener('pointercancel', this.dragCancel);
            this.canvas.removeEventListener('click', this.click);
            this.canvas.removeEventListener('pointermove', this.pointer);
            this.canvas.removeEventListener('pointerleave', this.leave);
            document.removeEventListener('visibilitychange', this.onVisibilityChange);
        }
    }
    return {
        World,
        project,
        unproject,
        walkable,
        environmentAt,
        wildlifeMotion,
        wildlifeFrame,
        wildlifeDirection,
    };
})();
