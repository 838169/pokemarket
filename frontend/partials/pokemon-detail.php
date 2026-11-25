<div
    id="pokemonModal"
    class="pokemon-modal"
    role="dialog"
    aria-modal="true"
    aria-label="Detalles del Pokémon"
>
    <div class="modal-backdrop"></div>
    <section class="pokemon-detail">
        <button id="cerrarModal" class="modal-close" type="button" aria-label="Cerrar detalles">
            ×
        </button>
        <div class="detail-preview">
            <div class="detail-image-area">
                <div class="detail-art-heading"><span class="detail-edition">Ficha Pokémon</span><span id="detalleNivel" class="detail-level"></span></div>
                <span id="detalleNumeroArte" class="detail-art-number" aria-hidden="true"></span>
                <svg class="detail-pokeball" viewBox="0 0 200 200" fill="none" aria-hidden="true"><circle cx="100" cy="100" r="85"/><path d="M15 100h60m50 0h60"/><circle cx="100" cy="100" r="25"/><circle cx="100" cy="100" r="11"/></svg>
                <img id="detalleImagen" alt="" draggable="false" />
            </div>
            <section class="detail-ability-panel" aria-labelledby="detalleHabilidadesTitulo">
                <h3 id="detalleHabilidadesTitulo">Habilidades de la especie</h3>
                <div id="detalleHabilidades" class="detail-abilities"></div>
                <details class="detail-rules"><summary>Sobre las habilidades pasivas</summary><p class="market-footnote">
                    Cada Pokémon usa una sola habilidad. Las marcadas «En combate» tienen efecto real; las demás son informativas y no añaden recargo. Puedes elegir una compatible en el Hogar para la próxima batalla.
                </p></details>
                <div id="detalleInicio" class="detail-arrival"></div>
            </section>
        </div>
        <div class="detail-info">
            <div class="detail-heading-meta"><span id="detalleNumero" class="detail-number"></span><span id="detalleCategoria" class="detail-category"></span></div>
            <h2 id="detalleNombre">Detalles del Pokémon</h2>
            <div id="detalleTipos" class="detail-types"></div>
            <ul id="detalleFortalezas" class="detail-strengths" aria-label="Estadísticas base más altas"></ul>
            <div class="detail-stats">
                <div class="detail-stat">
                    <span>Altura</span><strong id="detalleAltura"></strong>
                </div>
                <div class="detail-stat"><span>Peso</span><strong id="detallePeso"></strong></div>
            </div>
            <div class="detail-section-heading"><h3>Estadísticas base</h3><span>Potencial de la especie</span></div>
            <div id="detalleCombate" class="market-stat-grid"></div>
            <section class="detail-weaknesses" aria-labelledby="detalleDebilidadesTitulo">
                <h3 id="detalleDebilidadesTitulo">Debilidades por tipo</h3>
                <ul id="detalleDebilidades" class="detail-weakness-list"></ul>
                <p class="detail-weakness-note">Daño recibido por tipo; no incluye habilidades pasivas.</p>
            </section>
            <button id="modalColeccion" class="detail-collection-button" type="button">
                Comprar
            </button>
        </div>
    </section>
</div>
