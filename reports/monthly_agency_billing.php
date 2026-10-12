<?php
require_once '../includes/auth.php';
require_role('admin');
require_once '../db.php';

$sql = "
SELECT guest_name, SUM(total_amount) AS total, MONTH(created_at) AS month
FROM reservations r
JOIN bills b ON r.id = b.reservation_id
WHERE guest_name LIKE '%Travel Agency%'
GROUP BY guest_name, MONTH(created_at)
";

$results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Monthly Agency Billing</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
    <div class="container">
        <h3>📆 Monthly Billing Summary for Travel Agencies</h3>
        <table class="table table-striped">
            <thead><tr><th>Agency</th><th>Month</th><th>Total ($)</th></tr></thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['guest_name']) ?></td>
                        <td><?= $row['month'] ?></td>
                        <td>$<?= number_format($row['total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
