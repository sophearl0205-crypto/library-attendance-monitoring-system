<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_role(['super_admin', 'moderator']);
$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!is_super_admin()) { http_response_code(403); exit('Access denied.'); }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = input_int($_POST, 'user_id');
            $name = trim(input_string($_POST, 'full_name'));
            $username = trim(input_string($_POST, 'username'));
            $role = input_string($_POST, 'role');
            $status = input_string($_POST, 'status', 'active');
            $password = input_string($_POST, 'password');
            if ($name === '' || $username === '' || !in_array($role, ['super_admin', 'moderator'], true) || !in_array($status, ['active', 'inactive'], true)) {
                throw new RuntimeException('Complete all required fields with valid values.');
            }
            if ($id > 0) {
                $existing = $pdo->prepare('SELECT role,status FROM users WHERE user_id=?');
                $existing->execute([$id]);
                $before = $existing->fetch();
                if ($before && normalized_role($before['role']) === 'super_admin' && $before['status'] === 'active' && ($role !== 'super_admin' || $status !== 'active')) {
                    $activeAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin' AND status='active'")->fetchColumn();
                    if ($activeAdmins <= 1) throw new RuntimeException('The last active Super Admin cannot be demoted or deactivated.');
                }
                if ($id === (int)current_user()['user_id'] && ($role !== 'super_admin' || $status !== 'active')) {
                    throw new RuntimeException('You cannot remove your own Super Admin access or deactivate your account.');
                }
                if ($password !== '') {
                    if (strlen($password) < 10) throw new RuntimeException('Passwords must contain at least 10 characters.');
                    $stmt = $pdo->prepare('UPDATE users SET full_name=?, username=?, role=?, status=?, password=? WHERE user_id=?');
                    $stmt->execute([$name, $username, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET full_name=?, username=?, role=?, status=? WHERE user_id=?');
                    $stmt->execute([$name, $username, $role, $status, $id]);
                }
                audit_log('user.update', 'Updated user account ID ' . $id . '.');
                flash('success', 'User updated.');
            } else {
                if (strlen($password) < 10) throw new RuntimeException('Passwords must contain at least 10 characters.');
                $stmt = $pdo->prepare('INSERT INTO users(full_name,username,password,role,status) VALUES(?,?,?,?,?)');
                $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
                audit_log('user.create', 'Created user account ' . $username . '.');
                flash('success', 'User created.');
            }
            redirect('users/index.php');
        }
        if ($action === 'delete') {
            $id = input_int($_POST, 'user_id');
            if ($id < 1 || $id === (int)current_user()['user_id']) throw new RuntimeException('You cannot delete your own account.');
            $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id=? AND status='active'");
            $stmt->execute([$id]);
            $targetRole = normalized_role($stmt->fetchColumn() ?: null);
            if ($targetRole === 'super_admin' && (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin' AND status='active'")->fetchColumn() <= 1) {
                throw new RuntimeException('The last active Super Admin cannot be deleted.');
            }
            $refs = $pdo->prepare('SELECT (SELECT COUNT(*) FROM attendance_records WHERE recorded_by=?) + (SELECT COUNT(*) FROM audit_logs WHERE user_id=?)');
            $refs->execute([$id, $id]);
            if ((int)$refs->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE users SET status='inactive' WHERE user_id=?");
                $stmt->execute([$id]);
                audit_log('user.deactivate', 'Deactivated user account ID ' . $id . ' to preserve history.');
                flash('success', 'This account has historical records, so it was deactivated to preserve attendance and audit history.');
            } else {
                audit_log('user.delete', 'Deleted user account ID ' . $id . '.');
                $stmt = $pdo->prepare('DELETE FROM users WHERE user_id=?');
                $stmt->execute([$id]);
                flash('success', 'User deleted.');
            }
            redirect('users/index.php');
        }
    } catch (Throwable $ex) {
        $error = $ex instanceof PDOException && $ex->getCode() === '23000' ? 'That username is already in use.' : $ex->getMessage();
    }
}

$edit = null;
if (isset($_GET['edit']) && is_super_admin()) {
    $stmt = $pdo->prepare('SELECT user_id,full_name,username,role,status FROM users WHERE user_id=?');
    $stmt->execute([input_int($_GET, 'edit')]);
    $edit = $stmt->fetch() ?: null;
}
$users = $pdo->query('SELECT user_id,full_name,username,role,status,created_at FROM users ORDER BY full_name')->fetchAll();
$pageTitle = 'User Management';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4"><div><h1 class="h2 page-heading mb-1">User Management</h1><p class="text-secondary mb-0">Manage system accounts and roles.</p></div><?php if (is_super_admin()): ?><span class="badge text-bg-success">Super Admin controls</span><?php else: ?><span class="badge text-bg-light border">View only</span><?php endif; ?></div>
<?php if ($message = flash('success')): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if (is_super_admin()): ?><div class="panel mb-4"><h2 class="h5 fw-bold mb-3"><?=$edit ? 'Edit user' : 'Add user'?></h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="user_id" value="<?=e((string)($edit['user_id'] ?? ''))?>"><div class="row g-3"><div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="full_name" maxlength="150" required value="<?=e($edit['full_name'] ?? '')?>"></div><div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" maxlength="50" required value="<?=e($edit['username'] ?? '')?>"></div><div class="col-md-4"><label class="form-label">Role</label><select class="form-select" name="role"><option value="moderator" <?=normalized_role($edit['role'] ?? null) === 'moderator' || !$edit ? 'selected' : ''?>>Moderator</option><option value="super_admin" <?=normalized_role($edit['role'] ?? null) === 'super_admin' ? 'selected' : ''?>>Super Admin</option></select></div><div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?=($edit['status'] ?? 'active') === 'active' ? 'selected' : ''?>>Active</option><option value="inactive" <?=($edit['status'] ?? '') === 'inactive' ? 'selected' : ''?>>Inactive</option></select></div><div class="col-md-4"><label class="form-label">Password <?=$edit ? '(leave blank to keep current)' : '(10+ characters)'?></label><input class="form-control" type="password" name="password" <?=$edit ? '' : 'required minlength="10"'?> autocomplete="new-password"></div></div><div class="d-flex gap-2 mt-3"><button class="btn btn-success"><?=$edit ? 'Save changes' : 'Create user'?></button><?php if ($edit): ?><a class="btn btn-light" href="index.php">Cancel</a><?php endif; ?></div></form></div><?php endif; ?>
<div class="panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><?php if (is_super_admin()): ?><th>Actions</th><?php endif; ?></tr></thead><tbody><?php foreach ($users as $account): ?><tr><td><?=e($account['full_name'])?></td><td><?=e($account['username'])?></td><td><?=e(ucwords(str_replace('_', ' ', normalized_role($account['role']) ?? $account['role'])))?></td><td><?=e(ucfirst($account['status']))?></td><td><?=e($account['created_at'])?></td><?php if (is_super_admin()): ?><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-secondary" href="?edit=<?=(int)$account['user_id']?>">Edit</a><?php if ((int)$account['user_id'] !== (int)current_user()['user_id']): ?><form method="post" onsubmit="return confirm('Delete this user? Accounts linked to historical records will be deactivated instead.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?=(int)$account['user_id']?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?></div></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div><p class="small text-secondary mb-0">Moderators can view accounts. Super Admins can create, update, and remove accounts; linked attendance history is preserved.</p></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
