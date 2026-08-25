<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Permission model (simple and explicit):
// - captain: all actions
// - secretary: create/update/delete blotter + create resolutions
// - councilor: view, can update resolution only (limited)
// - lupon: view/update case resolutions only (limited)

function require_role(array $roles): void
{
  $u = current_user();
  if (!$u) {
    header('Location: ?route=login');
    exit;
  }
  if (!in_array($u['role'], $roles, true)) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
  }
}

function can_delete_blotter(): bool
{
  $u = current_user();
  return $u && in_array($u['role'], ['captain', 'secretary'], true);
}

function can_edit_blotter(): bool
{
  $u = current_user();
  return $u && in_array($u['role'], ['captain', 'secretary'], true);
}

function can_view_blotter(): bool
{
  return is_logged_in();
}

function can_manage_users(): bool
{
  $u = current_user();
  return $u && in_array($u['role'], ['captain', 'secretary'], true);
}

function can_update_resolution(): bool
{
  $u = current_user();
  return $u && in_array($u['role'], ['captain', 'secretary', 'councilor', 'lupon'], true);
}

