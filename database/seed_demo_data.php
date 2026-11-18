<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$pdo = db();
$isRemove = ($argv[1] ?? '') === '--remove';
if ($isRemove) {
    $pdo->beginTransaction();
    try {
        $deletedRecords = (int)$pdo->exec('DELETE FROM attendance_records WHERE is_demo=1');
        $deletedPeople = (int)$pdo->exec('DELETE FROM persons WHERE is_demo=1 AND NOT EXISTS (SELECT 1 FROM attendance_records a WHERE a.person_id=persons.person_id)');
        $pdo->commit();
        echo "Removed {$deletedRecords} demo visits and {$deletedPeople} demo-only people.\n";
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
    exit;
}
$operator = $pdo->query("SELECT user_id FROM users WHERE status='active' ORDER BY user_id LIMIT 1")->fetchColumn();
if (!$operator) { fwrite(STDERR, "Create the first administrator before seeding demo attendance.\n"); exit(1); }

$people = [
    ['DEMO-LAMAS-STU-001','Maya','Santos','student'],
    ['DEMO-LAMAS-STU-002','Noah','Reyes','student'],
    ['DEMO-LAMAS-STU-003','Lia','Dela Cruz','student'],
    ['DEMO-LAMAS-STU-004','Ethan','Garcia','student'],
    ['DEMO-LAMAS-FAC-001','Ana','Villanueva','faculty'],
    ['DEMO-LAMAS-FAC-002','Marco','Bautista','faculty'],
];
$findDepartment = $pdo->prepare("SELECT department_id FROM departments WHERE status='active' AND category_type=? ORDER BY department_name LIMIT 1");
$fallbackDepartment = (int)$pdo->query("SELECT department_id FROM departments WHERE status='active' ORDER BY department_name LIMIT 1")->fetchColumn();
if (!$fallbackDepartment) { fwrite(STDERR, "Add an active department before seeding demo attendance.\n"); exit(1); }
$insertPerson = $pdo->prepare('INSERT INTO persons(school_id,first_name,last_name,person_type,department_id,status,is_demo) VALUES(?,?,?,?,?,\'active\',1) ON DUPLICATE KEY UPDATE school_id=VALUES(school_id)');
$personIds = [];
foreach ($people as [$schoolId,$first,$last,$type]) {
    $findDepartment->execute([$type === 'faculty' ? 'faculty' : 'program']);
    $departmentId = (int)($findDepartment->fetchColumn() ?: $fallbackDepartment);
    $insertPerson->execute([$schoolId,$first,$last,$type,$departmentId]);
    $lookup = $pdo->prepare('SELECT person_id,is_demo FROM persons WHERE school_id=?');
    $lookup->execute([$schoolId]); $person = $lookup->fetch();
    if (!$person || !(int)$person['is_demo']) {
        fwrite(STDERR, "A sample ID conflicts with a non-demo person: {$schoolId}. No attendance was added.\n"); exit(1);
    }
    $personIds[] = (int)$person['person_id'];
}

$exists = $pdo->prepare('SELECT COUNT(*) FROM attendance_records WHERE person_id=? AND attendance_datetime=? AND is_demo=1');
$insertVisit = $pdo->prepare('INSERT INTO attendance_records(person_id,attendance_datetime,attendance_method,recorded_by,is_demo) VALUES(?,?,\'manual\',?,1)');
$created = 0;
$today = new DateTimeImmutable('today');
$pdo->beginTransaction();
try {
    for ($monthsAgo=11; $monthsAgo>=0; $monthsAgo--) {
        $monthStart = $today->modify('first day of this month')->modify('-' . $monthsAgo . ' months');
        foreach ($personIds as $personIndex=>$personId) {
            foreach ([3,10,17,24] as $visitIndex=>$day) {
                $visitDate = $monthStart->setDate((int)$monthStart->format('Y'),(int)$monthStart->format('n'),$day);
                if ($visitDate > $today) continue;
                $timestamp = $visitDate->setTime(8 + (($personIndex + $visitIndex) % 4), 5 + (($personIndex * 7 + $visitIndex * 11) % 50), 0)->format('Y-m-d H:i:s');
                $exists->execute([$personId,$timestamp]);
                if ((int)$exists->fetchColumn() === 0) { $insertVisit->execute([$personId,$timestamp,$operator]); $created++; }
            }
        }
    }
    $pdo->commit();
} catch (Throwable $error) { $pdo->rollBack(); throw $error; }
echo "Demo data ready: {$created} new historical visits across 6 clearly identified demo people. Run with --remove to remove demo visits and demo-only people.\n";
