<?php
// partials/grafik_penjualan.php
// Self-contained partial grafik penjualan.
// Include di halaman mana pun yang sudah punya $pdoBarang.
// NO CDN — pakai Chart.js lokal.

// === Pastikan dependensi ada ===
if (!isset($pdoBarang)) {
    require_once BASE_PATH . 'database/db_barang.php';
}
if (!function_exists('buildGrafikDataLaporan')) {
    require_once BASE_PATH . 'database/query_grafik.php';
}

// === Ambil parameter tanggal dari GET ===
$grafikMulai   = $_GET['tgl_mulai']   ?? date('Y-m-d', strtotime('-6 days'));
$grafikSelesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// Validasi format tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $grafikMulai))   $grafikMulai   = date('Y-m-d', strtotime('-6 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $grafikSelesai)) $grafikSelesai = date('Y-m-d');

// === Build data grafik ===
$grafikData = buildGrafikDataLaporan($pdoBarang, $grafikMulai, $grafikSelesai);

// === URL halaman saat ini untuk form filter ===
$currentUrl = $_SERVER['PHP_SELF'] ?? '/';
?>

<!-- === CHART.JS LOKAL (NO CDN) === -->
<script src="<?= BASE_URL; ?>assets/js/chart.umd.js"></script>

<!-- === HEADER SECTION === -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
  <h5 class="fw-bold mb-0 text-dark">
    <i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Grafik Penjualan
  </h5>
  <a href="<?= BASE_URL; ?>kasir/laporan.php?tgl_mulai=<?= htmlspecialchars($grafikMulai); ?>&tgl_selesai=<?= htmlspecialchars($grafikSelesai); ?>"
     class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-box-arrow-up-right me-1"></i>Laporan Lengkap
  </a>
</div>

<!-- === FORM FILTER + QUICK RANGE === -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-3">
    <form method="GET" action="<?= htmlspecialchars($currentUrl); ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Dari Tanggal</label>
        <input type="date" name="tgl_mulai" class="form-control form-control-sm"
               value="<?= htmlspecialchars($grafikMulai); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-bold mb-1">Sampai Tanggal</label>
        <input type="date" name="tgl_selesai" class="form-control form-control-sm"
               value="<?= htmlspecialchars($grafikSelesai); ?>">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-primary w-100">
          <i class="bi bi-filter me-1"></i>Filter
        </button>
      </div>
      <div class="col-md-4">
        <div class="d-flex flex-wrap gap-1">
          <button type="button" class="btn btn-sm btn-outline-primary flex-fill" data-range="today">Hari Ini</button>
          <button type="button" class="btn btn-sm btn-outline-primary flex-fill" data-range="7d">7 Hari</button>
          <button type="button" class="btn btn-sm btn-outline-primary flex-fill" data-range="30d">30 Hari</button>
          <button type="button" class="btn btn-sm btn-outline-primary flex-fill" data-range="month">Bulan Ini</button>
        </div>
      </div>
    </form>
    <small class="text-muted mt-2 d-block">
      Periode: <?= date('d/m/Y', strtotime($grafikMulai)); ?> s/d <?= date('d/m/Y', strtotime($grafikSelesai)); ?>
    </small>
  </div>
</div>

<!-- === TREN HARIAN (full width) === -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white py-3">
    <h6 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Tren Omzet & Laba Harian</h6>
  </div>
  <div class="card-body">
    <div style="position: relative; height: 300px;">
      <canvas id="chartTrenHarian"></canvas>
    </div>
  </div>
</div>

<!-- === 3 GRAFIK ROW === -->
<div class="row g-3 mb-3">
  <!-- Metode Bayar -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill me-2 text-info"></i>Metode Bayar</h6>
      </div>
      <div class="card-body">
        <div style="position: relative; height: 240px;">
          <canvas id="chartMetodeBayar"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Top 5 Barang -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-trophy-fill me-2 text-warning"></i>Top 5 Barang</h6>
      </div>
      <div class="card-body">
        <div style="position: relative; height: 240px;">
          <canvas id="chartTopBarang"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Omzet per Kategori -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-tags-fill me-2 text-success"></i>Omzet per Kategori</h6>
      </div>
      <div class="card-body">
        <div style="position: relative; height: 240px;">
          <canvas id="chartPerKategori"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- === SCRIPT CHART.JS === -->
