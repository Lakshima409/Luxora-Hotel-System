<?php
// index.php - role-based landing
require_once __DIR__.'/config.php';
if(empty($_SESSION['logged_in'])){
  header("Location: login.php"); exit();
}
$r=$_SESSION['role']??'clerk';
$map=['admin'=>'admin/admin_dashboard.php','manager'=>'admin/manager_dashboard.php','clerk'=>'clerk_dashboard.php','guest'=>'public/my_bookings.php'];
header("Location: ".($map[$r]??'clerk_dashboard.php')); exit();
