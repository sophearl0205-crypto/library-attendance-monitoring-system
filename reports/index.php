<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_role(['super_admin', 'moderator']);
$pdo = db();
$mode = is_string($_GET['mode'] ?? null) ? $_GET['mode'] : 'month';
if (!in_array($mode, ['month', 'custom', 'all'], true)) $mode = 'month';
$year = is_string($_GET['year'] ?? null) ? $_GET['year'] : date('Y');
if ($year !== 'all' && !preg_match('/^\d{4}$/', $year)) $year = date('Y');
$months = $_GET['months'] ?? [];
if (!is_array($months)) $months = [];
$months = array_values(array_unique(array_map('intval', array_filter($months, static fn($m) => is_string($m) || is_int($m)))));
$months = array_values(array_filter($months, static fn($m) => $m >= 1 && $m <= 12));
if ($mode === 'month' && !$months && !isset($_GET['months'])) $months = [(int)date('n')];
$from = is_string($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
$to = is_string($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
$deptRaw = $_GET['department_id'] ?? 0;
$dept = is_scalar($deptRaw) ? max(0, (int)$deptRaw) : 0;
$where = [];
$args = [];
if ($mode === 'month') {
    if ($year !== 'all' && $months) {
        $ranges = [];
        foreach ($months as $month) {
            $start = sprintf('%04d-%02d-01', (int)$year, $month);
            $end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
            $ranges[] = '(a.attendance_datetime >= ? AND a.attendance_datetime < ?)';
            array_push($args, $start, $end);
        }
        $where[] = '(' . implode(' OR ', $ranges) . ')';
    } elseif ($year !== 'all') {
        $where[] = 'a.attendance_datetime >= ? AND a.attendance_datetime < ?';
        array_push($args, $year . '-01-01', ((int)$year + 1) . '-01-01');
    } elseif ($months) {
        $where[] = 'MONTH(a.attendance_datetime) IN (' . implode(',', array_fill(0, count($months), '?')) . ')';
        array_push($args, ...$months);
    }
} elseif ($mode === 'custom') {
    $validDate = static function (string $value): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    };
    if (!$validDate($from) || !$validDate($to) || $from > $to) {
        http_response_code(400);
        exit('Choose a valid date range.');
    }
    $where[] = 'a.attendance_datetime >= ? AND a.attendance_datetime < DATE_ADD(?, INTERVAL 1 DAY)';
    array_push($args, $from, $to);
}
if ($dept > 0) { $where[] = 'p.department_id=?'; $args[] = $dept; }
$whereSql = $where ? implode(' AND ', $where) : '1=1';
$baseSql = " FROM attendance_records a JOIN persons p ON p.person_id=a.person_id JOIN departments d ON d.department_id=p.department_id WHERE {$whereSql}";
$departments = $pdo->query("SELECT department_id,department_name FROM departments ORDER BY department_name")->fetchAll();
$years = $pdo->query('SELECT DISTINCT YEAR(attendance_datetime) report_year FROM attendance_records ORDER BY report_year DESC')->fetchAll(PDO::FETCH_COLUMN);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lamas-attendance-report.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date and time', 'School ID', 'Name', 'Type', 'Department', 'Method', 'Record type']);
    $stmt = $pdo->prepare('SELECT a.attendance_datetime,p.school_id,CONCAT(p.first_name,\' \',p.last_name) full_name,p.person_type,d.department_name,a.attendance_method,a.is_demo' . $baseSql . ' ORDER BY a.attendance_datetime DESC');
    $stmt->execute($args);
    while ($record = $stmt->fetch()) {
        $values = [$record['attendance_datetime'], $record['school_id'], $record['full_name'], $record['person_type'], $record['department_name'], $record['attendance_method'], (int)$record['is_demo'] ? 'DEMO' : 'LIVE'];
        foreach ($values as &$value) if (preg_match('/^[\x00-\x20]*[=+@-]/u', (string)$value)) $value = "'" . $value;
        unset($value);
        fputcsv($out, $values);
    }
    fclose($out);
    exit;
}

