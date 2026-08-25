<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function is_logged_in(): bool
{
  return isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
}

function require_login(): void
{
  if (!is_logged_in()) {
    header('Location: ?route=login');
    exit;
  }
}

function session_timeout_guard(): void
{
  if (!is_logged_in()) {
    return;
  }
  $now = time();
  $last = $_SESSION['last_activity'] ?? $now;
  if (($now - (int)$last) > SESSION_TIMEOUT_SECONDS) {
    session_unset();
    session_destroy();
    header('Location: ?route=login');
    exit;
  }
  $_SESSION['last_activity'] = $now;
}

function login_user(string $username, string $password): bool
{
  session_timeout_guard();

  $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
  $stmt->execute([':username' => $username]);
  $user = $stmt->fetch();
  if (!$user) {
    return false;
  }
  if (!password_verify($password, (string)$user['password_hash'])) {
    return false;
  }

  $_SESSION['user'] = [
    'id' => (int)$user['id'],
    'full_name' => (string)$user['full_name'],
    'username' => (string)$user['username'],
    'role' => (string)$user['role'],
  ];
  $_SESSION['last_activity'] = time();

  return true;
}

function logout_user(): void
{
  session_unset();
  session_destroy();
}

function current_user(): ?array
{
  return $_SESSION['user'] ?? null;
}

