<?php

use Symfony\Component\Process\Process;

test('admintool displays php info output for authenticated users', function () {
    $script = <<<'PHP'
session_id('admintool-test');
session_start();
$_SESSION[hash('sha256', getcwd().'|admintool')] = true;
$_SESSION['admintool_csrf'] = 'test-csrf-token';
session_write_close();

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admintool.php';
$_POST['command'] = 'php_info';
$_POST['csrf_token'] = 'test-csrf-token';

ob_start();
require 'public/admintool.php';
echo ob_get_clean();
PHP;

    $process = new Process([PHP_BINARY, '-r', $script], base_path());
    $process->mustRun();

    expect($process->getOutput())
        ->toContain('PHP Info')
        ->toContain('PHP Version');
});
