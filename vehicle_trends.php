<?php
// Dashboard page to visualize historical fueling trends for a single asset
$asset = isset($_GET['asset']) ? $_GET['asset'] : '';
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : '';
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : '';
if ($asset === '') { http_response_code(400); echo 'Missing asset'; exit; }
// Build back link to fuel with current date filters
$backUrl = 'fuel.php';
$qs = [];
if (!empty($startDate)) { $qs['startDate'] = $startDate; }
if (!empty($endDate)) { $qs['endDate'] = $endDate; }
if (!empty($qs)) { $backUrl .= '?' . http_build_query($qs); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="assets/favicon.png" type="image/x-icon">
  <title>Fuel Trends - <?php echo htmlspecialchars($asset); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
  html, body { margin: 0; padding: 0; }
    .stat-card h2 { font-size: 1.6rem; margin: 0; }
    .stat-card small { opacity:.75; }
    .chart-card { min-height: 320px; }
  header .navbar { top: 0; left: 0; right: 0; }
    header .navbar { z-index: 1050; }
  /* Breadcrumb row offset to clear the absolute navbar */
  .breadcrumb-row { margin-top: 90px; }
  </style>
</head>
<body>
<header>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark position-absolute w-100 p-0">
    <div class="container-fluid">
      <a class="navbar-brand" style="font-weight: bold" href="index.php">
        <img style="width: 80px;" src="assets/logo.png" alt="Fleetara Logo"/>
        Fleetara
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto" style="font-weight: bold">
          <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
          <li class="nav-item"><a class="nav-link" href="trailers.php">Trailers</a></li>
          <li class="nav-item"><a class="nav-link" href="rentals.php">Rentals</a></li>
          <li class="nav-item"><a class="nav-link" href="assets.php">Assets</a></li>
          <li class="nav-item"><a class="nav-link active" href="fuel.php">Fuel</a></li>
          <li class="nav-item"><a class="nav-link" href="dvirs.php">DVIRS</a></li>
          <?php if (($_SESSION['USR_ROLE'] ?? '') === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="admin.php">Admin</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link text-danger" href="index.php?Logout=1">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<div class="bg-light border-bottom breadcrumb-row">
  <div class="container py-2 d-flex align-items-center justify-content-between gap-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($backUrl); ?>">
      <i class="bi bi-arrow-left"></i> Back to Fuel
    </a>
    <div class="text-end">
      <div class="text-muted small">Viewing Asset</div>
      <div class="fw-bold h5 mb-0">Trends: <?php echo htmlspecialchars($asset); ?></div>
    </div>
  </div>
</div>

<main class="container my-4">
  <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
    <div>
      <label class="form-label mb-1">Start Date</label>
      <input id="startDate" type="date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>" />
    </div>
    <div>
      <label class="form-label mb-1">End Date</label>
      <input id="endDate" type="date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>" />
    </div>
    <div class="ms-auto">
      <button id="applyBtn" class="btn btn-primary">Apply</button>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card stat-card bg-primary text-white">
        <div class="card-body">
          <small>Total Gallons</small>
          <h2 id="statGallons">0</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card bg-success text-white">
        <div class="card-body">
          <small>Total Cost</small>
          <h2 id="statCost">$0.00</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card bg-secondary text-white">
        <div class="card-body">
          <small>Total Miles</small>
          <h2 id="statMiles">0</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card bg-secondary text-white">
        <div class="card-body">
          <small>Avg MPG</small>
          <h2 id="statAvgMpg">0</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card chart-card">
        <div class="card-header bg-light fw-bold">Gallons Over Time</div>
        <div class="card-body"><canvas id="gallonsChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card chart-card">
        <div class="card-header bg-light fw-bold">Cost Over Time</div>
        <div class="card-body"><canvas id="costChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card chart-card">
        <div class="card-header bg-light fw-bold">Distance (Miles) Over Time</div>
        <div class="card-body"><canvas id="milesChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card chart-card">
        <div class="card-header bg-light fw-bold">Average Unit Cost</div>
        <div class="card-body"><canvas id="unitChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card chart-card">
        <div class="card-header bg-light fw-bold">MPG Over Time</div>
        <div class="card-body"><canvas id="mpgChart"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card chart-card">
        <div class="card-header bg-light fw-bold">Top Products (Count)</div>
        <div class="card-body"><canvas id="productChart"></canvas></div>
      </div>
    </div>
  </div>
</main>

<script>
(function(){
  const asset = <?php echo json_encode($asset); ?>;
  const startEl = document.getElementById('startDate');
  const endEl = document.getElementById('endDate');
  const applyBtn = document.getElementById('applyBtn');

  let gallonsChart, costChart, unitChart, productChart, milesChart, mpgChart;

  function fmtMoney(v){ return '$' + Number(v||0).toFixed(2); }

  function makeLineChart(ctx, labels, data, label, color){
    return new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: [{ label, data, borderColor: color, backgroundColor: color+'33', fill: true, tension: .25 }] },
      options: { responsive: true, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display:true } }, scales: { x: { ticks: { maxRotation: 0 } } } }
    });
  }

  function makeBarChart(ctx, labels, data, label, color){
    return new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets: [{ label, data, backgroundColor: color }] },
      options: { responsive: true, plugins: { legend: { display:false } } }
    });
  }

  function updateStats(summary){
    document.getElementById('statGallons').textContent = Number(summary.gallons||0).toFixed(2);
    document.getElementById('statCost').textContent = fmtMoney(summary.cost||0);
    document.getElementById('statMiles').textContent = Number(summary.miles||0).toFixed(1);
    document.getElementById('statAvgMpg').textContent = Number(summary.mpg||0).toFixed(2);
  }

  function destroyCharts(){
    for (const c of [gallonsChart, costChart, unitChart, productChart, milesChart, mpgChart]) { if (c) c.destroy(); }
    gallonsChart = costChart = unitChart = productChart = milesChart = mpgChart = null;
  }

  function load(){
    const params = new URLSearchParams({ asset });
    if (startEl.value) params.set('startDate', startEl.value);
    if (endEl.value) params.set('endDate', endEl.value);
    fetch('vehicle_data.php?' + params.toString())
      .then(r => r.json())
      .then(d => {
        if (!d || !d.success) throw new Error(d?.error || 'Failed to load');
        updateStats(d.summary||{});
        const labels = d.series?.dates || [];
        destroyCharts();
        gallonsChart = makeLineChart(document.getElementById('gallonsChart'), labels, d.series?.gallons||[], 'Gallons', '#0d6efd');
        costChart = makeLineChart(document.getElementById('costChart'), labels, d.series?.cost||[], 'Cost', '#198754');
        milesChart = makeLineChart(document.getElementById('milesChart'), labels, d.series?.miles||[], 'Miles', '#fd7e14');
        unitChart = makeLineChart(document.getElementById('unitChart'), labels, d.series?.avgUnitCost||[], 'Avg Unit Cost', '#6c757d');
        mpgChart = makeLineChart(document.getElementById('mpgChart'), labels, d.series?.mpg||[], 'MPG', '#20c997');
        const prods = d.topProducts ? Object.keys(d.topProducts) : [];
        const counts = d.topProducts ? Object.values(d.topProducts) : [];
        productChart = makeBarChart(document.getElementById('productChart'), prods, counts, 'Top Products', '#6610f2');
      })
      .catch(err => {
        destroyCharts();
        alert('Load failed: ' + err);
      });
  }

  applyBtn.addEventListener('click', () => {
    const url = new URL(window.location.href);
    if (startEl.value) url.searchParams.set('startDate', startEl.value); else url.searchParams.delete('startDate');
    if (endEl.value) url.searchParams.set('endDate', endEl.value); else url.searchParams.delete('endDate');
    history.replaceState(null, '', url.toString());
    load();
  });

  load();
})();
</script>
</body>
</html>
