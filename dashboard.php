<?php
require_once __DIR__ . '/includes/helpers.php'; require_login();
$pdo=db();
$total=(int)$pdo->query("SELECT COUNT(*) FROM attendance_records")->fetchColumn();
$today=(int)$pdo->query("SELECT COUNT(*) FROM attendance_records WHERE DATE(attendance_datetime)=CURDATE()")->fetchColumn();
$people=(int)$pdo->query("SELECT COUNT(*) FROM persons WHERE status='active'")->fetchColumn();
$departments=(int)$pdo->query("SELECT COUNT(*) FROM departments WHERE status='active'")->fetchColumn();
$rows=$pdo->query("SELECT d.department_name,COUNT(a.attendance_id) visits FROM departments d LEFT JOIN persons p ON p.department_id=d.department_id LEFT JOIN attendance_records a ON a.person_id=p.person_id GROUP BY d.department_id ORDER BY visits DESC,d.department_name")->fetchAll();
$recent=$pdo->query("SELECT a.attendance_datetime,a.attendance_method,p.school_id,CONCAT(p.first_name,' ',p.last_name) person_name,p.person_type,d.department_name FROM attendance_records a JOIN persons p ON p.person_id=a.person_id JOIN departments d ON d.department_id=p.department_id ORDER BY a.attendance_datetime DESC LIMIT 8")->fetchAll();
$pageTitle='Dashboard'; require __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4"><div><h1 class="page-heading h2 mb-1">Dashboard</h1><p class="text-secondary mb-0">Library attendance overview</p></div><span class="badge text-bg-light border"><?=date('F d, Y')?></span></div>
<div class="row g-3 mb-4">
<?php foreach([['Total visits',$total,'↗'],["Today's visits",$today,'◷'],['Registered people',$people,'♙'],['Departments',$departments,'▤']] as $s): ?>
<div class="col-12 col-sm-6 col-xl-3"><div class="stat-card h-100"><div class="d-flex justify-content-between"><span class="stat-label"><?=e($s[0])?></span><span class="stat-icon"><?=$s[2]?></span></div><div class="stat-value mt-3"><?=number_format($s[1])?></div></div></div>
<?php endforeach; ?></div>
<div class="row g-3 mb-4 align-items-stretch"><div class="col-12 col-xl-7"><div class="panel h-100"><h2 class="h5 fw-bold mb-3">Visits by department</h2><div class="chart-wrap department-chart-wrap"><canvas id="deptChart"></canvas></div></div></div><div class="col-12 col-xl-5"><div class="panel h-100"><h2 class="h5 fw-bold mb-3">Visitor type</h2><div class="chart-wrap visitor-chart-wrap"><canvas id="typeChart"></canvas></div></div></div></div>
<div class="panel"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Recent attendance</h2><a href="<?=BASE_URL?>/attendance/index.php" class="btn btn-sm btn-outline-success">View all</a></div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date & time</th><th>Person</th><th>School ID</th><th>Department</th><th>Method</th></tr></thead><tbody>
<?php foreach($recent as $r): ?><tr><td><?=e($r['attendance_datetime'])?></td><td><?=e($r['person_name'])?><div class="small text-secondary"><?=e(ucfirst($r['person_type']))?></div></td><td><?=e($r['school_id'])?></td><td><?=e($r['department_name'])?></td><td><span class="badge text-bg-light"><?=e(strtoupper($r['attendance_method']))?></span></td></tr><?php endforeach; ?>
<?php if(!$recent): ?><tr><td colspan="5" class="text-center text-secondary py-4">No attendance records yet. Add people and record the first visit.</td></tr><?php endif; ?>
</tbody></table></div></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>
const labels=<?=json_encode(array_column($rows,'department_name'),JSON_HEX_TAG|JSON_HEX_APOS)?>;
const values=<?=json_encode(array_map('intval',array_column($rows,'visits')))?>;
const deptCanvas=document.getElementById('deptChart');
deptCanvas.parentElement.style.height=Math.max(300,Math.min(labels.length*32,520))+'px';
new Chart(deptCanvas,{type:'bar',data:{labels,datasets:[{label:'Visits',data:values,backgroundColor:'#26845d',borderRadius:6,maxBarThickness:22}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0},grid:{color:'#edf1ee'}},y:{ticks:{autoSkip:false,font:{size:11},callback(value){const label=this.getLabelForValue(value);return label.length>28?label.slice(0,26)+'…':label;}},grid:{display:false}}}}});
new Chart(document.getElementById('typeChart'),{type:'doughnut',data:{labels:['Students','Faculty'],datasets:[{data:[<?= (int)$pdo->query("SELECT COUNT(*) FROM attendance_records a JOIN persons p ON p.person_id=a.person_id WHERE p.person_type='student'")->fetchColumn()?>,<?= (int)$pdo->query("SELECT COUNT(*) FROM attendance_records a JOIN persons p ON p.person_id=a.person_id WHERE p.person_type='faculty'")->fetchColumn()?>],backgroundColor:['#26845d','#b8d9c7'],borderColor:'#fff',borderWidth:3}]},options:{responsive:true,maintainAspectRatio:false,cutout:'62%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyle:'rect',padding:18}}}}});
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
