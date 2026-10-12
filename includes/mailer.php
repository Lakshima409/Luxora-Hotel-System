<?php
// includes/mailer.php - SMTP via mail() + file fallback (offline LAN safe)
require_once __DIR__.'/../config.php';
function send_mail($to,$subject,$body,$from=null){
  $from = $from ?: (defined('ADMIN_EMAIL')?ADMIN_EMAIL:'admin@luxora.local');
  $headers = "From: $from\r\nContent-Type: text/plain; charset=UTF-8\r\n";
  $logDir = __DIR__.'/../logs'; if(!is_dir($logDir)) @mkdir($logDir,0777,true);
  $line = date('Y-m-d H:i:s')." | TO:$to | SUBJ:$subject | ".str_replace("\n",' ',$body)."\n";
  @file_put_contents($logDir.'/mail.log',$line,FILE_APPEND);
  // try real mail(), ignore failure offline
  try{ @mail($to,$subject,$body,$headers); }catch(Exception $ex){}
  return true;
}
function notify_booking($to,$name,$ref,$ci,$co,$status='Confirmed'){
  $body="Dear $name,\nYour Luxora reservation $ref ($ci to $co) is: $status.\nThank you.";
  return send_mail($to,"Luxora Reservation $ref - $status",$body);
}