$total = $pdo->prepare('SELECT COUNT(*)' . $baseSql); $total->execute($args); $totalCount = (int)$total->fetchColumn();
$summary = $pdo->prepare("SELECT DATE_FORMAT(a.attendance_datetime,'%Y-%m') report_month, COUNT(*) visits, SUM(p.person_type='student') student_visits, SUM(p.person_type='faculty') faculty_visits" . $baseSql . ' GROUP BY report_month ORDER BY report_month'); $summary->execute($args); $monthly = $summary->fetchAll();
$deptQ = $pdo->prepare('SELECT d.department_name,COUNT(*) visits' . $baseSql . ' GROUP BY d.department_id ORDER BY visits DESC'); $deptQ->execute($args); $byDept = $deptQ->fetchAll();
$studentQ = $pdo->prepare("SELECT COUNT(*)" . $baseSql . " AND p.person_type='student'"); $studentQ->execute($args); $studentCount = (int)$studentQ->fetchColumn();
$facultyQ = $pdo->prepare("SELECT COUNT(*)" . $baseSql . " AND p.person_type='faculty'"); $facultyQ->execute($args); $facultyCount = (int)$facultyQ->fetchColumn();
$printAll = ($_GET['print'] ?? '') === '1';
$rowsSql = 'SELECT a.attendance_datetime,p.school_id,CONCAT(p.first_name,\' \',p.last_name) full_name,p.person_type,d.department_name,a.attendance_method,a.is_demo' . $baseSql . ' ORDER BY a.attendance_datetime DESC' . ($printAll ? '' : ' LIMIT 300');
$rowsQ = $pdo->prepare($rowsSql); $rowsQ->execute($args); $records = $rowsQ->fetchAll();
$query = $_GET; unset($query['export']); $query['export'] = 'csv';
$printQuery = $_GET; unset($printQuery['export']); $printQuery['print'] = '1';
$pageTitle = 'Reports & Analytics'; require __DIR__ . '/../includes/header.php';
?>
<style>@media print{.app-navbar,.sidebar,.btn,form{display:none!important}.app-shell{display:block}.main-content{padding:0;margin:0}.panel,.stat-card{box-shadow:none;border:0}.page-heading{color:#000}.table{font-size:10pt}}</style>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4"><div><h1 class="h2 page-heading mb-1">Reports &amp; Analytics</h1><p class="text-secondary mb-0">Monthly attendance summaries and exports.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-success" href="?<?=e(http_build_query($printQuery))?>">Print / Save PDF</a><a class="btn btn-success" href="?<?=e(http_build_query($query))?>">Export CSV</a></div></div>
<div class="panel mb-4"><form class="row g-2 align-items-end" method="get"><div class="col-md-3"><label class="form-label" for="mode">Report period</label><select id="mode" class="form-select" name="mode"><option value="month" <?=$mode==='month'?'selected':''?>>Selected months</option><option value="custom" <?=$mode==='custom'?'selected':''?>>Custom date range</option><option value="all" <?=$mode==='all'?'selected':''?>>All time</option></select></div><div class="col-md-2 period-month"><label class="form-label" for="year">Year</label><select id="year" class="form-select" name="year"><option value="all" <?=$year==='all'?'selected':''?>>All years</option><?php foreach(array_unique(array_merge([date('Y')], array_map('strval',$years))) as $availableYear): ?><option value="<?=e((string)$availableYear)?>" <?=$year===(string)$availableYear?'selected':''?>><?=e((string)$availableYear)?></option><?php endforeach; ?></select></div><div class="col-md-3 period-month"><label class="form-label" for="months">Months (choose one or more)</label><select id="months" class="form-select" name="months[]" multiple size="4"><?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=in_array($m,$months,true)?'selected':''?>><?=e(date('F', mktime(0,0,0,$m,1)))?></option><?php endfor; ?></select></div><div class="col-md-2 period-custom"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?=e($from)?>"></div><div class="col-md-2 period-custom"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?=e($to)?>"></div><div class="col-md-3"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="0">All departments</option><?php foreach($departments as $d): ?><option value="<?=$d['department_id']?>" <?=$dept===(int)$d['department_id']?'selected':''?>><?=e($d['department_name'])?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-outline-success w-100">Apply filters</button></div></form><p class="small text-secondary mb-0 mt-2">All time exports every matching record from the first recorded visit onward. Demo records are labeled in tables and CSV exports.</p></div>
<div class="row g-3 mb-4"><div class="col-md-4"><div class="stat-card"><div class="stat-label">Total visits</div><div class="stat-value"><?=number_format($totalCount)?></div></div></div><div class="col-md-4"><div class="stat-card"><div class="stat-label">Student visits</div><div class="stat-value"><?=number_format($studentCount)?></div></div></div><div class="col-md-4"><div class="stat-card"><div class="stat-label">Faculty visits</div><div class="stat-value"><?=number_format($facultyCount)?></div></div></div></div>
<div class="row g-3 mb-4"><div class="col-lg-8"><div class="panel"><h2 class="h5 fw-bold">Visits by department</h2><canvas id="deptChart" height="120"></canvas></div></div><div class="col-lg-4"><div class="panel"><h2 class="h5 fw-bold">Visitor type</h2><canvas id="typeChart"></canvas></div></div></div>
<div class="panel mb-4"><h2 class="h5 fw-bold mb-3">Monthly totals</h2><div class="table-responsive"><table class="table"><thead><tr><th>Month</th><th>Total</th><th>Student visits</th><th>Faculty visits</th></tr></thead><tbody><?php foreach($monthly as $month): ?><tr><td><?=e(date('F Y', strtotime($month['report_month'].'-01')))?></td><td><?=number_format((int)$month['visits'])?></td><td><?=number_format((int)$month['student_visits'])?></td><td><?=number_format((int)$month['faculty_visits'])?></td></tr><?php endforeach; ?><?php if(!$monthly): ?><tr><td colspan="4" class="text-center text-secondary">No attendance records for this selection.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><h2 class="h5 fw-bold mb-3">Attendance records (showing <?=count($records)?> of <?=number_format($totalCount)?>)</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date &amp; time</th><th>Visitor</th><th>School ID</th><th>Type</th><th>Department</th><th>Method</th><th>Record</th></tr></thead><tbody><?php foreach($records as $r): ?><tr><td><?=e($r['attendance_datetime'])?></td><td><?=e($r['full_name'])?></td><td><?=e($r['school_id'])?></td><td><?=e(ucfirst($r['person_type']))?></td><td><?=e($r['department_name'])?></td><td><?=e(strtoupper($r['attendance_method']))?></td><td><?=$r['is_demo'] ? '<span class="badge text-bg-warning">DEMO</span>' : 'Live'?></td></tr><?php endforeach; ?><?php if(!$records): ?><tr><td colspan="7" class="text-center text-secondary py-4">No attendance records in this period.</td></tr><?php endif; ?></tbody></table></div><?php if($totalCount>300 && !$printAll): ?><p class="small text-secondary mb-0">This page shows the first 300 rows. Print / Save PDF and CSV include every matching record.</p><?php endif; ?></div>
<script src="<?=BASE_URL?>/assets/vendor/chart.js/chart.umd.min.js"></script><script>
const labels=<?=json_encode(array_column($byDept,'department_name'),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
const values=<?=json_encode(array_map('intval',array_column($byDept,'visits')))?>;
new Chart(document.getElementById('deptChart'),{type:'bar',data:{labels,datasets:[{label:'Visits',data:values,backgroundColor:'#26845d',borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
new Chart(document.getElementById('typeChart'),{type:'doughnut',data:{labels:['Students','Faculty'],datasets:[{data:[<?=$studentCount?>,<?=$facultyCount?>],backgroundColor:['#26845d','#b8d9c7']}]},options:{plugins:{legend:{position:'bottom'}}}});
const mode=document.getElementById('mode');function updatePeriodFields(){document.querySelectorAll('.period-month').forEach(el=>el.hidden=mode.value!=='month');document.querySelectorAll('.period-custom').forEach(el=>el.hidden=mode.value!=='custom');}mode.addEventListener('change',updatePeriodFields);updatePeriodFields();
if(new URLSearchParams(location.search).get('print')==='1')window.addEventListener('load',()=>window.print());
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
