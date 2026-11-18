<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_role(['super_admin', 'moderator']);
$pdo = db(); $message = ''; $error = '';

if (isset($_GET['search'])) {
    header('Content-Type: application/json; charset=utf-8');
    $term = trim(input_string($_GET, 'search'));
    if ($term === '') { echo '[]'; exit; }
    $stmt = $pdo->prepare("SELECT person_id,school_id,first_name,last_name,person_type FROM persons WHERE status='active' AND (school_id LIKE ? OR first_name LIKE ? OR last_name LIKE ?) ORDER BY last_name,first_name LIMIT 25");
    $like = '%' . $term . '%'; $stmt->execute([$like, $like, $like]);
    echo json_encode($stmt->fetchAll(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $method = $_POST['method'] ?? 'manual';
    if (!in_array($method, ['manual', 'qr'], true)) $method = 'manual';
    $person = null;
    if ($method === 'qr') {
        $token = trim(input_string($_POST, 'qr_token'));
        if ($token !== '') {
            $stmt = $pdo->prepare("SELECT person_id,school_id,first_name,last_name FROM persons WHERE qr_token_hash=? AND status='active' LIMIT 1");
            $stmt->execute([hash('sha256', $token)]); $person = $stmt->fetch();
        }
    } else {
        $stmt = $pdo->prepare("SELECT person_id,school_id,first_name,last_name FROM persons WHERE person_id=? AND status='active'");
        $stmt->execute([input_int($_POST, 'person_id')]); $person = $stmt->fetch();
    }
    if (!$person) $error = 'No active registered person found. Check the selected person or QR code.';
    else {
        $duplicate = $pdo->prepare('SELECT COUNT(*) FROM attendance_records WHERE person_id=? AND attendance_datetime >= DATE_SUB(NOW(),INTERVAL 30 SECOND)');
        $duplicate->execute([$person['person_id']]);
        if ((int)$duplicate->fetchColumn() > 0) $error = 'This person was already recorded within the last 30 seconds.';
        else {
            $stmt = $pdo->prepare('INSERT INTO attendance_records(person_id,attendance_method,recorded_by) VALUES(?,?,?)');
            $stmt->execute([$person['person_id'], $method, current_user()['user_id']]);
            $message = 'Attendance recorded for ' . $person['first_name'] . ' ' . $person['last_name'] . ' (' . $person['school_id'] . ').';
        }
    }
}
$recent = $pdo->query('SELECT a.attendance_datetime,a.attendance_method,a.is_demo,p.school_id,CONCAT(p.first_name,\' \',p.last_name) full_name,p.person_type,d.department_name FROM attendance_records a JOIN persons p ON p.person_id=a.person_id JOIN departments d ON d.department_id=p.department_id ORDER BY a.attendance_datetime DESC LIMIT 20')->fetchAll();
$pageTitle = 'Attendance'; require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4"><h1 class="h2 page-heading mb-1">Attendance</h1><p class="text-secondary mb-0">Search by student or faculty name or ID, or scan a QR code.</p></div>
<?php if ($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<div class="row g-3 mb-4"><div class="col-lg-6"><div class="panel h-100"><h2 class="h5 fw-bold">Manual attendance</h2><p class="small text-secondary">Search by name or school ID. Results identify student and faculty visitors.</p><form method="post" id="manualForm"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="method" value="manual"><input type="hidden" name="person_id" id="person_id" required><label class="form-label" for="personSearch">Find a person</label><input class="form-control" id="personSearch" type="search" autocomplete="off" placeholder="Start typing a name or ID"><div id="personResults" class="list-group mt-2" aria-live="polite"></div><div id="personSelection" class="small text-success mt-2" aria-live="polite"></div><button class="btn btn-success mt-3" id="recordManual" disabled>Record attendance</button></form><noscript><p class="small text-danger mt-2">JavaScript is required for live attendance search.</p></noscript></div></div>
<div class="col-lg-6"><div class="panel h-100"><h2 class="h5 fw-bold">QR attendance</h2><p class="small text-secondary">Start the scanner when you are ready. Camera access requires permission.</p><div class="d-flex gap-2 mb-2"><button class="btn btn-success" id="openScanner" type="button">Open Scanner</button><button class="btn btn-outline-secondary" id="retryScanner" type="button" hidden>Retry</button><button class="btn btn-outline-secondary" id="stopScanner" type="button" hidden>Stop</button></div><div id="scannerStatus" class="small mb-2" role="status" aria-live="polite">Scanner is closed.</div><div id="qr-reader" style="max-width:420px"></div><form method="post" id="qrForm" class="mt-3"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="method" value="qr"><label class="form-label" for="qr_token">Scanned QR token</label><input class="form-control mb-2" id="qr_token" name="qr_token" placeholder="Scan or paste token" required><button class="btn btn-outline-success">Record QR attendance</button></form></div></div></div>
<div class="panel"><h2 class="h5 fw-bold mb-3">Recent attendance</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date &amp; time</th><th>Visitor</th><th>School ID</th><th>Type</th><th>Department</th><th>Method</th><th>Record</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><?=e($r['attendance_datetime'])?></td><td><?=e($r['full_name'])?></td><td><?=e($r['school_id'])?></td><td><?=e(ucfirst($r['person_type']))?></td><td><?=e($r['department_name'])?></td><td><span class="badge text-bg-light"><?=e(strtoupper($r['attendance_method']))?></span></td><td><?=$r['is_demo'] ? '<span class="badge text-bg-warning">DEMO</span>' : 'Live'?></td></tr><?php endforeach; ?><?php if(!$recent): ?><tr><td colspan="7" class="text-center text-secondary">No records yet.</td></tr><?php endif; ?></tbody></table></div></div>
<script src="<?=BASE_URL?>/assets/vendor/html5-qrcode/html5-qrcode.min.js"></script><script>
const searchInput=document.getElementById('personSearch'),results=document.getElementById('personResults'),selectedId=document.getElementById('person_id'),selectedLabel=document.getElementById('personSelection'),recordButton=document.getElementById('recordManual');let searchTimer;
searchInput.addEventListener('input',()=>{clearTimeout(searchTimer);selectedId.value='';selectedLabel.textContent='';recordButton.disabled=true;const term=searchInput.value.trim();results.replaceChildren();if(term.length<2)return;searchTimer=setTimeout(async()=>{try{const response=await fetch('?search='+encodeURIComponent(term),{headers:{'Accept':'application/json'}});if(!response.ok)throw new Error('Search failed. Try again.');const people=await response.json();if(!people.length){results.textContent='No matching active people found.';return;}people.forEach(person=>{const button=document.createElement('button');button.type='button';button.className='list-group-item list-group-item-action';button.textContent=`${person.last_name}, ${person.first_name} — ${person.school_id} (${person.person_type==='faculty'?'Faculty':'Student'})`;button.addEventListener('click',()=>{selectedId.value=person.person_id;selectedLabel.textContent='Selected: '+button.textContent;recordButton.disabled=false;results.replaceChildren();});results.append(button);});}catch(error){results.textContent=error.message;}},180);});
const statusEl=document.getElementById('scannerStatus'),openButton=document.getElementById('openScanner'),retryButton=document.getElementById('retryScanner'),stopButton=document.getElementById('stopScanner');let scanner=null,scanning=false;
function scannerMessage(error){const text=String(error||'');if(/NotAllowed|Permission|denied/i.test(text))return 'Camera permission was denied. Allow camera access in your browser, then retry.';if(/NotFound|DevicesNotFound|camera/i.test(text))return 'No camera was found. Connect or enable a camera, then retry.';if(/NotReadable|in use|Could not start/i.test(text))return 'The camera is unavailable or in use by another app. Close other camera apps, then retry.';return 'Could not start the scanner. Check camera access and browser permissions, then retry.';}
async function stopCamera(){if(scanner&&scanning){try{await scanner.stop();}catch(e){}scanning=false;}stopButton.hidden=true;}
async function startCamera(){if(!window.Html5Qrcode){statusEl.textContent='The local QR scanner library is missing. Restore the app assets and reload.';retryButton.hidden=false;return;}openButton.disabled=true;retryButton.hidden=true;statusEl.textContent='Requesting camera permission…';try{if(!scanner)scanner=new Html5Qrcode('qr-reader');const cameras=await Html5Qrcode.getCameras();if(!cameras.length)throw new Error('NotFoundError');await scanner.start({facingMode:'environment'},{fps:10,qrbox:{width:220,height:220}},async decoded=>{document.getElementById('qr_token').value=decoded;statusEl.textContent='QR code scanned. Confirm attendance below.';await stopCamera();},()=>{});scanning=true;statusEl.textContent='Camera active. Point it at a LAMAS QR code.';stopButton.hidden=false;}catch(error){statusEl.textContent=scannerMessage(error);retryButton.hidden=false;}finally{openButton.disabled=false;}}
openButton.addEventListener('click',startCamera);retryButton.addEventListener('click',startCamera);stopButton.addEventListener('click',async()=>{await stopCamera();statusEl.textContent='Scanner stopped.';retryButton.hidden=false;});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
