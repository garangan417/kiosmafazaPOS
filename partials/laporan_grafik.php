<?php
// partials/laporan_grafik.php
// Partial grafik laporan penjualan — NO CDN, pakai asset lokal.
// Variabel yang dibutuhkan dari parent:
//   $grafikData (array hasil buildGrafikDataLaporan())
//   $tglMulai, $tglSelesai

if (!isset($grafikData)) {
    return;
}
?>

<!-- QUICK RANGE BUTTONS -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <span class="text-muted small align-self-center me-1">Rentang cepat:</span>
  <button type="button" class="btn btn-sm btn-outline-primary" data-range="today">Hari Ini</button>
  <button type="button" class="btn btn-sm btn-outline-primary" data-range="7d">7 Hari</button>
  <button type="button" class="btn btn-sm btn-outline-primary" data-range="30d">30 Hari</button>
  <button type="button" class="btn btn-sm btn-outline-primary" data-range="month">Bulan Ini</button>
</div>

<!-- ROW 1: TREN HARIAN (full width) -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <h6 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Tren Omzet & Laba Harian</h6>
    <small class="text-muted">Periode: <?= date('d/m/Y', strtotime($tglMulai)); ?> s/d <?= date('d/m/Y', strtotime($tglSelesai)); ?></small>
  </div>
  <div class="card-body">
    <div style="position: relative; height: 320px;">
      <canvas id="chartTrenHarian"></canvas>
    </div>
  </div>
</div>

<!-- ROW 2: 3 GRAFIK -->
<div class="row g-3 mb-3">
  <!-- Komposisi Metode Bayar -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill me-2 text-info"></i>Komposisi Metode Bayar</h6>
      </div>
      <div class="card-body">
        <div style="position: relative; height: 260px;">
          <canvas id="chartMetodeBayar"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Top 5 Barang Terlaris -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-trophy-fill me-2 text-warning"></i>Top 5 Barang Terlaris</h6>
      </div>
      <div class="card-body">
        <div style="position: relative; height: 260px;">
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
        <div style="position: relative; height: 260px;">
          <canvas id="chartPerKategori"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const DATA_GRAFIK = <?= json_encode($grafikData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const WARNA = {
    omzet:  '#0d6efd',
    laba:   '#198754',
    tunai:  '#0d6efd',
    qris:   '#0dcaf0',
    utang:  '#dc3545',
    transfer: '#fd7e14',
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

  const tooltipRupiah = {
    callbacks: {
      label: (ctx) => {
        const label = ctx.dataset.label || ctx.label || '';
        return label + ': ' + rupiahFull(ctx.parsed.y ?? ctx.parsed.x ?? ctx.parsed);
      }
    }
  };

  // 1. TREN HARIAN
  const ctx1 = document.getElementById('chartTrenHarian');
  if (ctx1) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: DATA_GRAFIK.harian.labels,
        datasets: [
          {
            label: 'Omzet',
            data: DATA_GRAFIK.harian.omzet,
            borderColor: WARNA.omzet,
            backgroundColor: 'rgba(13,110,253,0.1)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6
          },
          {
            label: 'Laba',
            data: DATA_GRAFIK.harian.laba,
            borderColor: WARNA.laba,
            backgroundColor: 'rgba(25,135,84,0.1)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: (ctx) => ctx.dataset.label + ': ' + rupiahFull(ctx.parsed.y)
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: (v) => rupiahRingkas(v) }
          }
        }
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
          backgroundColor: mb.labels.map(l => {
            const key = l.toLowerCase();
            return WARNA[key] || WARNA.others;
          }),
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const total = ctx.dataset.data.reduce((a,b)=>a+b,0) || 1;
                const pct = ((ctx.parsed / total) * 100).toFixed(1);
                return ctx.label + ': ' + rupiahFull(ctx.parsed) + ' (' + pct + '%)';
              }
            }
          }
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
        datasets: [{
          label: 'Qty Terjual',
          data: tb.qty,
          backgroundColor: 'rgba(255,193,7,0.8)',
          borderColor: '#ffc107',
          borderWidth: 1
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => 'Terjual: ' + (Number(ctx.parsed.x) || 0).toLocaleString('id-ID')
            }
          }
        },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 } }
        }
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
        datasets: [{
          label: 'Omzet',
          data: pk.omzet,
          backgroundColor: 'rgba(25,135,84,0.8)',
          borderColor: '#198754',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: tooltipRupiah
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: (v) => rupiahRingkas(v) }
          }
        }
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

      let mulai = ymd(today);
      let selesai = ymd(today);

      if (range === '7d') {
        const d = new Date(today); d.setDate(d.getDate() - 6);
        mulai = ymd(d);
      } else if (range === '30d') {
        const d = new Date(today); d.setDate(d.getDate() - 29);
        mulai = ymd(d);
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