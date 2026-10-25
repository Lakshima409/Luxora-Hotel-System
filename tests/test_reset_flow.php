<?php
/** @test Password reset flow (Request_Reset.php, newpass.php). Runs in child processes. */

class ResetFlowTest extends TestCase
{
    private function runEndpoint(string $file, array $session, array $post, array $env = []): array
    {
        $sid = bin2hex(random_bytes(8));
        $sessCode = "session_id('" . $sid . "');";
        $sessSet = '';
        foreach ($session as $k => $v) {
            $sessSet .= "\$_SESSION[" . var_export($k, true) . "] = " . var_export($v, true) . "; ";
        }
        $postSet = '';
        foreach ($post as $k => $v) {
            $postSet .= "\$_POST[" . var_export($k, true) . "] = " . var_export($v, true) . "; ";
        }
        $envSet = '';
        foreach ($env as $k => $v) {
            $envSet .= "putenv(" . var_export($k . '=' . $v, true) . "); ";
        }
        $dumpFile = 'C:/temp/reset_state_' . $sid . '.txt';
        $body = "\$_SERVER['REQUEST_METHOD']='POST'; \$_SERVER['HTTP_HOST']='localhost'; \$_SERVER['SCRIPT_NAME']='/hotel-system/" . basename($file) . "'; "
              . $envSet . $sessCode . " session_start(); " . $sessSet . $postSet
              . " register_shutdown_function(function(){ file_put_contents('" . $dumpFile . "', serialize(\$_SESSION)); }); "
              . " require '" . str_replace('\\', '/', APP_ROOT) . "/" . basename($file) . "';";
        $r = t_run_php($body);
        $state = [];
        if (is_file($dumpFile)) {
            $raw = file_get_contents($dumpFile);
            $state = @unserialize($raw);
            if (!is_array($state)) $state = [];
            @unlink($dumpFile);
        }
        $r['session'] = $state;
        return $r;
    }

    private function createUser(string $suffix): array
    {
        $pdo = t_db();
        if (!$pdo) {
            throw new SkipTest('Database unavailable');
        }
        $username = 'reset_test_' . $suffix;
        $email = $username . '@example.test';
        $hash = password_hash('OldPass123!', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash, password, role, email, active) VALUES (?,?,?,?,?,1)")
            ->execute([$username, $hash, 'legacy-old', 'guest', $email]);
        return ['id' => (int)$pdo->lastInsertId(), 'email' => $email, 'username' => $username];
    }

