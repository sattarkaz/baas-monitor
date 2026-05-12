<?php
// =============================================================================
// transactions.php — Transaction Analytics page
// =============================================================================
require_once '_common.php';
require_auth();

// =============================================================================
// DATA LAYER — Oracle (production) or mock arrays (demo)
// DWH table names resolve to "TABLE@DBLINK" automatically via tbl() when
// config.php → db_dwh → access_type = 'dblink'
// =============================================================================

// ── Read filters from GET ──
$filter_partner  = (int)($_GET['partner_id'] ?? 0);
$filter_currency = trim($_GET['currency'] ?? '');
$filter_mcc      = trim($_GET['mcc'] ?? '');
$filter_country  = trim($_GET['country'] ?? '');
$filter_from     = $_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days'));
$filter_to       = $_GET['date_to']   ?? date('Y-m-d');

// Currency display settings
$currency_factor = 1.0;
$currency_symbol = '₼';
if ($filter_currency === 'USD') { $currency_factor = 1/1.70; $currency_symbol = '$'; }
if ($filter_currency === 'EUR') { $currency_factor = 1/1.84; $currency_symbol = '€'; }
if ($filter_currency === 'TRY') { $currency_factor = 18.5;   $currency_symbol = '₺'; }
if ($filter_currency === 'RUB') { $currency_factor = 55.0;   $currency_symbol = '₽'; }

$chart_labels  = [];
$chart_volumes = [];
$chart_counts  = [];
$total_vol = $total_cnt = 0;
$avg_ticket    = 0;

