<?php
// scripts/archive_logs.php - weekly archive (keeps 90d hot)
require_once __DIR__.'/cron_common.php';
$dow=(int)$NOW->format('N'); // 7=Sunday
if($dow!==7 && !$mock){ echo "Runs Sundays.\n"; exit(0); }
$dir=__DIR__.'/../logs/archive'; if(!is_dir($dir)) mkdir($dir,0777,true);
$f=$dir.'/action_logs_'.$NOW->format('Y-m-d').'.csv';
$rows=$pdo->query("SELECT * FROM action_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)")->fetchAll();
$fh=fopen($f,'w'); foreach($rows as $r) fputcsv($fh,$r); fclose($fh);
$n=count($rows);
if($n) $pdo->exec("DELETE FROM action_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)");
cron_log_db($pdo,'archive_logs','success',$n,"file=$f");
echo "Archived $n rows to $f\n";