    private function deleteUser(int $id): void
    {
        $pdo = t_db();
        if ($pdo) {
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$id]);
        }
    }

    public function test_unknown_email_does_not_create_reset_state()
    {
        $r = $this->runEndpoint('Request_Reset.php', [
            'csrf_token' => 'test-token',
        ], [
            'csrf_token' => 'test-token',
            'email' => 'definitely-unknown-' . bin2hex(random_bytes(4)) . '@example.test',
        ]);
        $this->assertTrue(!isset($r['session']['reset_user_id']), 'no reset user for unknown email');
    }
    public function test_smtp_failure_clears_state()
    {
        $user = $this->createUser(bin2hex(random_bytes(4)));
        try {
            $r = $this->runEndpoint('Request_Reset.php', [
                'csrf_token' => 'test-token',
            ], [
                'csrf_token' => 'test-token',
                'email' => $user['email'],
            ], [
                'SMTP_USER' => 'invalid@example.com',
                'SMTP_PASS' => 'invalid-password',
            ]);
            $this->assertStringContains('if an account exists', strtolower((string)($r['session']['error'] ?? '')), 'generic response for missing mail service');
            $this->assertTrue(!isset($r['session']['otp']), 'OTP cleared when SMTP fails');
            $this->assertTrue(!isset($r['session']['reset_user_id']), 'reset authorization cleared when SMTP fails');
        } finally {
            $this->deleteUser($user['id']);
        }
    }

    public function test_password_change_without_verification_rejected()
    {
        $r = $this->runEndpoint('newpass.php', [
            'csrf_token' => 'test-token',
        ], [
            'csrf_token' => 'test-token',
            'password' => 'NewPass123!',
            'confirm_password' => 'NewPass123!',
        ]);
        $this->assertStringContains('expired', strtolower((string)($r['session']['error'] ?? '')), 'authorization required');
        $this->assertTrue(!isset($r['session']['otp_verified']), 'not verified');
    }

    public function test_expired_authorization_rejected()
    {
        $user = $this->createUser(bin2hex(random_bytes(4)));
        try {
            $r = $this->runEndpoint('newpass.php', [
                'csrf_token' => 'test-token',
                'reset_user_id' => $user['id'],
                'reset_flow' => 'password_reset',
                'otp_verified' => true,
                'otp_verified_email' => $user['email'],
                'otp_verified_at' => time() - 700,
            ], [
                'csrf_token' => 'test-token',
                'password' => 'NewPass123!',
                'confirm_password' => 'NewPass123!',
            ]);
            $this->assertStringContains('expired', strtolower((string)($r['session']['error'] ?? '')), 'expired authorization');
            $this->assertTrue(!isset($r['session']['otp_verified']), 'verified state cleared');
        } finally {
            $this->deleteUser($user['id']);
        }
    }

    public function test_mismatched_authorization_rejected()
    {
        $user = $this->createUser(bin2hex(random_bytes(4)));
        try {
            $r = $this->runEndpoint('newpass.php', [
                'csrf_token' => 'test-token',
                'reset_user_id' => $user['id'],
                'reset_flow' => 'password_reset',
                'otp_verified' => true,
                'otp_verified_email' => 'someone-else@example.test',
                'otp_verified_at' => time(),
            ], [
                'csrf_token' => 'test-token',
                'password' => 'NewPass123!',
                'confirm_password' => 'NewPass123!',
            ]);
            $this->assertStringContains('expired', strtolower((string)($r['session']['error'] ?? '')), 'mismatched authorization');
        } finally {
            $this->deleteUser($user['id']);
        }
    }

    public function test_successful_password_update()
    {
        $user = $this->createUser(bin2hex(random_bytes(4)));
        try {
            $r = $this->runEndpoint('newpass.php', [
                'csrf_token' => 'test-token',
                'reset_user_id' => $user['id'],
                'reset_flow' => 'password_reset',
                'otp_verified' => true,
                'otp_verified_email' => $user['email'],
                'otp_verified_at' => time(),
            ], [
                'csrf_token' => 'test-token',
                'password' => 'NewPass123!',
                'confirm_password' => 'NewPass123!',
            ]);
            $pdo = t_db();
            $stmt = $pdo->prepare("SELECT password_hash, password FROM users WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            $this->assertTrue(password_verify('NewPass123!', (string)$row['password_hash']), 'new hash verifies');
            $this->assertTrue($row['password'] === null, 'legacy password cleared');
            $this->assertTrue(!isset($r['session']['otp_verified']), 'verified state cleared');
            $this->assertTrue(!isset($r['session']['reset_user_id']), 'reset user cleared');
        } finally {
            $this->deleteUser($user['id']);
        }
    }

    public function test_csrf_token_invalidated_after_reset()
    {
        $user = $this->createUser(bin2hex(random_bytes(4)));
        try {
            $first = $this->runEndpoint('newpass.php', [
                'csrf_token' => 'test-token',
                'reset_user_id' => $user['id'],
                'reset_flow' => 'password_reset',
                'otp_verified' => true,
                'otp_verified_email' => $user['email'],
                'otp_verified_at' => time(),
            ], [
                'csrf_token' => 'test-token',
                'password' => 'NewPass123!',
                'confirm_password' => 'NewPass123!',
            ]);
            $this->assertTrue(!isset($first['session']['csrf_token']), 'CSRF token cleared after reset');

            $second = $this->runEndpoint('newpass.php', $first['session'], [
                'csrf_token' => 'test-token',
                'password' => 'AnotherPass123!',
                'confirm_password' => 'AnotherPass123!',
            ]);
            $this->assertStringContains('invalid request', strtolower((string)($second['session']['error'] ?? '')), 'replayed CSRF rejected');
        } finally {
            $this->deleteUser($user['id']);
        }
    }
}
