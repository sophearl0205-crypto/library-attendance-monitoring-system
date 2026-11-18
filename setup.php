<?php
require_once __DIR__ . '/includes/helpers.php';
$count = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && $count===0) {
 verify_csrf();
 $name=trim($_POST['full_name']??''); $username=trim($_POST['username']??'');
 $password=$_POST['password']??'';
 if ($name==='' || $username==='' || strlen($password)<10) $error='Enter a name, username, and password of at least 10 characters.';
 else {
  $stmt=db()->prepare("INSERT INTO users(full_name,username,password,role) VALUES(?,?,?,'super_admin')");
  $stmt->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT)]);
  redirect('login.php');
 }
}
$pageTitle='Initial administrator setup'; require __DIR__.'/includes/header.php';
?>
<div class="container py-5" style="max-width:520px"><div class="panel">
<h1 class="h3 page-heading">Create administrator</h1>
<p class="text-secondary">One-time setup for the first system administrator.</p>
<?php if($count>0): ?><div class="alert alert-info">An account already exists. Please sign in.</div><a class="btn btn-success" href="login.php">Go to login</a>
<?php else: ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label class="form-label">Full name</label><input class="form-control mb-3" name="full_name" required>
<label class="form-label">Username</label><input class="form-control mb-3" name="username" required>
<label class="form-label">Password (10+ characters)</label><input class="form-control mb-3" type="password" name="password" minlength="10" required>
<button class="btn btn-success w-100">Create administrator</button></form>
<?php endif; ?></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