if (!USE_MOCK_DATA) {
    // ── ORACLE: filtered daily volume (monitoring DB cache) ───────────────────
    $params_daily = [':date_from' => $filter_from, ':date_to' => $filter_to];
    $where_pid    = '';
    if ($filter_partner > 0) {
        $where_pid          = ' AND partner_id = :partner_id';
        $params_daily[':partner_id'] = $filter_partner;
    }
    $where_cur = '';
    if ($filter_currency !== '') {
        $where_cur             = ' AND currency = :currency';
        $params_daily[':currency'] = $filter_currency;
    }
    $stmt_daily = get_monitor_pdo()->prepare('
        SELECT TO_CHAR(agg_date,\'YYYY-MM-DD\') AS agg_date,
               SUM(transaction_count)          AS cnt,
               SUM(transaction_volume)         AS vol,
               AVG(avg_transaction_amount)     AS avg_ticket
        FROM ' . tbl('transaction_daily_agg_cache') . '
        WHERE agg_date BETWEEN TO_DATE(:date_from,\'YYYY-MM-DD\')
                           AND TO_DATE(:date_to,\'YYYY-MM-DD\')'
        . $where_pid . $where_cur . '
        GROUP BY agg_date ORDER BY agg_date');
    $stmt_daily->execute($params_daily);
    foreach ($stmt_daily->fetchAll() as $d) {
        $v = round((float)$d['VOL'] * $currency_factor, 2);
        $chart_labels[]  = substr($d['AGG_DATE'], 5);   // MM-DD
        $chart_volumes[] = $v;
        $chart_counts[]  = (int)$d['CNT'];
        $total_vol += $v;
        $total_cnt += (int)$d['CNT'];
    }
    $avg_ticket = $total_cnt > 0 ? round($total_vol / $total_cnt, 2) : 0;

    // ── ORACLE: MCC breakdown (DWH — resolved via tbl() + DB-link) ───────────
    $params_mcc = [':date_from' => $filter_from, ':date_to' => $filter_to];
    $where_mcc_pid = '';
    if ($filter_partner > 0) { $where_mcc_pid = ' AND m.partner_id = :partner_id'; $params_mcc[':partner_id'] = $filter_partner; }
    $stmt_mcc = get_monitor_pdo()->prepare('
        SELECT m.mcc, m.mcc_description,
               SUM(m.transaction_count)  AS txn_count,
               SUM(m.transaction_volume) AS txn_volume
        FROM ' . tbl('dwh_transaction_mcc_agg') . ' m
        WHERE m.agg_date BETWEEN TO_DATE(:date_from,\'YYYY-MM-DD\')
                              AND TO_DATE(:date_to,\'YYYY-MM-DD\')'
        . $where_mcc_pid . '
        GROUP BY m.mcc, m.mcc_description ORDER BY txn_volume DESC');
    $stmt_mcc->execute($params_mcc);
    $mcc_data = array_map(fn($r) => [
        'mcc'       => $r['MCC'],
        'label'     => $r['MCC_DESCRIPTION'],
        'txn_count' => (int)$r['TXN_COUNT'],
        'txn_volume'=> (float)$r['TXN_VOLUME'] * $currency_factor,
    ], $stmt_mcc->fetchAll());

    // ── ORACLE: Country breakdown (DWH) ──────────────────────────────────────
    $params_cty = [':date_from' => $filter_from, ':date_to' => $filter_to];
    $where_cty_pid = '';
    if ($filter_partner > 0) { $where_cty_pid = ' AND c.partner_id = :partner_id'; $params_cty[':partner_id'] = $filter_partner; }
    $stmt_cty = get_monitor_pdo()->prepare('
        SELECT c.merchant_country,
               SUM(c.transaction_count)  AS txn_count,
               SUM(c.transaction_volume) AS txn_volume
        FROM ' . tbl('dwh_transaction_country_agg') . ' c
        WHERE c.agg_date BETWEEN TO_DATE(:date_from,\'YYYY-MM-DD\')
                              AND TO_DATE(:date_to,\'YYYY-MM-DD\')'
        . $where_cty_pid . '
        GROUP BY c.merchant_country ORDER BY txn_volume DESC');
    $stmt_cty->execute($params_cty);
    $country_data = array_map(fn($r) => [
        'code'       => $r['MERCHANT_COUNTRY'],
        'name'       => $r['MERCHANT_COUNTRY'],
        'txn_count'  => (int)$r['TXN_COUNT'],
        'txn_volume' => (float)$r['TXN_VOLUME'] * $currency_factor,
    ], $stmt_cty->fetchAll());

} else {
    // ── MOCK DATA ─────────────────────────────────────────────────────────────
    $daily_all = mock_daily_txn_data($filter_partner > 0 ? $filter_partner : 0);
    foreach ($daily_all as $d) {
        if ($d['date_full'] < $filter_from || $d['date_full'] > $filter_to) continue;
        $v = round($d['volume'] * $currency_factor, 2);
        $chart_labels[]  = $d['date'];
        $chart_volumes[] = $v;
        $chart_counts[]  = $d['count'];
        $total_vol += $v;
        $total_cnt += $d['count'];
    }
    $avg_ticket = $total_cnt > 0 ? round($total_vol / $total_cnt, 2) : 0;

    $mcc_data = $MOCK_MCC;
    if ($filter_mcc) {
        $mcc_data = array_filter($mcc_data, fn($m) => $m['mcc'] === $filter_mcc);
    }

    $country_data = $MOCK_COUNTRY;
    if ($filter_country) {
        $country_data = array_filter($country_data, fn($c) => $c['code'] === $filter_country);
    }
} // end if/else USE_MOCK_DATA

// Scale MCC + country by currency factor (mock data only — Oracle path scales inline)
if (USE_MOCK_DATA) {
    foreach ($mcc_data as &$m)     { $m['txn_volume'] = round($m['txn_volume'] * $currency_factor, 2); }
    foreach ($country_data as &$c) { $c['txn_volume'] = round($c['txn_volume'] * $currency_factor, 2); }
    unset($m, $c);
}

$mcc_total_vol     = array_sum(array_column($mcc_data, 'txn_volume'));
$mcc_chart_labels  = array_column($mcc_data, 'label');
$mcc_chart_volumes = array_column($mcc_data, 'txn_volume');
$country_total_vol = array_sum(array_column($country_data, 'txn_volume'));

render_header('Transactions', 'transactions');
render_nav('transactions');
?>
<div class="main">
<?php render_topbar('Transaction Analytics / Аналитика транзакций'); ?>
<div class="page-content">

  <!-- NOTICE: DWH source -->
  <div class="notice info" style="margin-bottom:16px">
    <i class="fa-solid fa-circle-info"></i>
    <div>
      Daily aggregates from <strong>transaction_daily_agg_cache</strong>.
      MCC/Country data from DWH views (<strong>dwh_transaction_mcc_agg</strong>, <strong>dwh_transaction_country_agg</strong>).
      Detail transactions remain in DWH — drill-down queries on demand.
    </div>
  </div>

  <!-- FILTER BAR -->
  <form method="GET" action="">
    <div class="filter-bar">
      <div class="filter-group">
        <label><i class="fa-solid fa-handshake"></i> Partner</label>
        <select name="partner_id">
          <option value="0">All Partners</option>
          <?php foreach ($MOCK_PARTNERS as $p): ?>
          <option value="<?= $p['partner_id'] ?>" <?= $filter_partner == $p['partner_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['partner_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label><i class="fa-solid fa-calendar-days"></i> From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($filter_from) ?>">
      </div>
      <div class="filter-group">
        <label>&nbsp;</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($filter_to) ?>">
      </div>
      <div class="filter-group">
        <label><i class="fa-solid fa-coins"></i> Currency</label>
        <select name="currency">
          <option value="">All Currencies</option>
          <?php foreach (['RUB','USD','EUR','KZT'] as $c): ?>
          <option value="<?= $c ?>" <?= $filter_currency === $c ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label><i class="fa-solid fa-tag"></i> MCC</label>
        <select name="mcc">
          <option value="">All MCC</option>
          <?php foreach ($MOCK_MCC as $m): ?>
          <option value="<?= $m['mcc'] ?>" <?= $filter_mcc === $m['mcc'] ? 'selected' : '' ?>>
            <?= $m['mcc'] ?> – <?= htmlspecialchars($m['label']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label><i class="fa-solid fa-earth-europe"></i> Country</label>
        <select name="country">
          <option value="">All Countries</option>
          <?php foreach ($MOCK_COUNTRY as $ct): ?>
          <option value="<?= $ct['code'] ?>" <?= $filter_country === $ct['code'] ? 'selected' : '' ?>>
            <?= $ct['flag'] ?> <?= htmlspecialchars($ct['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
      </div>
      <div class="filter-group">
        <label>&nbsp;</label>
        <a href="transactions.php" class="btn btn-outline"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </div>
  </form>

  <!-- SUMMARY KPIs -->
  <div class="cards-row cards-row-3" style="margin-bottom:20px">
    <div class="kpi-card">
      <div class="kpi-label"><i class="fa-solid fa-arrow-right-arrow-left"></i> Total Volume</div>
      <div class="kpi-value" style="font-size:20px"><?= $currency_symbol ?> <?= fmt_num($total_vol, 2) ?></div>
      <div class="kpi-sub">For selected period / filter</div>
    </div>
    <div class="kpi-card green">
      <div class="kpi-label"><i class="fa-solid fa-receipt"></i> Transaction Count</div>
      <div class="kpi-value"><?= fmt_num($total_cnt) ?></div>
      <div class="kpi-sub">Successful transactions</div>
    </div>
    <div class="kpi-card orange">
      <div class="kpi-label"><i class="fa-solid fa-calculator"></i> Avg Transaction</div>
      <div class="kpi-value" style="font-size:20px"><?= $currency_symbol ?> <?= fmt_num($avg_ticket, 2) ?></div>
      <div class="kpi-sub">Average ticket size</div>
    </div>
  </div>

  <!-- DAILY TREND CHART -->
  <div class="chart-card" style="margin-bottom:20px">
    <div class="chart-card-title">
      <i class="fa-solid fa-chart-bar"></i>
      Daily Transaction Volume<?= $filter_partner > 0 ? ' — ' . htmlspecialchars(array_values(array_filter($MOCK_PARTNERS, fn($p) => $p['partner_id']==$filter_partner))[0]['partner_name'] ?? '') : ' — All Partners' ?>
      <span style="margin-left:auto;font-size:11px;color:#94a3b8;font-weight:400">
        Oracle: SELECT agg_date, SUM(transaction_volume) FROM transaction_daily_agg_cache WHERE … GROUP BY agg_date
      </span>
    </div>
    <canvas id="barChart" height="90"></canvas>
  </div>

  <!-- MCC + COUNTRY ROW -->
  <div class="cards-row cards-row-2" style="margin-bottom:20px">

    <!-- MCC Breakdown -->
    <div>
      <div class="table-card">
        <div class="table-card-header">
          <h3><i class="fa-solid fa-tag" style="color:#8e24aa;margin-right:6px"></i>
              MCC Breakdown / Разбивка по MCC
              <span style="font-size:10px;font-weight:400;color:#94a3b8;margin-left:6px">
                Oracle: dwh_transaction_mcc_agg
              </span>
          </h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>MCC</th>
              <th>Category / Категория</th>
              <th class="right">Count</th>
              <th class="right">Volume</th>
              <th style="min-width:100px">Share</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $mcc_list = array_values($mcc_data);
            $mcc_vol_total = array_sum(array_column($mcc_list, 'txn_volume')) ?: 1;
            usort($mcc_list, fn($a,$b) => $b['txn_volume'] <=> $a['txn_volume']);
            foreach ($mcc_list as $m):
              $pct = round($m['txn_volume'] / $mcc_vol_total * 100, 1);
            ?>
            <tr>
              <td class="mono"><?= htmlspecialchars($m['mcc']) ?></td>
              <td><?= htmlspecialchars($m['label']) ?></td>
              <td class="right"><?= fmt_num($m['txn_count']) ?></td>
              <td class="right"><strong><?= $currency_symbol ?> <?= fmt_num($m['txn_volume'], 0) ?></strong></td>
              <td>
                <div style="height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                  <div style="width:<?= min(100,$pct) ?>%;height:100%;background:#8e24aa;border-radius:3px"></div>
                </div>
                <small style="font-size:10px;color:#64748b"><?= $pct ?>%</small>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Country Breakdown -->
    <div>
      <div class="table-card">
        <div class="table-card-header">
          <h3><i class="fa-solid fa-earth-europe" style="color:#00897b;margin-right:6px"></i>
              Country Breakdown / По странам
              <span style="font-size:10px;font-weight:400;color:#94a3b8;margin-left:6px">
                Oracle: dwh_transaction_country_agg
              </span>
          </h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Country / Страна</th>
              <th class="right">Count</th>
              <th class="right">Volume</th>
              <th style="min-width:100px">Share</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $ctry_list = array_values($country_data);
            $ctry_vol_total = array_sum(array_column($ctry_list, 'txn_volume')) ?: 1;
            usort($ctry_list, fn($a,$b) => $b['txn_volume'] <=> $a['txn_volume']);
            foreach ($ctry_list as $ct):
              $pct = round($ct['txn_volume'] / $ctry_vol_total * 100, 1);
            ?>
            <tr>
              <td>
                <span style="font-size:16px"><?= $ct['flag'] ?></span>
                <strong style="margin-left:6px"><?= htmlspecialchars($ct['name']) ?></strong>
                <span style="color:#94a3b8;font-size:11px;margin-left:4px"><?= $ct['code'] ?></span>
              </td>
              <td class="right"><?= fmt_num($ct['txn_count']) ?></td>
              <td class="right"><strong><?= $currency_symbol ?> <?= fmt_num($ct['txn_volume'], 0) ?></strong></td>
              <td>
                <div style="height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                  <div style="width:<?= min(100,$pct) ?>%;height:100%;background:#00897b;border-radius:3px"></div>
                </div>
                <small style="font-size:10px;color:#64748b"><?= $pct ?>%</small>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PARTNER BREAKDOWN TABLE -->
  <div class="table-card">
    <div class="table-card-header">
      <h3><i class="fa-solid fa-table" style="color:#1e88e5;margin-right:6px"></i>
          Per-Partner Transaction Summary (selected period)
          <span style="font-size:10px;font-weight:400;color:#94a3b8;margin-left:8px">
            Oracle: SELECT p.partner_name, SUM(t.transaction_volume), SUM(t.transaction_count)
            FROM transaction_daily_agg_cache t JOIN partners p … GROUP BY p.partner_name ORDER BY txn_volume DESC
          </span>
      </h3>
    </div>
    <table>
      <thead>
        <tr>
          <th>Partner / Партнёр</th>
          <th class="right">Txn Count</th>
          <th class="right">Txn Volume</th>
          <th class="right">Avg Ticket</th>
          <th style="min-width:160px">Share of Total</th>
          <th>Trend</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $partner_vol_total = array_sum(array_column($MOCK_PARTNER_SUMMARY, 'txn_volume_30d')) ?: 1;
        $sorted_partners = $MOCK_PARTNERS;
        usort($sorted_partners, fn($a,$b) => ($MOCK_PARTNER_SUMMARY[$b['partner_id']]['txn_volume_30d'] ?? 0) <=> ($MOCK_PARTNER_SUMMARY[$a['partner_id']]['txn_volume_30d'] ?? 0));
        foreach ($sorted_partners as $p):
          if ($filter_partner > 0 && $p['partner_id'] != $filter_partner) continue;
          $s   = $MOCK_PARTNER_SUMMARY[$p['partner_id']] ?? [];
          $vol = ($s['txn_volume_30d'] ?? 0) * $currency_factor;
          $cnt = $s['txn_count_30d'] ?? 0;
          $avg = $cnt > 0 ? round($vol / $cnt, 2) : 0;
          $share = round($vol / ($partner_vol_total * $currency_factor) * 100, 1);
          $trend = ($p['partner_id'] % 2 === 0) ? '+' : '';
          $trend_val = round(3.5 + ($p['partner_id'] * 1.7) % 8, 1);
          $trend_color = $trend === '+' ? '#43a047' : '#e53935';
        ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($p['partner_name']) ?></strong>
            <?= status_badge($p['status']) ?>
          </td>
          <td class="right"><?= fmt_num($cnt) ?></td>
          <td class="right"><strong><?= $currency_symbol ?> <?= fmt_num($vol, 0) ?></strong></td>
          <td class="right"><?= $currency_symbol ?> <?= fmt_num($avg, 2) ?></td>
          <td>
            <?php if ($p['status'] === 'active' && $vol > 0): ?>
            <div style="height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-bottom:2px">
              <div style="width:<?= min(100,$share) ?>%;height:100%;background:linear-gradient(90deg,#1e88e5,#42a5f5);border-radius:3px"></div>
            </div>
            <small style="font-size:10px;color:#64748b"><?= $share ?>%</small>
            <?php else: ?>
            <span style="color:#94a3b8;font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($p['status'] === 'active'): ?>
            <span style="color:<?= $trend_color ?>;font-weight:700;font-size:12px">
              <?= $trend ?>+<?= $trend_val ?>% WoW
            </span>
            <?php else: ?>
            <span style="color:#94a3b8;font-size:12px">Inactive</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- DWH INFO BOX -->
  <div style="background:#e8f5e9;border-radius:10px;padding:16px 20px;font-size:12px;color:#1b5e20;margin-bottom:20px">
    <strong><i class="fa-solid fa-database"></i> Data Architecture Note:</strong>
    Daily aggregates are cached in <code>transaction_daily_agg_cache</code> via a scheduled sync from DWH (every 1–3h).
    Detailed transaction records (<code>dwh_transactions</code>) remain in the Data Warehouse and are queried on-demand
    for drill-downs and exports only — not duplicated in the monitoring system.
  </div>

</div><!-- .page-content -->
</div><!-- .main -->

<script>
const labels  = <?= json_encode($chart_labels) ?>;
const volumes = <?= json_encode($chart_volumes) ?>;
const counts  = <?= json_encode($chart_counts) ?>;
const sym     = <?= json_encode($currency_symbol) ?>;

const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: `Volume (${sym})`,
      data: volumes,
      backgroundColor: 'rgba(30,136,229,.75)',
      borderColor: '#1e88e5',
      borderWidth: 1,
      borderRadius: 4,
      yAxisID: 'y',
      order: 2,
    },{
      label: 'Transaction Count',
      data: counts,
      type: 'line',
      borderColor: '#f57c00',
      backgroundColor: 'transparent',
      borderWidth: 2,
      pointRadius: 2,
      tension: 0.4,
      yAxisID: 'y1',
      order: 1,
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
            if (ctx.datasetIndex === 0)
              return ` Volume: ${sym} ${ctx.parsed.y.toLocaleString('az-AZ', {minimumFractionDigits:2})}`;
            return ` Count: ${ctx.parsed.y.toLocaleString('az-AZ')}`;
          }
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 12 } },
      y: {
        position: 'left',
        grid: { color: '#f1f5f9' },
        ticks: { font: { size: 10 }, callback: v => sym + (v>=1e6 ? (v/1e6).toFixed(1)+'M' : (v/1e3).toFixed(0)+'K') }
      },
      y1: {
        position: 'right',
        grid: { drawOnChartArea: false },
        ticks: { font: { size: 10 }, callback: v => v.toLocaleString('az-AZ') }
      }
    }
  }
});
</script>
</body>
</html>
