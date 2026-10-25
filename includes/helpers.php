<?php
// includes/helpers.php - shared functions
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect($url){ header("Location: $url"); exit(); }
function log_action_pdo($pdo,$user_id,$role,$type,$msg){
  try{
    $st=$pdo->prepare("INSERT INTO action_logs (user_id,role,action_type,action,message,description) VALUES (?,?,?,?,?,?)");
    $st->execute([$user_id,$role,$type,$type,$msg,$msg]);
  }catch(Exception $ex){ error_log('log_action failed: '.$ex->getMessage()); }
}
function get_setting($pdo,$key,$def=''){
  try{ $s=$pdo->prepare("SELECT svalue FROM settings WHERE skey=?"); $s->execute([$key]);
    $r=$s->fetchColumn(); return $r!==false?$r:$def;
  }catch(Exception $ex){ return $def; }
}
function nights_between($in,$out){
  $a=new DateTime($in); $b=new DateTime($out);
  // Count by calendar date (standard hotel billing): nights = check-out date - check-in date.
  // Time-of-day is ignored so "Oct 1 14:00 -> Oct 4 12:00" correctly bills 3 nights
  // (DateTime::diff()->days on the raw timestamps would truncate "2d 22h" to 2 and undercharge).
  $a->setTime(0,0,0); $b->setTime(0,0,0);
  $n=(int)$a->diff($b)->days; return max(1,$n);
}

// Minimal CSRF helpers for the password-reset flow.
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