<script>
(function() {
  if (typeof Chart === 'undefined') {
    console.error('[Grafik Penjualan] Chart.js belum ter-load. Cek path <script src="...chart.umd.js">.');
    return;
  }

  const DATA_GRAFIK = <?= json_encode($grafikData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const WARNA = {
    omzet: '#0d6efd', laba: '#198754',
    tunai: '#0d6efd', qris: '#0dcaf0',
    utang: '#dc3545', transfer: '#fd7e14',
    others: '#6c757d'
  };

  function rupiahRingkas(val) {
    val = Number(val) || 0;
    if (Math.abs(val) >= 1e9) return 'Rp ' + (val/1e9).toFixed(1).replace('.', ',') + 'M';
    if (Math.abs(val) >= 1e6) return 'Rp ' + (val/1e6).toFixed(1).replace('.', ',') + 'jt';
    if (Math.abs(val) >= 1e3) return 'Rp ' + (val/1e3).toFixed(0) + 'rb';
    return 'Rp ' + val;
  }
  function rupiahFull(val) {
    return 'Rp ' + (Number(val) || 0).toLocaleString('id-ID');
  }

  // 1. TREN HARIAN
  const ctx1 = document.getElementById('chartTrenHarian');
  if (ctx1) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: DATA_GRAFIK.harian.labels,
        datasets: [
          { label: 'Omzet', data: DATA_GRAFIK.harian.omzet,
            borderColor: WARNA.omzet, backgroundColor: 'rgba(13,110,253,0.1)',
            fill: true, tension: 0.35, pointRadius: 3, pointHoverRadius: 6 },
          { label: 'Laba', data: DATA_GRAFIK.harian.laba,
            borderColor: WARNA.laba, backgroundColor: 'rgba(25,135,84,0.1)',
            fill: true, tension: 0.35, pointRadius: 3, pointHoverRadius: 6 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + rupiahFull(c.parsed.y) } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: (v) => rupiahRingkas(v) } } }
      }
    });
  }

  // 2. METODE BAYAR
  const ctx2 = document.getElementById('chartMetodeBayar');
  if (ctx2) {
    const mb = DATA_GRAFIK.metode_bayar;
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: mb.labels,
        datasets: [{
          data: mb.values,
          backgroundColor: mb.labels.map(l => WARNA[l.toLowerCase()] || WARNA.others),
          borderWidth: 2, borderColor: '#fff'
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { callbacks: {
            label: (c) => {
              const total = c.dataset.data.reduce((a,b)=>a+b,0) || 1;
              const pct = ((c.parsed / total) * 100).toFixed(1);
              return c.label + ': ' + rupiahFull(c.parsed) + ' (' + pct + '%)';
            }
          }}
        }
      }
    });
  }

  // 3. TOP 5 BARANG
  const ctx3 = document.getElementById('chartTopBarang');
  if (ctx3) {
    const tb = DATA_GRAFIK.top_barang;
    new Chart(ctx3, {
      type: 'bar',
      data: {
        labels: tb.labels,
        datasets: [{ label: 'Qty', data: tb.qty,
          backgroundColor: 'rgba(255,193,7,0.8)', borderColor: '#ffc107', borderWidth: 1 }]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: {
            label: (c) => 'Terjual: ' + (Number(c.parsed.x) || 0).toLocaleString('id-ID')
          }}
        },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // 4. OMZET PER KATEGORI
  const ctx4 = document.getElementById('chartPerKategori');
  if (ctx4) {
    const pk = DATA_GRAFIK.per_kategori;
    new Chart(ctx4, {
      type: 'bar',
      data: {
        labels: pk.labels,
        datasets: [{ label: 'Omzet', data: pk.omzet,
          backgroundColor: 'rgba(25,135,84,0.8)', borderColor: '#198754', borderWidth: 1 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => 'Omzet: ' + rupiahFull(c.parsed.y) } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: (v) => rupiahRingkas(v) } } }
      }
    });
  }

  // QUICK RANGE
  document.querySelectorAll('[data-range]').forEach(btn => {
    btn.addEventListener('click', function() {
      const range = this.dataset.range;
      const today = new Date();
      const ymd = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${dd}`;
      };

      let mulai = ymd(today), selesai = ymd(today);
      if (range === '7d') {
        const d = new Date(today); d.setDate(d.getDate() - 6); mulai = ymd(d);
      } else if (range === '30d') {
        const d = new Date(today); d.setDate(d.getDate() - 29); mulai = ymd(d);
      } else if (range === 'month') {
        mulai = ymd(new Date(today.getFullYear(), today.getMonth(), 1));
        selesai = ymd(new Date(today.getFullYear(), today.getMonth()+1, 0));
      }

      const form = document.querySelector('form[method="GET"]');
      if (form) {
        const inpMulai = form.querySelector('input[name="tgl_mulai"]');
        const inpSelesai = form.querySelector('input[name="tgl_selesai"]');
        if (inpMulai) inpMulai.value = mulai;
        if (inpSelesai) inpSelesai.value = selesai;
        form.submit();
      }
    });
  });
})();
</script>