<?php
/** @test Auth & access control. These functions header()+exit(), so each scenario
 *  runs in a child PHP process and we inspect its output/exit code. */

$AUTH = '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["HTTP_HOST"]="localhost"; $_SERVER["SCRIPT_NAME"]="/hotel-system/login.php";'
      . ' require_once "' . str_replace('\\', '/', __DIR__ . '/../includes/auth.php') . '";';
// expose to the class below via a constant (class methods can't see file-scope vars)
define('T_AUTH', $AUTH);

class AuthTest extends TestCase
{
    private function sessionPreamble(string $role = null): string
    {
        if ($role === null) return ''; // no session = logged out
        return "\$_SESSION['user_id']=1; \$_SESSION['username']='{$role}1'; \$_SESSION['role']='{$role}'; \$_SESSION['logged_in']=true;";
    }

    public function test_require_login_blocks_anonymous()
    {
        $body = T_AUTH . $this->sessionPreamble(null) . ' require_login(); echo "REACHED";';
        $r = t_run_php($body);
        t_false(strpos($r['output'], 'REACHED') !== false, 'anonymous user must NOT pass require_login()');
    }

    public function test_require_login_allows_authenticated()
    {
        $body = T_AUTH . $this->sessionPreamble('clerk') . ' require_login(); echo "REACHED";';
        $r = t_run_php($body);
        t_true(strpos($r['output'], 'REACHED') !== false, 'authenticated clerk passes require_login()');
    }

    public function test_require_role_denies_wrong_role()
    {
        $body = T_AUTH . $this->sessionPreamble('clerk') . ' require_role("admin"); echo "REACHED";';
        $r = t_run_php($body);
        t_false(strpos($r['output'], 'REACHED') !== false, 'clerk must NOT pass require_role(admin)');
        t_true(strpos($r['output'], 'Access denied') !== false, '403 message shown for wrong role');
    }

    public function test_require_role_allows_matching_role()
    {
        $body = T_AUTH . $this->sessionPreamble('admin') . ' require_role("admin"); echo "REACHED";';
        $r = t_run_php($body);
        t_true(strpos($r['output'], 'REACHED') !== false, 'admin passes require_role(admin)');
    }

    public function test_require_role_blocks_anonymous()
    {
        $body = T_AUTH . $this->sessionPreamble(null) . ' require_role("admin"); echo "REACHED";';
        $r = t_run_php($body);
        t_false(strpos($r['output'], 'REACHED') !== false, 'anonymous blocked even before role check');
    }

    public function test_has_any_role_membership()
    {
        $body = T_AUTH . $this->sessionPreamble('clerk') . ' echo has_any_role(["clerk","manager"]) ? "YES":"NO";';
        $r = t_run_php($body);
        t_true(strpos($r['output'], 'YES') !== false, 'clerk is a member of [clerk,manager]');
    }

    public function test_has_any_role_non_membership()
    {
        $body = T_AUTH . $this->sessionPreamble('clerk') . ' echo has_any_role(["admin","manager"]) ? "YES":"NO";';
        $r = t_run_php($body);
        t_true(strpos($r['output'], 'NO') !== false, 'clerk is NOT a member of [admin,manager]');
    }

    public function test_auth_login_url_is_absolute()
    {
        $body = 'define("BASE_URL","http://localhost/hotel-system");'
              . ' $_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["HTTP_HOST"]="localhost";'
              . ' require_once "' . str_replace('\\', '/', __DIR__ . '/../includes/auth.php') . '";'
              . ' echo auth_login_url();';
        $r = t_run_php($body);
        t_true(strpos($r['output'], 'http://localhost/hotel-system/login.php') !== false,
            'login URL is absolute (fixes subfolder redirect 404)');
    }

    public function test_role_helper_shortcuts_exist()
    {
        $body = T_AUTH . ' echo function_exists("require_clerk") && function_exists("require_admin") && function_exists("require_manager") ? "ALL":"MISSING";';
        $r = t_run_php($body);
        t_true(strpos($r['output'], 'ALL') !== false, 'clerk/admin/manager role shortcuts defined');
    }
}
