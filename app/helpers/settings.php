<?php
// Lightweight settings helper
// Requires an active $conn (mysqli) when functions are used.

function get_setting(mysqli $conn, string $name, $default = null)
{
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $stmt->bind_result($value);
        if ($stmt->fetch()) {
            $stmt->close();
            return $value;
        }
        $stmt->close();
    }
    return $default;
}

function set_setting(mysqli $conn, string $name, string $value): bool
{
    // Upsert behavior
    $stmt = $conn->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    if ($stmt) {
        $stmt->bind_param('ss', $name, $value);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}

function get_settings(mysqli $conn, array $names): array
{
    if (empty($names)) return [];
    // Build placeholders
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $types = str_repeat('s', count($names));
    $stmt = $conn->prepare("SELECT name, value FROM settings WHERE name IN ($placeholders)");
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$names);
    $stmt->execute();
    $result = $stmt->get_result();
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[$row['name']] = $row['value'];
    }
    $stmt->close();
    return $out;
}

