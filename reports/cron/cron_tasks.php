<?php
/**
 * Cron Tasks Script
 * Executes scheduled tasks at regular intervals
 */

// Prevent direct access
defined('BASEPATH') || exit('No direct script access allowed');

// Load required libraries
require_once 'config.php';
require_once 'database.php';

// Log cron start
file_put_contents('cron_log.txt', date('Y-m-d H:i:s') . " - Cron started\n", FILE_APPEND);

// Execute scheduled tasks
try {
    // Task 1: Generate daily reports
    require_once 'generate_report.php';
    
    // Task 2: Process monthly billing
    if (date('j') == 1) { // Run on first day of month
        require_once 'monthly_agency_billing.php';
    }
    
    // Task 3: Clean up temporary files
    // ... cleanup code here ...
    
    file_put_contents('cron_log.txt', date('Y-m-d H:i:s') . " - Cron completed successfully\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents('cron_log.txt', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>