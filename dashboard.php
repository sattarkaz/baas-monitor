<?php
// =============================================================================
// dashboard.php — Main Dashboard
// Oracle query shown above each data block as a comment
// =============================================================================
require_once '_common.php';
require_auth();

// =============================================================================
// DATA LAYER — reads from Oracle when USE_MOCK_DATA=false, else uses mock arrays
// Table names resolve through tbl() which handles schema prefix + DB-link suffix
// automatically based on config.php settings.
// =============================================================================

if (!USE_MOCK_DATA) {
    // ── ORACLE: KPIs ─────────────────────────────────────────────────────────
    // SELECT * FROM {tbl('v_dashboard_kpis')}
    $kpi = get_monitor_pdo()
        ->query('SELECT * FROM ' . tbl('v_dashboard_kpis'))
        ->fetch();
    $active_partners   = (int)$kpi['ACTIVE_PARTNERS'];
    $active_packages   = (int)$kpi['ACTIVE_PACKAGES'];
    $total_issued      = (int)$kpi['TOTAL_ISSUED_CARDS'];
    $total_remaining   = (int)$kpi['TOTAL_REMAINING_CARDS'];
    $overall_usage_pct = (float)$kpi['OVERALL_USAGE_PCT'];
    $txn_vol_30d       = (float)$kpi['TXN_VOLUME_30D'];
    $txn_cnt_30d       = (int)$kpi['TXN_COUNT_30D'];

    // ── ORACLE: 30-day daily trend ────────────────────────────────────────────
    $sql_trend = '
        SELECT agg_date, SUM(transaction_volume) AS vol, SUM(transaction_count) AS cnt
        FROM ' . tbl('transaction_daily_agg_cache') . '
        WHERE agg_date >= TRUNC(SYSDATE)-30
        GROUP BY agg_date ORDER BY agg_date';
    $daily_rows    = get_monitor_pdo()->query($sql_trend)->fetchAll();
    $chart_labels  = array_column($daily_rows, 'AGG_DATE');
    $chart_volumes = array_map('floatval', array_column($daily_rows, 'VOL'));
    $chart_counts  = array_map('intval',   array_column($daily_rows, 'CNT'));

    // ── ORACLE: Top partners by 30-day volume ─────────────────────────────────
    $sql_partners = '
        SELECT p.partner_id, p.partner_name, p.status,
               SUM(s.issued_cards)    AS issued,
               SUM(s.remaining_cards) AS remaining,
               ROUND(SUM(s.issued_cards)/NULLIF(SUM(cp.package_size),0)*100,2) AS usage_pct,
               NVL(SUM(t.transaction_volume),0) AS txn_vol_30d,
               NVL(SUM(t.transaction_count),0)  AS txn_cnt_30d
        FROM ' . tbl('partners') . ' p
        JOIN ' . tbl('card_packages') . ' cp ON cp.partner_id = p.partner_id
        JOIN ' . tbl('package_usage_snapshot') . ' s
               ON s.package_id = cp.package_id
              AND s.snapshot_date = (SELECT MAX(s2.snapshot_date)
                                     FROM ' . tbl('package_usage_snapshot') . ' s2
                                     WHERE s2.package_id = cp.package_id)
        LEFT JOIN ' . tbl('transaction_daily_agg_cache') . ' t
               ON t.partner_id = p.partner_id
              AND t.agg_date >= TRUNC(SYSDATE)-30
        WHERE p.status = \'active\'
        GROUP BY p.partner_id, p.partner_name, p.status
        ORDER BY txn_vol_30d DESC';
    $partner_rows = get_monitor_pdo()->query($sql_partners)->fetchAll();

} else {
    // ── MOCK DATA ─────────────────────────────────────────────────────────────
    $active_partners   = count(array_filter($MOCK_PARTNERS, fn($p) => $p['status'] === 'active'));
    $active_packages   = count(array_filter($MOCK_PACKAGES, fn($p) => $p['status'] === 'active'));
    $total_issued      = array_sum(array_column($MOCK_PACKAGES, 'issued_cards'));
    $total_remaining   = array_sum(array_column($MOCK_PACKAGES, 'remaining_cards'));
    $total_pkg_size    = array_sum(array_column($MOCK_PACKAGES, 'package_size'));
    $overall_usage_pct = $total_pkg_size > 0 ? round($total_issued / $total_pkg_size * 100, 1) : 0;

    $txn_vol_30d = array_sum(array_column($MOCK_PARTNER_SUMMARY, 'txn_volume_30d'));
    $txn_cnt_30d = array_sum(array_column($MOCK_PARTNER_SUMMARY, 'txn_count_30d'));

    $daily = mock_daily_txn_data(0);
    $chart_labels  = array_column($daily, 'date');
    $chart_volumes = array_column($daily, 'volume');
    $chart_counts  = array_column($daily, 'count');

    $partner_rows = [];
    foreach ($MOCK_PARTNERS as $p) {
        $s = $MOCK_PARTNER_SUMMARY[$p['partner_id']] ?? null;
        if (!$s || $p['status'] !== 'active') continue;
        $partner_rows[] = [
            'partner_id'   => $p['partner_id'],
            'partner_name' => $p['partner_name'],
            'status'       => $p['status'],
            'issued'       => $s['total_issued'],
            'remaining'    => $s['total_remaining'],
            'usage_pct'    => $s['avg_usage_pct'],
            'txn_vol_30d'  => $s['txn_volume_30d'],
            'txn_cnt_30d'  => $s['txn_count_30d'],
        ];
    }
    usort($partner_rows, fn($a,$b) => $b['txn_vol_30d'] <=> $a['txn_vol_30d']);
}

