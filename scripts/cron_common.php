<?php
// scripts/cron_common.php - shared bootstrap for CLI + web + Task Scheduler
$config = __DIR__.'/../config.php'; if(file_exists($config)) require_once $config;
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/mailer.php';
if(!isset($pdo) || !$pdo){ fwrite(STDERR,"DB unavailable\n"); exit(1); }
// mock time for testing: ?mock_time=2026-01-01+19:00 or CLI --mock=...
$mock = $_GET['mock_time'] ?? null;
foreach($argv ?? [] as $a){ if(str_starts_with($a,'--mock=')) $mock=substr($a,7); }
$NOW = $mock ? new DateTime($mock) : new DateTime('now');
function cron_log_db($pdo,$script,$status,$n,$msg=''){
  try{ $s=$pdo->prepare("INSERT INTO cron_log (script,status,records_affected,message) VALUES (?,?,?,?)"); $s->execute([$script,$status,$n,$msg]); }
  catch(Exception $e){ @file_put_contents(__DIR__.'/../logs/cron.log',date('Y-m-d H:i:s')." $script $status $n $msg\n",FILE_APPEND); }
}
