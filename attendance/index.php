<?php
require_once __DIR__ . '/../includes/helpers.php'; require_login(); $pdo=db(); $message=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $method=$_POST['method']??'manual'; $person=null;
 if($method==='qr'){
  $token=trim($_POST['qr_token']??'');
  if($token!==''){ $q=$pdo->prepare("SELECT person_id,school_id,first_name,last_name FROM persons WHERE qr_token_hash=? AND status='active' LIMIT 1"); $q->execute([hash('sha256',$token)]); $person=$q->fetch(); }
 } else {
  $q=$pdo->prepare("SELECT person_id,school_id,first_name,last_name FROM persons WHERE person_id=? AND status='active'"); $q->execute([(int)($_POST['person_id']??0)]); $person=$q->fetch();
 }
 if(!$person) $error='No active registered person found. Check the selected person or QR code.';
 else {
  $dup=$pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE person_id=? AND attendance_datetime >= DATE_SUB(NOW(),INTERVAL 30 SECOND)"); $dup->execute([$person['person_id']]);
  if((int)$dup->fetchColumn()>0) $error='This person was already recorded within the last 30 seconds.';
  else { $q=$pdo->prepare("INSERT INTO attendance_records(person_id,attendance_method,recorded_by) VALUES(?,?,?)"); $q->execute([$person['person_id'],$method==='qr'?'qr':'manual',current_user()['user_id']]); $message='Attendance recorded for '.$person['first_name'].' '.$person['last_name'].' ('.$person['school_id'].').'; }
 }
}
$people=$pdo->query("SELECT person_id,school_id,first_name,last_name,person_type FROM persons WHERE status='active' ORDER BY last_name,first_name")->fetchAll();
$recent=$pdo->query("SELECT a.attendance_datetime,a.attendance_method,p.school_id,CONCAT(p.first_name,' ',p.last_name) full_name,p.person_type,d.department_name FROM attendance_records a JOIN persons p ON p.person_id=a.person_id JOIN departments d ON d.department_id=p.department_id ORDER BY a.attendance_datetime DESC LIMIT 20")->fetchAll();
$pageTitle='Attendance'; require __DIR__.'/../includes/header.php';
?>
<div class="mb-4"><h1 class="h2 page-heading mb-1">Attendance</h1><p class="text-secondary mb-0">Record library visits manually or by QR code.</p></div>
<?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<div class="row g-3 mb-4"><div class="col-lg-6"><div class="panel h-100"><h2 class="h5 fw-bold">Manual attendance</h2><p class="small text-secondary">Select a registered active visitor.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="method" value="manual"><label class="form-label">Student / Faculty</label><select class="form-select mb-3" name="person_id" required><option value="">Choose a person</option><?php foreach($people as $p): ?><option value="<?=$p['person_id']?>"><?=e($p['school_id'].' — '.$p['last_name'].', '.$p['first_name'].' ('.ucfirst($p['person_type']).')')?></option><?php endforeach; ?></select><button class="btn btn-success">Record attendance</button></form></div></div>
<div class="col-lg-6"><div class="panel h-100"><h2 class="h5 fw-bold">QR attendance</h2><p class="small text-secondary">Allow camera access, scan the person's QR code, then confirm the token.</p><div id="qr-reader" style="max-width:420px"></div><form method="post" id="qrForm" class="mt-3"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="method" value="qr"><label class="form-label">Scanned QR token</label><input class="form-control mb-2" id="qr_token" name="qr_token" placeholder="Scan or paste token" required><button class="btn btn-outline-success">Record QR attendance</button></form></div></div></div>
<div class="panel"><h2 class="h5 fw-bold mb-3">Recent attendance</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date &amp; time</th><th>Visitor</th><th>School ID</th><th>Type</th><th>Department</th><th>Method</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><?=e($r['attendance_datetime'])?></td><td><?=e($r['full_name'])?></td><td><?=e($r['school_id'])?></td><td><?=e(ucfirst($r['person_type']))?></td><td><?=e($r['department_name'])?></td><td><span class="badge text-bg-light"><?=e(strtoupper($r['attendance_method']))?></span></td></tr><?php endforeach; ?><?php if(!$recent): ?><tr><td colspan="6" class="text-center text-secondary">No records yet.</td></tr><?php endif; ?></tbody></table></div></div>
<script src="https://unpkg.com/html5-qrcode"></script><script>
if(window.Html5Qrcode){const scanner=new Html5Qrcode("qr-reader");Html5Qrcode.getCameras().then(c=>{if(c&&c.length)scanner.start(c[0].id,{fps:10,qrbox:{width:220,height:220}},decoded=>{document.getElementById('qr_token').value=decoded;scanner.stop();},()=>{}).catch(()=>{});}).catch(()=>{});}
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>