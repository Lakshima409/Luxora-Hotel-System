<?php
// scripts/generate_occupancy_report.php - midnight daily snapshot (excludes Cancelled)
require_once __DIR__.'/cron_common.php';
$date=$NOW->format('Y-m-d');
$total=(int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occ=(int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status='occupied' OR room_condition='Occupied'")->fetchColumn();
$pct=$total?round($occ/$total*100,2):0;
$rev=$pdo->query("SELECT COALESCE(SUM(b.total_amount),0) FROM bills b JOIN reservations r ON r.reservation_id=b.reservation_id WHERE DATE(b.created_at)='$date' AND r.booking_status NOT IN ('Cancelled')")->fetchColumn();
$ns=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE DATE(check_in)='$date' AND booking_status='No-Show'")->fetchColumn();
$s=$pdo->prepare("INSERT INTO reports (report_date,occupancy_pct,revenue,total_rooms,occupied_rooms,no_shows,payload) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE occupancy_pct=VALUES(occupancy_pct),revenue=VALUES(revenue),total_rooms=VALUES(total_rooms),occupied_rooms=VALUES(occupied_rooms),no_shows=VALUES(no_shows),payload=VALUES(payload)");
$s->execute([$date,$pct,$rev,$total,$occ,$ns,json_encode(['date'=>$date])]);
cron_log_db($pdo,'generate_occupancy_report','success',1,"occ=$pct% rev=$rev");
echo "Report $date: $occ/$total ($pct%), rev $rev, no-shows $ns\n";
