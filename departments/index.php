<?php
require_once __DIR__ . '/../includes/helpers.php';
require_login();
$pdo=db(); $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $action=$_POST['action']??'';
 try{
  if($action==='save'){
   $id=(int)($_POST['department_id']??0); $name=trim($_POST['department_name']??'');
   $category=$_POST['category_type']??'program';
   if($name==='') throw new RuntimeException('Department name is required.');
   if(!in_array($category,['program','faculty','other'],true)) throw new RuntimeException('Choose a valid category.');
   if($id){$q=$pdo->prepare("UPDATE departments SET department_name=?,category_type=? WHERE department_id=?");$q->execute([$name,$category,$id]);flash('success','Department updated.');}
   else{$q=$pdo->prepare("INSERT INTO departments(department_name,category_type) VALUES(?,?)");$q->execute([$name,$category]);flash('success','Department added.');}
   redirect('departments/index.php');
  }
  if($action==='status'){
   $id=(int)($_POST['department_id']??0);$status=($_POST['status']??'inactive')==='active'?'active':'inactive';
   $q=$pdo->prepare("UPDATE departments SET status=? WHERE department_id=?");$q->execute([$status,$id]);
   flash('success','Department status updated.');redirect('departments/index.php');
  }
 }catch(Throwable $ex){$error=$ex instanceof PDOException && $ex->getCode()==='23000'?'A department with that name already exists.':$ex->getMessage();}
}
$edit=null;
if(isset($_GET['edit'])){$q=$pdo->prepare("SELECT * FROM departments WHERE department_id=?");$q->execute([(int)$_GET['edit']]);$edit=$q->fetch()?:null;}
$search=trim($_GET['q']??'');
$q=$pdo->prepare("SELECT d.department_id,d.department_name,d.category_type,d.status,COUNT(DISTINCT p.person_id) people,COUNT(a.attendance_id) visits FROM departments d LEFT JOIN persons p ON p.department_id=d.department_id LEFT JOIN attendance_records a ON a.person_id=p.person_id WHERE d.department_name LIKE ? GROUP BY d.department_id ORDER BY d.department_name");
$q->execute(["%$search%"]);$departments=$q->fetchAll();
$pageTitle='Departments';require __DIR__.'/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="h2 page-heading mb-1">Departments</h1><p class="text-secondary mb-0">View and manage all departments and programs.</p></div><a class="btn btn-outline-success" href="<?=BASE_URL?>/persons/index.php">Students &amp; Faculty</a></div>
<?php if($msg=flash('success')): ?><div class="alert alert-success"><?=e($msg)?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<div class="row g-3 mb-4"><div class="col-lg-4"><div class="panel h-100"><h2 class="h5 fw-bold mb-3"><?=$edit?'Edit department':'Add department'?></h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="department_id" value="<?=e((string)($edit['department_id']??''))?>"><label class="form-label">Department name</label><input class="form-control mb-3" name="department_name" maxlength="100" required value="<?=e($edit['department_name']??'')?>"><label class="form-label">Category</label><select class="form-select mb-3" name="category_type"><option value="program" <?=($edit['category_type']??'program')==='program'?'selected':''?>>Program</option><option value="faculty" <?=($edit['category_type']??'')==='faculty'?'selected':''?>>Faculty</option><option value="other" <?=($edit['category_type']??'')==='other'?'selected':''?>>Other</option></select><div class="d-flex gap-2"><button class="btn btn-success"><?=$edit?'Save changes':'Add department'?></button><?php if($edit): ?><a class="btn btn-light" href="index.php">Cancel</a><?php endif; ?></div></form></div></div>
<div class="col-lg-8"><div class="panel h-100"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 fw-bold mb-0">All departments</h2><span class="badge text-bg-light border"><?=count($departments)?> listed</span></div><form class="input-group mb-3"><input class="form-control" name="q" placeholder="Search departments" value="<?=e($search)?>"><button class="btn btn-outline-success">Search</button></form><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Department</th><th>Category</th><th>People</th><th>Visits</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($departments as $d): ?><tr><td class="fw-semibold"><?=e($d['department_name'])?></td><td><?=e(ucfirst($d['category_type']))?></td><td><?=number_format((int)$d['people'])?></td><td><?=number_format((int)$d['visits'])?></td><td><span class="badge <?=$d['status']==='active'?'text-bg-success':'text-bg-secondary'?>"><?=e(ucfirst($d['status']))?></span></td><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-secondary" href="?edit=<?=$d['department_id']?>">Edit</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="department_id" value="<?=$d['department_id']?>"><input type="hidden" name="status" value="<?=$d['status']==='active'?'inactive':'active'?>"><button class="btn btn-sm btn-outline-<?=$d['status']==='active'?'danger':'success'?>"><?=$d['status']==='active'?'Deactivate':'Activate'?></button></form></div></td></tr><?php endforeach; ?><?php if(!$departments): ?><tr><td colspan="6" class="text-center text-secondary py-4">No departments found.</td></tr><?php endif; ?></tbody></table></div><p class="small text-secondary mb-0">Departments with existing records are deactivated rather than deleted, preserving attendance history.</p></div></div></div>
<?php require __DIR__.'/../includes/footer.php'; ?>