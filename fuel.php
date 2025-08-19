<?php
session_start();
// Frontend loads data via AJAX from fuel_data.php
$endDateRaw = isset($_GET['endDate']) ? $_GET['endDate'] : date('Y-m-d');
$startDateRaw = isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-d',  strtotime('-30 days'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/favicon.png" type="image/x-icon">
    <title>Fleetara</title>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <script>
        function updateDates() {
            const startDate = document.getElementById('startDatePicker').value;
            const endDate = document.getElementById('endDatePicker').value;
            const newUrl = '?startDate=' + encodeURIComponent(startDate) + '&endDate=' + encodeURIComponent(endDate);
            window.location.href = newUrl;
        }
        function exportData() {
            const table = document.getElementById('vehiclesTable');
            // Clone and strip hidden filter columns from export
            const clone = table.cloneNode(true);
            Array.from(clone.tBodies[0].rows).forEach(tr => {
                Array.from(tr.querySelectorAll('td.vehicleType, td.region')).forEach(td => td.remove());
            });
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(clone);
            const range = XLSX.utils.decode_range(ws['!ref']);
            const dollarCols = [8, 9];
            for (let r = range.s.r + 1; r <= range.e.r; r++) {
                for (const c of dollarCols) {
                    const addr = XLSX.utils.encode_cell({ r, c });
                    const cell = ws[addr];
                    if (!cell) continue;
                    const val = String(cell.v ?? '').trim();
                    if (!val.startsWith('$')) {
                        const num = parseFloat(val.replace(/[^0-9.-]/g, ''));
                        if (!isNaN(num)) { cell.v = '$' + num.toFixed(2); cell.t = 's'; }
                        else { cell.v = '$' + val; cell.t = 's'; }
                    }
                }
            }
            XLSX.utils.book_append_sheet(wb, ws, 'Fuel Report');
            XLSX.writeFile(wb, 'samsara_fuel_report.xlsx');
        }
        function sortTable(columnIndex) {
            const table = document.getElementById('vehiclesTable');
            const tbody = table.tBodies[0];
            const rows = Array.from(tbody.rows);
            const asc = table.getAttribute('data-sort-order') === 'asc';
            table.querySelectorAll('th').forEach(th => th.classList.remove('sort-asc','sort-desc'));
            table.querySelectorAll('th')[columnIndex].classList.add(asc ? 'sort-desc':'sort-asc');
            rows.sort((a,b)=>{
                const A = a.cells[columnIndex]?.innerText.trim() || '';
                const B = b.cells[columnIndex]?.innerText.trim() || '';
                const nA = parseFloat(A.replace(/[^0-9.-]+/g,''));
                const nB = parseFloat(B.replace(/[^0-9.-]+/g,''));
                const aNum = !isNaN(nA), bNum = !isNaN(nB);
                let cmp;
                if (aNum && bNum) cmp = nA - nB; else cmp = A.localeCompare(B);
                return asc ? cmp : -cmp;
            });
            rows.forEach(r => tbody.appendChild(r));
            table.setAttribute('data-sort-order', asc ? 'desc' : 'asc');
        }
    </script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
    <style>
        /* wrap the input + icon */
        .search-wrapper {
        position: relative;
        }

        /* the magnifier, positioned over the input */
        .search-wrapper .search-icon {
        position: absolute;
        top: 50%;
        left: 0.75rem;
        transform: translateY(-50%);
        pointer-events: none;        /* clicks go to the input */
        color: #6c757d;              /* match .form-control-sm text-muted */
        font-size: 1rem;             /* same size as the input content */
        }

        /* hide the placeholder text itself */
        .search-wrapper input::placeholder {
        color: transparent;
        }

        /* NEW: when focused or not empty, remove extra padding */
        .search-wrapper input:focus,
        .search-wrapper input:not(:placeholder-shown) {
        padding-left: 0.5rem;  /* back to something like the default .form-control-sm */
        }

        /* as soon as the user types (i.e. NOT placeholder-shown), hide the icon */
        .search-wrapper input:focus + .search-icon,
        .search-wrapper input:not(:placeholder-shown) + .search-icon {
        display: none;
        }

        th:hover {
            cursor: pointer;
            background-color: #f8f9fa; /* Light gray background on hover */
        }

        /* Add styles for sort indicators */
        th.sort-asc::after {
            content: " \25B2"; /* Up arrow */
            font-size: 0.8rem;
            color: #6c757d;
        }

        th.sort-desc::after {
            content: " \25BC"; /* Down arrow */
            font-size: 0.8rem;
            color: #6c757d;
        }
    /* Variance coloring */
    .variance-green { background-color: #d1e7dd !important; color: #0f5132 !important; font-weight: 600; }
    .variance-yellow { background-color: #fff3cd !important; color: #664d03 !important; font-weight: 600; }
    .variance-red { background-color: #f8d7da !important; color: #842029 !important; font-weight: 600; }
    /* Clickable row UX */
    tr.clickable-row { cursor: pointer; }
    header .navbar { z-index: 1050; }
    body { margin: 0; }
    /* Solid background for expanded mobile menu */
    @media (max-width: 991.98px) { .navbar-collapse { background-color: #212529; } }
    /* Make brand image a bit smaller on very small screens */
    @media (max-width: 575.98px) { header .navbar .navbar-brand img { width: 56px; height: auto; } }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top w-100 p-0">
            <div class="container-fluid">
                <a class="navbar-brand" style="font-weight: bold" href="index.php">
                    <img style="width: 80px;"src="assets/logo.png" alt="Fleetara Logo"/>    
                    Fleetara
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto" style="font-weight: bold">
                        <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="trailers.php">Trailers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rentals.php">Rentals</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assets.php">Assets</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="fuel.php">Fuel</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dvirs.php">DVIRS</a>
                        </li>
                        <?php if (($_SESSION['USR_ROLE'] ?? '') === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin</a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="index.php?Logout=1">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

        <div id="loadingOverlay" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:9999;background:rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;">
            <div class="spinner-border text-primary" style="width:4rem;height:4rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

    <main class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card p-4 shadow-lg" style="width: 100%;">
            <h2 class="text-center mb-4">Samsara Fuel Efficiency Report</h2>
            <!-- Dashboard Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Estimated Cost</h5>
                            <h2 class="card-text" id="totalCost">$0.00</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Miles Driven</h5>
                            <h2 class="card-text" id="totalMiles">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Overall Actual Cost</h5>
                            <h2 class="card-text" id="overallCost">$0.00</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Overall Calculated Miles</h5>
                            <h2 class="card-text" id="overallMiles">0</h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row justify-content-between mb-4">
                <div class="d-flex col-auto">
                    <div class="col-auto mx-2">
                        <label class="form-label d-flex justify-content-center" for="datePicker">Start Date:</label>
                        <div>
                            <input type="date" id="startDatePicker" value="<?php echo htmlspecialchars($startDateRaw); ?>">
                        </div>
                        
                    </div>
                    <div class="col-auto mx-2">
                        <label class="form-label d-flex justify-content-center" for="datePicker">End Date:</label>
                        <div>
                            <input type="date" id="endDatePicker" value="<?php echo htmlspecialchars($endDateRaw); ?>">
                            <button onclick="updateDates()">Update</button>
                        </div>
                        
                    </div>  
                    <div class="col-auto mx-2">
                        <label for="vehicleTypeFilter" class="form-label d-flex justify-content-center">Vehicle Type:</label>
                        <select id="vehicleTypeFilter" class="form-select form-select-sm">
                            <option value="">All Types</option>
                        </select>
                    </div>
                    <div class="col-auto mx-2">
                        <label for="regionFilter" class="form-label d-flex justify-content-center">Region:</label>
                        <select id="regionFilter" class="form-select form-select-sm">
                            <option value="">All Regions</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex col-auto align-items-center gap-2">
                    <button onclick="exportData()">Export to Excel</button>
                    <form style="margin:0;" id="importForm" enctype="multipart/form-data" style="display:inline-block;">
                        <label for="importFile" class="btn btn-secondary btn-sm mb-0">Import from File</label>
                        <input type="file" id="importFile" name="importFile" accept=".csv" style="display:none;" />
                    </form>
                </div>
            </div>
            
            <div class="table-responsive">
            <table id="vehiclesTable" class="table table-striped table-hover" data-sort-order="asc">
                <thead class="sticky-top bg-white">
                    <tr>
                        <th scope="col" onclick="sortTable(0)">Asset #</th>
                        <th scope="col" onclick="sortTable(1)">Efficiency</th>
                        <th scope="col" onclick="sortTable(2)">Fuel Used</th>
                        <th scope="col" onclick="sortTable(3)">Actual Fuel</th>
                        <th scope="col" onclick="sortTable(4)">Fuel Variance</th>
                        <th scope="col" onclick="sortTable(5)">Distance</th>
                        <th scope="col" onclick="sortTable(6)">Potential Miles</th>
                        <th scope="col" onclick="sortTable(7)">Mileage Variance</th>
                        <th scope="col" onclick="sortTable(8)">Est. Cost</th>
                        <th scope="col" onclick="sortTable(9)">Actual Cost</th>
                        <th scope="col" onclick="sortTable(10)">Idle Fuel (gal)</th>
                        <th scope="col" onclick="sortTable(11)">Links</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be loaded via AJAX -->
                </tbody>
            </table>
            </div>
        </div>
    </main>

    <footer></footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // AJAX load + import handler + filters/totals
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('loadingOverlay');
        const tbody = document.querySelector('#vehiclesTable tbody');
        const typeSelect = document.getElementById('vehicleTypeFilter');
        const regionSelect = document.getElementById('regionFilter');
        const startDate = document.getElementById('startDatePicker').value;
        const endDate = document.getElementById('endDatePicker').value;

        function setTotals(t) {
            document.getElementById('totalCost').textContent = '$' + (t.totalCost || 0).toFixed(2);
            document.getElementById('totalMiles').textContent = (t.totalMiles || 0).toFixed(1);
            document.getElementById('overallCost').textContent = '$' + (t.overallCost || 0).toFixed(2);
            document.getElementById('overallMiles').textContent = (t.overallMiles || 0).toFixed(1);
        }

        function rowMatchesFilters(tr) {
            const typeVal = (typeSelect.value || '').toLowerCase();
            const regionVal = (regionSelect.value || '').toLowerCase();
            const t = (tr.querySelector('.vehicleType')?.textContent || '').trim().toLowerCase();
            const r = (tr.querySelector('.region')?.textContent || '').trim().toLowerCase();
            const typeOk = !typeVal || t === typeVal;
            const regionOk = !regionVal || r === regionVal;
            return typeOk && regionOk;
        }

        function recalcTotalsVisible() {
            const table = document.getElementById('vehiclesTable');
            let totalCost = 0, totalMiles = 0, overallCost = 0, overallMiles = 0;
            Array.from(table.tBodies[0].rows).forEach(row => {
                if (row.style.display === 'none') return;
                const estCost = parseFloat((row.cells[8]?.textContent || '').replace(/[$,]/g,''));
                if (!isNaN(estCost)) totalCost += estCost;
                const miles = parseFloat((row.cells[5]?.textContent || '').replace(/[^0-9.]/g,''));
                if (!isNaN(miles)) totalMiles += miles;
                const actCostTxt = (row.cells[9]?.textContent || '').trim();
                if (actCostTxt !== '-') {
                    const actCost = parseFloat(actCostTxt.replace(/[$,]/g,''));
                    if (!isNaN(actCost)) overallCost += actCost;
                }
                const potMilesTxt = (row.cells[6]?.textContent || '').trim();
                if (potMilesTxt !== '-') {
                    const pm = parseFloat(potMilesTxt.replace(/[^0-9.]/g,''));
                    if (!isNaN(pm)) overallMiles += pm;
                }
            });
            setTotals({ totalCost, totalMiles, overallCost, overallMiles });
        }

        function applyFilters() {
            Array.from(tbody.rows).forEach(tr => {
                tr.style.display = rowMatchesFilters(tr) ? '' : 'none';
            });
            recalcTotalsVisible();
        }

        function createLinkButtons(links) {
            const td = document.createElement('td');
            td.className = 'text-center';
            const wrap = document.createElement('div');
            wrap.className = 'd-inline-flex align-items-center gap-1';
            if (links.fleetio) {
                const a = document.createElement('a');
                a.href = links.fleetio; a.target = '_blank';
                a.className = 'btn btn-sm text-white';
                a.style.backgroundColor = 'rgb(6, 119, 72)';
                a.style.fontWeight = '700';
                a.textContent = 'F';
                wrap.appendChild(a);
            } else {
                const span = document.createElement('span');
                span.className = 'text-muted';
                span.textContent = '-';
                wrap.appendChild(span);
            }
            const s = document.createElement('a');
            s.href = links.samsara; s.target = '_blank';
            s.className = 'btn btn-sm btn-dark';
            s.style.fontWeight = '700';
            s.textContent = 'S';
            wrap.appendChild(s);
            td.appendChild(wrap);
            return td;
        }

        function renderRows(rows) {
            tbody.innerHTML = '';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.classList.add('clickable-row');
                const assetName = r[0];
                tr.title = 'View fueling trends for ' + assetName;
                for (let i = 0; i <= 11; i++) {
                    const td = document.createElement('td');
                    if (i === 4 || i === 7) {
                        const val = r[i];
                        if (Array.isArray(val)) { td.textContent = val[0]; if (val[1]) td.className = val[1]; }
                        else { td.textContent = val; }
                    } else if (i === 11) {
                        const linkTd = createLinkButtons(r[i]);
                        tr.appendChild(linkTd);
                        continue;
                    } else {
                        td.textContent = r[i] ?? '';
                    }
                    tr.appendChild(td);
                }
                const typeTd = document.createElement('td');
                typeTd.className = 'vehicleType'; typeTd.style.display = 'none';
                typeTd.textContent = r[12] || '';
                tr.appendChild(typeTd);
                const regionTd = document.createElement('td');
                regionTd.className = 'region'; regionTd.style.display = 'none';
                regionTd.textContent = r[13] || '';
                tr.appendChild(regionTd);
                // Click -> vehicle trends page with current date range
                tr.addEventListener('click', (ev) => {
                    if (ev.target && ev.target.closest('a')) return; // allow inner links to work
                    const startDate = document.getElementById('startDatePicker').value;
                    const endDate = document.getElementById('endDatePicker').value;
                    const url = new URL(window.location.origin + window.location.pathname.replace(/[^\/]+$/, ''));
                    url.pathname += 'vehicle_trends.php';
                    url.searchParams.set('asset', assetName);
                    if (startDate) url.searchParams.set('startDate', startDate);
                    if (endDate) url.searchParams.set('endDate', endDate);
                    window.location.href = url.toString();
                });
                tbody.appendChild(tr);
            });
        }

        function populateFilters(types, regions) {
            // Clear extra options
            for (let i = typeSelect.options.length - 1; i >= 1; i--) typeSelect.remove(i);
            for (let i = regionSelect.options.length - 1; i >= 1; i--) regionSelect.remove(i);
            types.forEach(t => { const o = document.createElement('option'); o.value = t; o.textContent = t; typeSelect.appendChild(o); });
            regions.forEach(r => { const o = document.createElement('option'); o.value = r; o.textContent = r; regionSelect.appendChild(o); });
        }

        // Build data URL with optional cache bypass
        const qs = new URLSearchParams(window.location.search);
        const bypass = qs.get('bypassCache') === '1' || qs.has('refresh');
        let dataUrl = 'fuel_data.php?startDate=' + encodeURIComponent(startDate) + '&endDate=' + encodeURIComponent(endDate);
        if (bypass) dataUrl += '&bypassCache=1';

        fetch(dataUrl)
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) throw new Error('Failed to load data');
                renderRows(data.tableRows || []);
                setTotals(data.dashboardTotals || {});
                populateFilters(data.vehicleTypes || [], data.regions || []);
                applyFilters();
            })
            .catch(err => {
                console.error('Load error', err);
                tbody.innerHTML = '<tr><td colspan="12" class="text-center text-danger">Failed to load data</td></tr>';
            })
            .finally(() => { if (overlay) overlay.style.display = 'none'; });

        // Filter change
        typeSelect.addEventListener('change', applyFilters);
        regionSelect.addEventListener('change', applyFilters);

        // Import handler: on success, force cache bypass
        const importFileInput = document.getElementById('importFile');
        const importForm = document.getElementById('importForm');
        if (importFileInput && importForm) {
            let busy = false;
            importFileInput.addEventListener('change', function(e) {
                if (busy) return; // prevent duplicate triggers
                const file = importFileInput.files[0];
                if (!file) return;
                busy = true;
                importFileInput.disabled = true; // prevent re-open while uploading
                const formData = new FormData(); formData.append('importFile', file);
                fetch('import_fuel.php', { method: 'POST', body: formData })
                    .then(resp => resp.json())
                    .then(data => {
                        if (data.success) {
                            const msg = typeof data.appendedRows === 'number'
                                ? `Import successful. ${data.appendedRows} new row(s) appended.`
                                : 'Import successful!';
                            alert(msg);
                            const url = new URL(window.location.href);
                            url.searchParams.set('refresh', Date.now());
                            window.location.href = url.toString();
                        } else {
                            alert('Import failed: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(err => alert('Import failed: ' + err))
                    .finally(() => { busy = false; importFileInput.disabled = false; importFileInput.value = ''; });
            }, { once: false });

            const labelBtn = importForm.querySelector('label[for="importFile"]');
            labelBtn.addEventListener('click', function(e) {
                if (busy) { e.preventDefault(); return; }
                // Let browser open the dialog (no manual .click()), avoids double dialog on some browsers
            });
        }
    });
    </script>
        <script>
        // dynamically pad body to navbar height to avoid content overlap and remove extra gaps
        (function(){
            function padToNav(){
                var nav = document.querySelector('header .navbar');
                if (!nav) return;
                var h = Math.ceil(nav.getBoundingClientRect().height);
                document.body.style.paddingTop = (h + 12) + 'px';
                document.documentElement.style.setProperty('--navH', h + 'px');
            }
            padToNav();
            window.addEventListener('load', padToNav);
            window.addEventListener('resize', padToNav);
        })();
        </script>
    </body>
</html>