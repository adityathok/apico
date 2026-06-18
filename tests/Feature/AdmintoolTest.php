<?php

use Database\Seeders\UserSeeder;
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

test('admintool shows user seeder action', function () {
    $script = <<<'PHP'
session_id('admintool-seed-user-test');
session_start();
$_SESSION[hash('sha256', getcwd().'|admintool')] = true;
$_SESSION['admintool_csrf'] = 'test-csrf-token';
session_write_close();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admintool.php';

ob_start();
require 'public/admintool.php';
echo ob_get_clean();
PHP;

    $process = new Process([PHP_BINARY, '-r', $script], base_path());
    $process->mustRun();

    expect($process->getOutput())
        ->toContain('Seed User')
        ->toContain('UserSeeder');
});

test('admintool shows public html storage symlink action', function () {
    $script = <<<'PHP'
session_id('admintool-public-html-storage-link-test');
session_start();
$_SESSION[hash('sha256', getcwd().'|admintool')] = true;
$_SESSION['admintool_csrf'] = 'test-csrf-token';
session_write_close();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admintool.php';

ob_start();
require 'public/admintool.php';
echo ob_get_clean();
PHP;

    $process = new Process([PHP_BINARY, '-r', $script], base_path());
    $process->mustRun();

    expect($process->getOutput())
        ->toContain('Public HTML Storage Link')
        ->toContain('public_html/storage')
        ->toContain('laravel/storage/app/public');
});

test('user seeder creates default users', function () {
    $this->seed(UserSeeder::class);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@example.com',
        'name' => 'Admin',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'usertest@example.com',
        'name' => 'User Test',
    ]);
});