// Donut data
$donut_labels  = array_column($partner_rows, 'partner_name');
$donut_volumes = array_column($partner_rows, 'txn_vol_30d');

render_header('Dashboard', 'dashboard');
render_nav('dashboard');
?>
<div class="main">
<?php render_topbar('Dashboard / Дашборд'); ?>
<div class="page-content">

  <!-- KPI CARDS -->
  <!-- Oracle: SELECT * FROM v_dashboard_kpis -->
  <div class="cards-row cards-row-6" style="margin-bottom:20px">
    <div class="kpi-card">
      <div class="kpi-label"><i class="fa-solid fa-handshake"></i> Active Partners</div>
      <div class="kpi-value"><?= $active_partners ?></div>
      <div class="kpi-sub">Активных партнёров</div>
    </div>
    <div class="kpi-card green">
      <div class="kpi-label"><i class="fa-solid fa-box"></i> Active Packages</div>
      <div class="kpi-value"><?= $active_packages ?></div>
      <div class="kpi-sub">Активных пакетов</div>
    </div>
    <div class="kpi-card purple">
      <div class="kpi-label"><i class="fa-solid fa-credit-card"></i> Cards Issued</div>
      <div class="kpi-value"><?= fmt_num($total_issued) ?></div>
      <div class="kpi-sub">Выпущено карт</div>
    </div>
    <div class="kpi-card teal">
      <div class="kpi-label"><i class="fa-solid fa-circle-minus"></i> Cards Remaining</div>
      <div class="kpi-value"><?= fmt_num($total_remaining) ?></div>
      <div class="kpi-sub">Остаток в пакетах</div>
    </div>
    <div class="kpi-card orange">
      <div class="kpi-label"><i class="fa-solid fa-percent"></i> Avg Package Usage</div>
      <div class="kpi-value"><?= $overall_usage_pct ?>%</div>
      <div class="kpi-sub">Средняя загрузка пакетов</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label"><i class="fa-solid fa-arrow-right-arrow-left"></i> Txn Volume (30d)</div>
      <div class="kpi-value" style="font-size:18px"><?= fmt_rub($txn_vol_30d) ?></div>
      <div class="kpi-sub"><?= fmt_num($txn_cnt_30d) ?> transactions · 30 days</div>
    </div>
  </div>

  <!-- CHARTS ROW -->
  <div class="cards-row cards-row-2" style="margin-bottom:20px">

    <!-- 30-day transaction trend -->
    <div class="chart-card">
      <div class="chart-card-title">
        <i class="fa-solid fa-chart-area"></i> Transaction Volume — Last 30 Days (All Partners)
        <span style="margin-left:auto;font-size:11px;color:#94a3b8;font-weight:400">Source: transaction_daily_agg_cache</span>
      </div>
      <!-- Oracle: SELECT agg_date, SUM(transaction_volume) FROM transaction_daily_agg_cache
                   WHERE agg_date >= TRUNC(SYSDATE)-30 GROUP BY agg_date ORDER BY agg_date -->
      <canvas id="trendChart" height="120"></canvas>
    </div>

    <!-- Partner volume distribution -->
    <div class="chart-card" style="display:flex;flex-direction:column">
      <div class="chart-card-title">
        <i class="fa-solid fa-chart-pie"></i> Transaction Share by Partner (30d)
        <span style="margin-left:auto;font-size:11px;color:#94a3b8;font-weight:400">Source: v_partner_txn_summary_30d</span>
      </div>
      <div style="flex:1;display:flex;align-items:center;justify-content:center">
        <canvas id="donutChart" style="max-height:220px;max-width:220px"></canvas>
      </div>
    </div>
  </div>

  <!-- ALERT: near-exhausted packages -->
  <?php
  $near_empty = array_filter($MOCK_PACKAGES, fn($p) => $p['status'] === 'active' && $p['usage_percent'] >= 90);
  if ($near_empty): ?>
  <div class="notice warn" style="margin-bottom:20px">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div>
      <strong>Package Alert / Внимание по пакетам:</strong>
      <?= count($near_empty) ?> active package(s) are ≥ 90% utilised and may need renewal soon.
      <a href="partners.php" style="color:#bf360c;font-weight:600">Review &rarr;</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- TOP PARTNERS TABLE -->
  <div class="table-card">
    <div class="table-card-header">
      <h3><i class="fa-solid fa-ranking-star" style="color:#f57c00;margin-right:6px"></i> Top Partners by Transaction Volume (30 days)</h3>
      <a href="partners.php" class="btn btn-outline" style="font-size:12px;padding:6px 12px">View All</a>
    </div>
    <!-- Oracle: SELECT p.partner_name, SUM(t.transaction_volume), ... FROM partners p JOIN ... -->
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Partner / Партнёр</th>
          <th>Cards Issued</th>
          <th>Remaining</th>
          <th>Package Usage</th>
          <th class="right">Txn Volume (30d)</th>
          <th class="right">Txn Count (30d)</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($partner_rows as $i => $row): ?>
        <tr>
          <td style="color:#94a3b8;font-weight:600"><?= $i + 1 ?></td>
          <td>
            <strong><?= htmlspecialchars($row['partner_name']) ?></strong>
            <?= status_badge($row['status']) ?>
          </td>
          <td><?= fmt_num($row['issued']) ?></td>
          <td><?= fmt_num($row['remaining']) ?></td>
          <td style="min-width:140px">
            <?= usage_bar($row['usage_pct']) ?>
          </td>
          <td class="right"><strong><?= fmt_rub($row['txn_vol_30d']) ?></strong></td>
          <td class="right"><?= fmt_num($row['txn_cnt_30d']) ?></td>
          <td>
            <a href="partners.php?id=<?= $row['partner_id'] ?>" class="btn btn-outline" style="font-size:11px;padding:4px 10px">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Open
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- PACKAGE STATUS OVERVIEW -->
  <div class="table-card">
    <div class="table-card-header">
      <h3><i class="fa-solid fa-layer-group" style="color:#1e88e5;margin-right:6px"></i> All Card Packages — Current Snapshot</h3>
    </div>
    <!-- Oracle: SELECT cp.*, s.issued_cards, s.remaining_cards, s.usage_percent
                 FROM card_packages cp JOIN package_usage_snapshot s ON ... JOIN partners p ON ... -->
    <table>
      <thead>
        <tr>
          <th>Package ID</th>
          <th>Partner</th>
          <th class="right">Size</th>
          <th class="right">Issued</th>
          <th class="right">Remaining</th>
          <th style="min-width:160px">Utilisation</th>
          <th>Period</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($MOCK_PACKAGES as $pkg):
        $pname = '';
        foreach ($MOCK_PARTNERS as $p) { if ($p['partner_id'] === $pkg['partner_id']) { $pname = $p['partner_name']; break; } }
      ?>
        <tr>
          <td class="mono">PKG-<?= str_pad($pkg['package_id'], 3, '0', STR_PAD_LEFT) ?></td>
          <td><?= htmlspecialchars($pname) ?></td>
          <td class="right"><?= fmt_num($pkg['package_size']) ?></td>
          <td class="right"><?= fmt_num($pkg['issued_cards']) ?></td>
          <td class="right"><?= fmt_num($pkg['remaining_cards']) ?></td>
          <td><?= usage_bar($pkg['usage_percent']) ?></td>
          <td style="font-size:12px;color:#64748b"><?= $pkg['start_date'] ?> – <?= $pkg['end_date'] ?></td>
          <td><?= status_badge($pkg['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div><!-- .page-content -->
</div><!-- .main -->

<script>
// ── Line chart: 30-day transaction volume trend ──
const labels  = <?= json_encode($chart_labels) ?>;
const volumes = <?= json_encode($chart_volumes) ?>;
const counts  = <?= json_encode($chart_counts) ?>;

const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Volume (₼)',
      data: volumes,
      borderColor: '#1e88e5',
      backgroundColor: 'rgba(30,136,229,.10)',
      fill: true,
      tension: 0.4,
      pointRadius: 2,
      pointHoverRadius: 5,
      borderWidth: 2.5,
      yAxisID: 'y',
    },{
      label: 'Count',
      data: counts,
      borderColor: '#43a047',
      backgroundColor: 'transparent',
      fill: false,
      tension: 0.4,
      pointRadius: 1,
      borderWidth: 1.5,
      borderDash: [5,3],
      yAxisID: 'y1',
    }]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 14 } },
      tooltip: {
        callbacks: {
          label: (ctx) => {
            if (ctx.datasetIndex === 0) return ' Volume: ₼ ' + ctx.parsed.y.toLocaleString('az-AZ', {minimumFractionDigits:2});
            return ' Count: ' + ctx.parsed.y.toLocaleString('az-AZ');
          }
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 10 } },
      y: {
        position: 'left',
        grid: { color: '#f1f5f9' },
        ticks: { font: { size: 10 }, callback: v => '₼ ' + (v/1e6).toFixed(1) + 'M' }
      },
      y1: {
        position: 'right',
        grid: { drawOnChartArea: false },
        ticks: { font: { size: 10 }, callback: v => v.toLocaleString('az-AZ') }
      }
    }
  }
});

// ── Donut chart: partner share ──
const donutLabels  = <?= json_encode($donut_labels) ?>;
const donutVolumes = <?= json_encode($donut_volumes) ?>;
const donutColors  = ['#1e88e5','#43a047','#f57c00','#8e24aa','#00897b','#e53935'];

const donutCtx = document.getElementById('donutChart').getContext('2d');
new Chart(donutCtx, {
  type: 'doughnut',
  data: {
    labels: donutLabels,
    datasets: [{
      data: donutVolumes,
      backgroundColor: donutColors,
      borderWidth: 2,
      borderColor: '#fff',
      hoverOffset: 8,
    }]
  },
  options: {
    responsive: true,
    cutout: '65%',
    plugins: {
      legend: { position: 'right', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } },
      tooltip: {
        callbacks: {
          label: (ctx) => {
            const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
            const pct   = ((ctx.parsed / total) * 100).toFixed(1);
            const v     = (ctx.parsed / 1e6).toFixed(2);
            return ` ₼${v}M (${pct}%)`;
          }
        }
      }
    }
  }
});
</script>
</body>
</html>
