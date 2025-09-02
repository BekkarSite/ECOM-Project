<?php
// Simple admin auth/authorization helpers

function require_admin_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['admin_id'])) {
        header('Location: /admin/admin_login.php');
        exit();
    }
}

function current_user_role(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return $_SESSION['role'] ?? 'customer';
}

function can(string $permission): bool
{
    // Foundation for future role-permission checks.
    // For now, full access for admins, restricted for others.
    $role = current_user_role();
    if ($role === 'admin') return true;
    // Extend here by mapping $role + $permission lookups when permissions table exists.
    return false;
}

