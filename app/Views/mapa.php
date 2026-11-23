<?= $this->extend('layout/template') ?>

<?= $this->section('content') ?>
<style>
    #mapa { height: 70vh; min-height: 360px; border-radius: .5rem; }
    .mapa-obal { position: relative; }
    /* Hláška při malém přiblížení, přes mapu nahoře uprostřed */
    #hlaska {
        position: absolute; top: .75rem; left: 50%; transform: translateX(-50%);
        z-index: 1000; pointer-events: none; max-width: calc(100% - 6rem);
    }
    /* Značky zastávek (divIcon s ikonou Font Awesome) */
    .znacka {
        display: flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 50%;
        color: #fff; border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,.4); font-size: 13px;
    }
    .znacka-bus  { background: #0d6efd; }
    .znacka-vlak { background: #c0392b; }
    .popup-odjezdy { font-size: .85rem; }
    .popup-odjezdy td { padding: .15rem .35rem; vertical-align: top; }
    .popup-odjezdy .cas { font-weight: 700; white-space: nowrap; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="mb-0"><i class="fa-solid fa-map-location-dot"></i> Mapa zastávek</h1>
    <div class="small text-body-secondary">
        <span class="znacka znacka-bus d-inline-flex align-middle"><i class="fa-solid fa-bus"></i></span> autobus
        <span class="znacka znacka-vlak d-inline-flex align-middle ms-2"><i class="fa-solid fa-train"></i></span> vlak
    </div>
</div>

<div class="mapa-obal">
    <div id="mapa" aria-label="Mapa zastávek"></div>
    <div id="hlaska" class="alert alert-info py-2 px-3 mb-0 shadow-sm d-none" role="status"></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('skripty') ?>
<link rel="stylesheet" href="<?= base_url('node_modules/leaflet/dist/leaflet.css') ?>">
<link rel="stylesheet" href="<?= base_url('node_modules/leaflet.markercluster/dist/MarkerCluster.css') ?>">
<link rel="stylesheet" href="<?= base_url('node_modules/leaflet.markercluster/dist/MarkerCluster.Default.css') ?>">
<script src="<?= base_url('node_modules/leaflet/dist/leaflet.js') ?>"></script>
<script src="<?= base_url('node_modules/leaflet.markercluster/dist/leaflet.markercluster.js') ?>"></script>
<script>
    // Nastavení z controlleru
    const MIN_ZOOM    = <?= json_encode($mapaMinZoom) ?>;
    const STRED       = <?= json_encode($stred) ?>; // [lat, lon] z odkazu na tabuli, jinak null
    const API_ZASTAVKY = <?= json_encode(site_url('api/zastavky')) ?>;
    const API_ODJEZDY  = <?= json_encode(site_url('api/odjezdy')) ?>;

    // Ochrana textu z dat (názvy jsou z cizího zdroje)
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (z) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[z]);

    // Mapa: střed ČR, nebo zastávka z odkazu
    const mapa = L.map('mapa').setView(STRED ?? [49.8, 15.5], STRED ? 16 : 7);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">přispěvatelé OpenStreetMap</a>',
    }).addTo(mapa);

    const cluster = L.markerClusterGroup({ disableClusteringAtZoom: 16 });
    mapa.addLayer(cluster);

    const ikony = {
        bus:  L.divIcon({ className: '', html: '<div class="znacka znacka-bus"><i class="fa-solid fa-bus"></i></div>', iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -14] }),
        vlak: L.divIcon({ className: '', html: '<div class="znacka znacka-vlak"><i class="fa-solid fa-train"></i></div>', iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -14] }),
    };

    const hlaska = document.getElementById('hlaska');
    function ukazHlasku(text) {
        hlaska.textContent = text;
        hlaska.classList.toggle('d-none', !text);
    }

    // Tlačítko „Najdi mě“
    const NajdiMe = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
            const btn = L.DomUtil.create('button', 'btn btn-light border shadow-sm');
            btn.type = 'button';
            btn.title = 'Najdi mě';
            btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i>';
            L.DomEvent.disableClickPropagation(btn);
            L.DomEvent.on(btn, 'click', () => mapa.locate({ setView: true, maxZoom: 16 }));
            return btn;
        },
    });
    mapa.addControl(new NajdiMe());
    mapa.on('locationerror', () => ukazHlasku('Polohu se nepodařilo zjistit.'));

    // Značky, které už jsou na mapě, podle klíče typ/slug
    const znacky = new Map();

    // Zastávky jen ve výřezu mapy (celkem jich je ~40 tisíc)
    let probihajici = null;
    async function nactiZastavky() {
        if (mapa.getZoom() < MIN_ZOOM) {
            cluster.clearLayers();
            znacky.clear();
            ukazHlasku('Přibližte mapu, aby se zobrazily zastávky.');
            return;
        }
        ukazHlasku('');

        // Starší požadavek zrušit, ať se značky nepřepíšou starými daty
        if (probihajici) probihajici.abort();
        probihajici = new AbortController();

        const b = mapa.getBounds();
        const parametry = new URLSearchParams({ s: b.getSouth(), w: b.getWest(), n: b.getNorth(), e: b.getEast() });
        try {
            const odpoved = await fetch(API_ZASTAVKY + '?' + parametry, { signal: probihajici.signal });
            if (!odpoved.ok) throw new Error(odpoved.status);
            const zastavky = await odpoved.json();

            // Jen rozdíl proti tomu, co už na mapě je. Kdyby se mazalo všechno (clearLayers),
            // zavřel by se popup: otevření popupu posune mapu, přijde moveend a značka by zmizela.
            const nove = new Set();
            const pridat = [];
            for (const z of zastavky) {
                const klic = z.typ + '/' + z.slug;
                nove.add(klic);
                if (!znacky.has(klic)) {
                    const znacka = L.marker([z.lat, z.lon], { icon: ikony[z.typ] ?? ikony.bus, title: z.nazev })
                        .bindPopup('Načítám…', { minWidth: 260 })
                        .on('click', (e) => nactiOdjezdy(e.target, z));
                    znacky.set(klic, znacka);
                    pridat.push(znacka);
                }
            }

            // Značky mimo nový výřez pryč, ale ne tu, která má otevřený popup
            const odebrat = [];
            for (const [klic, znacka] of znacky) {
                if (!nove.has(klic) && !znacka.isPopupOpen()) {
                    odebrat.push(znacka);
                    znacky.delete(klic);
                }
            }
            cluster.removeLayers(odebrat);
            cluster.addLayers(pridat);
        } catch (chyba) {
            if (chyba.name !== 'AbortError') ukazHlasku('Zastávky se nepodařilo načíst.');
        }
    }

    // Popup s 5 nejbližšími odjezdy
    async function nactiOdjezdy(znacka, z) {
        znacka.setPopupContent(`<strong>${esc(z.nazev)}</strong><br>Načítám…`);
        try {
            const odpoved = await fetch(`${API_ODJEZDY}/${encodeURIComponent(z.typ)}/${encodeURIComponent(z.slug)}`);
            if (!odpoved.ok) throw new Error(odpoved.status);
            const data = await odpoved.json();
            const dnes = new Date().toLocaleDateString('sv-SE'); // YYYY-MM-DD

            let html = `<strong>${esc(data.zastavka.nazev)}</strong>`;
            if (data.odjezdy.length === 0) {
                html += '<p class="my-2">Teď odsud nic nejede.</p>';
            } else {
                html += '<table class="popup-odjezdy my-2"><tbody>';
                for (const o of data.odjezdy) {
                    const za = o.zaMinut <= 0 ? 'odjíždí' : (o.zaMinut < 60 ? `${o.zaMinut} min` : `${Math.floor(o.zaMinut / 60)} h ${o.zaMinut % 60} min`);
                    html += `<tr>
                        <td class="cas">${o.datum !== dnes ? 'zítra ' : ''}${esc(o.cas)}</td>
                        <td>${esc(o.linka)}</td>
                        <td>${esc(o.cil)}</td>
                        <td class="text-nowrap">${esc(za)}</td>
                    </tr>`;
                }
                html += '</tbody></table>';
            }
            html += `<a href="${esc(data.url)}">Celá tabule →</a>`;
            znacka.setPopupContent(html);
        } catch (chyba) {
            znacka.setPopupContent(`<strong>${esc(z.nazev)}</strong><br>Odjezdy se nepodařilo načíst.`);
        }
    }

    mapa.on('moveend', nactiZastavky);
    nactiZastavky();
</script>
<?= $this->endSection() ?>
