<?php
require_once __DIR__ . '/includes/helpers.php';
if (current_user()) redirect('dashboard.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $stmt=db()->prepare("SELECT user_id,full_name,username,password,role,status FROM users WHERE username=? LIMIT 1");
 $stmt->execute([trim(input_string($_POST,'username'))]); $u=$stmt->fetch();
 if($u && $u['status']==='active' && password_verify(input_string($_POST,'password'),$u['password'])){
  session_regenerate_id(true); unset($u['password']); $u['role']=normalized_role($u['role']); $_SESSION['user']=$u; redirect('dashboard.php');
 }
 $error='Invalid credentials or inactive account.';
}
$pageTitle='Sign in'; require __DIR__.'/includes/header.php';
?>
<div class="container py-5" style="max-width:460px"><div class="panel">
<div class="text-center mb-4"><div class="display-5">🌿</div><h1 class="h3 page-heading">Welcome to LAMAS</h1><p class="text-secondary">Library Attendance Monitoring & Analytics</p></div>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label class="form-label">Username</label><input class="form-control mb-3" name="username" autocomplete="username" required>
<label class="form-label">Password</label><input class="form-control mb-3" type="password" name="password" autocomplete="current-password" required>
<button class="btn btn-success w-100">Sign in</button></form>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
