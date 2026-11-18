<?php
require_once __DIR__ . '/../includes/helpers.php';
require_role(['super_admin', 'moderator']);
$pdo=db(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf(); $action=$_POST['action']??'';
 try {
  if ($action==='department') {
   $name=trim($_POST['department_name']??'');
   if ($name==='') throw new RuntimeException('Enter a department name.');
   $q=$pdo->prepare("INSERT INTO departments(department_name,category_type) VALUES(?,?)");
   $q->execute([$name,in_array($_POST['category_type']??'program',['program','faculty','other'],true)?$_POST['category_type']:'program']);
   flash('success','Department added.'); redirect('persons/index.php');
  }
  if ($action==='save') {
   $id=(int)($_POST['person_id']??0); $school=trim($_POST['school_id']??''); $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??'');
   $type=$_POST['person_type']??'student'; $dept=(int)($_POST['department_id']??0);
   if(!$school||!$first||!$last||!in_array($type,['student','faculty'],true)||$dept<1) throw new RuntimeException('Complete all required fields.');
   if($id) {
    $q=$pdo->prepare("UPDATE persons SET school_id=?,first_name=?,last_name=?,person_type=?,department_id=? WHERE person_id=?");
    $q->execute([$school,$first,$last,$type,$dept,$id]); flash('success','Person details updated.');
   } else {
    $token=bin2hex(random_bytes(24));
    $q=$pdo->prepare("INSERT INTO persons(school_id,first_name,last_name,person_type,department_id,qr_token_hash) VALUES(?,?,?,?,?,?)");
    $q->execute([$school,$first,$last,$type,$dept,hash('sha256',$token)]);
    $_SESSION['new_qr_token']=$token; $_SESSION['new_qr_name']=$first.' '.$last;
    flash('success','Person registered. Save the QR code now; the token is shown only once.');
   }
   redirect('persons/index.php');
  }
  if ($action==='status') {
   $id=(int)($_POST['person_id']??0); $status=($_POST['status']??'inactive')==='active'?'active':'inactive';
   $q=$pdo->prepare("UPDATE persons SET status=? WHERE person_id=?"); $q->execute([$status,$id]);
   flash('success','Person status updated.'); redirect('persons/index.php');
  }
  if ($action==='delete') {
   if (!is_super_admin()) { http_response_code(403); exit('Access denied.'); }
   $id=input_int($_POST,'person_id');
   if ($id<1) throw new RuntimeException('Select a valid student or faculty record.');
   $pdo->beginTransaction();
   $q=$pdo->prepare('SELECT school_id,first_name,last_name,person_type FROM persons WHERE person_id=? FOR UPDATE');
   $q->execute([$id]); $person=$q->fetch();
   if (!$person) throw new RuntimeException('This student or faculty record no longer exists.');
   $q=$pdo->prepare('SELECT COUNT(*) FROM attendance_records WHERE person_id=?');
   $q->execute([$id]);
   if ((int)$q->fetchColumn()>0) throw new RuntimeException('This record has attendance history and cannot be deleted. Deactivate it instead to preserve history.');
   $q=$pdo->prepare('DELETE FROM persons WHERE person_id=?'); $q->execute([$id]);
   audit_log('person.delete',sprintf('Deleted %s record %s (%s %s) with no attendance history.', $person['person_type'], $person['school_id'], $person['first_name'], $person['last_name']));
   $pdo->commit();
   flash('success',ucfirst($person['person_type']).' record deleted.'); redirect('persons/index.php');
  }
  if ($action==='renew_qr') {
   $id=(int)($_POST['person_id']??0); $token=bin2hex(random_bytes(24));
   $q=$pdo->prepare("UPDATE persons SET qr_token_hash=? WHERE person_id=?"); $q->execute([hash('sha256',$token),$id]);
   $_SESSION['new_qr_token']=$token; $_SESSION['new_qr_name']='Updated QR code'; flash('success','QR code renewed. Previous QR code is no longer valid.'); redirect('persons/index.php');
  }
 } catch(Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$ex instanceof PDOException && $ex->getCode()==='23000' ? 'That school ID or department already exists.' : $ex->getMessage(); }
}
$edit=null;
if(isset($_GET['edit'])) { $q=$pdo->prepare("SELECT * FROM persons WHERE person_id=?"); $q->execute([(int)$_GET['edit']]); $edit=$q->fetch()?:null; }
$departments=$pdo->query("SELECT department_id,department_name FROM departments WHERE status='active' ORDER BY department_name")->fetchAll();
$search=trim($_GET['q']??''); $type=$_GET['type']??'';
$sql="SELECT p.*,d.department_name,(SELECT COUNT(*) FROM attendance_records ar WHERE ar.person_id=p.person_id) AS attendance_count FROM persons p JOIN departments d ON d.department_id=p.department_id WHERE (p.school_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)";
$args=["%$search%","%$search%","%$search%"];
if(in_array($type,['student','faculty'],true)){ $sql.=" AND p.person_type=?"; $args[]=$type; }
$sql.=" ORDER BY p.last_name,p.first_name LIMIT 250"; $q=$pdo->prepare($sql); $q->execute($args); $people=$q->fetchAll();
$newToken=$_SESSION['new_qr_token']??null; $newName=$_SESSION['new_qr_name']??''; unset($_SESSION['new_qr_token'],$_SESSION['new_qr_name']);
$pageTitle='Students & Faculty'; require __DIR__.'/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4"><div><h1 class="h2 page-heading mb-1">Students &amp; Faculty</h1><p class="text-secondary mb-0">Register and manage library visitors.</p></div><span class="badge text-bg-light border"><?=count($people)?> shown</span></div>
<?php if($msg=flash('success')): ?><div class="alert alert-success"><?=e($msg)?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if($newToken): ?><div class="panel mb-4 d-flex flex-wrap align-items-center gap-4"><div><h2 class="h5">QR code for <?=e($newName)?></h2><p class="small text-secondary mb-2">Print or save this QR code. It will not be displayed again.</p><div id="newQr"></div></div><button class="btn btn-outline-success" onclick="window.print()">Print this page</button></div><script src="<?=BASE_URL?>/assets/vendor/qrcodejs/qrcode.min.js"></script><script>new QRCode(document.getElementById('newQr'),{text:<?=json_encode($newToken,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>,width:180,height:180});</script><?php endif; ?>
<div class="panel mb-4"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h2 class="h5 fw-bold mb-0"><?=$edit?'Edit person':'Register a person'?></h2><a class="btn btn-sm btn-outline-success" href="<?=BASE_URL?>/departments/index.php">Manage departments</a></div>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="person_id" value="<?=e((string)($edit['person_id']??''))?>">
<div class="row g-3"><div class="col-md-6"><label class="form-label">School ID</label><input class="form-control" name="school_id" required value="<?=e($edit['school_id']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Person type</label><select class="form-select" name="person_type"><option value="student" <?=($edit['person_type']??'student')==='student'?'selected':''?>>Student</option><option value="faculty" <?=($edit['person_type']??'')==='faculty'?'selected':''?>>Faculty</option></select></div>
<div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name" required value="<?=e($edit['first_name']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" required value="<?=e($edit['last_name']??'')?>"></div>
<div class="col-12"><label class="form-label">Department / Program</label><select class="form-select" name="department_id" required><option value="">Select department</option><?php foreach($departments as $d): ?><option value="<?=$d['department_id']?>" <?=((int)($edit['department_id']??0)===(int)$d['department_id'])?'selected':''?>><?=e($d['department_name'])?></option><?php endforeach; ?></select></div></div>
<div class="d-flex gap-2 mt-3"><button class="btn btn-success"><?=$edit?'Save changes':'Register & generate QR'?></button><?php if($edit): ?><a class="btn btn-light" href="index.php">Cancel</a><?php endif; ?></div></form></div>
<div class="panel"><form class="row g-2 mb-3"><div class="col-md-7"><input class="form-control" name="q" placeholder="Search name or school ID" value="<?=e($search)?>"></div><div class="col-md-3"><select class="form-select" name="type"><option value="">All types</option><option value="student" <?=$type==='student'?'selected':''?>>Students</option><option value="faculty" <?=$type==='faculty'?'selected':''?>>Faculty</option></select></div><div class="col-md-2"><button class="btn btn-outline-success w-100">Search</button></div></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>School ID</th><th>Name</th><th>Type</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($people as $p): ?><tr><td><?=e($p['school_id'])?></td><td><?=e($p['first_name'].' '.$p['last_name'])?></td><td><?=e(ucfirst($p['person_type']))?></td><td><?=e($p['department_name'])?></td><td><span class="badge <?=$p['status']==='active'?'text-bg-success':'text-bg-secondary'?>"><?=e(ucfirst($p['status']))?></span></td><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-secondary" href="?edit=<?=$p['person_id']?>">Edit</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="person_id" value="<?=$p['person_id']?>"><input type="hidden" name="status" value="<?=$p['status']==='active'?'inactive':'active'?>"><button class="btn btn-sm btn-outline-<?= $p['status']==='active'?'danger':'success'?>"><?=$p['status']==='active'?'Deactivate':'Activate'?></button></form><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="renew_qr"><input type="hidden" name="person_id" value="<?=$p['person_id']?>"><button class="btn btn-sm btn-outline-success" title="Generate a replacement QR">QR</button></form><?php if(is_super_admin()): ?><form method="post" onsubmit="return confirm('Permanently delete this <?=e($p['person_type'])?> record? This cannot be undone.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="person_id" value="<?=$p['person_id']?>"><button class="btn btn-sm btn-outline-danger" <?=$p['attendance_count']>0?'disabled title="Record has attendance history; deactivate it instead."':''?>>Delete</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if(!$people): ?><tr><td colspan="6" class="text-center text-secondary py-4">No matching people found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
