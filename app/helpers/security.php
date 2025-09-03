<?php
// Common security helpers: password strength and simple CAPTCHA
// Ensure session exists for CAPTCHA state
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Validate a strong password according to policy.
 * Requirements:
 * - Minimum length 10
 * - At least one uppercase, one lowercase, one digit, one special character
 * - Disallow obvious common patterns
 *
 * @param string $password
 * @param string|null $error Populated with a human-readable reason on failure
 * @return bool True if strong, false otherwise
 */
function is_strong_password(string $password, ?string &$error = null): bool
{
    $minLen = 10;
    if (strlen($password) < $minLen) {
        $error = "Password must be at least {$minLen} characters.";
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must include at least one uppercase letter.';
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must include at least one lowercase letter.';
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must include at least one digit.';
        return false;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must include at least one special character.';
        return false;
    }
    // Reject common/obvious sequences
    $lower = strtolower($password);
    $common = ['password', '123456', 'qwerty', 'letmein', 'welcome', 'admin'];
    foreach ($common as $c) {
        if (strpos($lower, $c) !== false) {
            $error = 'Password is too common or predictable.';
            return false;
        }
    }
    $error = null;
    return true;
}

/**
 * Generate a simple math CAPTCHA question and store its answer in the session.
 * @return string The question to display to the user.
 */
function captcha_generate(): string
{
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $ops = ['+', '-'];
    $op = $ops[array_rand($ops)];
    $answer = $op === '+' ? $a + $b : $a - $b;
    $_SESSION['captcha_answer'] = (string)$answer;
    return "What is {$a} {$op} {$b}?";
}

/**
 * Validate the submitted CAPTCHA answer against the session and clear it.
 * @param mixed $input
 * @return bool
 */
function captcha_validate($input): bool
{
    $expected = $_SESSION['captcha_answer'] ?? null;
    // One-time use: clear to prevent replay
    unset($_SESSION['captcha_answer']);
    return $expected !== null && trim((string)$input) === (string)$expected;
}
