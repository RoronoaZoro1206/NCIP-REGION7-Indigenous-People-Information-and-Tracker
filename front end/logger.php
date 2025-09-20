<?php

if (!function_exists('get_client_ip')) {
    /**
     * Resolve client IP from common headers with sensible fallbacks.
     */
    function get_client_ip(): string {
        try {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                return (string)$_SERVER['HTTP_CLIENT_IP'];
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
                return trim($parts[0]) !== '' ? trim($parts[0]) : 'UNKNOWN';
            }
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                return (string)$_SERVER['REMOTE_ADDR'];
            }
            // CLI or unknown
            return PHP_SAPI === 'cli' ? 'SYSTEM' : 'UNKNOWN';
        } catch (Throwable $e) {
            return 'UNKNOWN';
        }
    }
}

if (!function_exists('write_auth_log')) {
    function write_auth_log(string $event, array $context = []): void {
        try {
            $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'user-auth.log';

            // Timestamp note: keep the same label style used elsewhere in the project logs
            $timestamp = date('Y-m-d H:i:s') . ' UTC+08:00 (Philippine Time)';
            $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

            $userId = $context['user_id'] ?? null;
            $username = $context['username'] ?? null;
            $email = $context['email'] ?? null;
            $details = $context['details'] ?? '';

            $parts = [];
            $parts[] = "[$timestamp]";
            $parts[] = strtoupper($event) . ':';
            if ($userId !== null) { $parts[] = "user_id={$userId}"; }
            if ($username !== null) { $parts[] = "username={$username}"; }
            if ($email !== null) { $parts[] = "email={$email}"; }
            $parts[] = "ip={$ip}";
            if (!empty($details)) { $parts[] = "| {$details}"; }

            $line = implode(' ', $parts) . PHP_EOL;
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Intentionally ignore logging errors to avoid affecting main flow
        }
    }
}

if (!function_exists('log_user_event')) {
    /**
     * @param PDO|null $pdo      Optional PDO connection to write into USER_LOGS; skipped if null.
     * @param string   $type     e.g., LOGIN, REGISTRATION, PASSWORD_RESET_REQUEST, TOKEN_CLEANUP, etc.
     * @param string   $details  Human-readable description, same style as DB.
     * @param int|null $userId   Optional user id to associate with the log.
     * @param string|null $ip    Optional IP; if null, derived from server vars.
     */
    function log_user_event($pdo, string $type, string $details, ?int $userId = null, ?string $ip = null): void {
        try {
            if ($pdo instanceof PDO) {
                try {
                    // Resolve IP if not provided or empty
                    $ipToUse = $ip;
                    if ($ipToUse === null || $ipToUse === '') {
                        $ipToUse = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
                    }
                    if ($userId !== null) {
                        $stmt = $pdo->prepare("INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) VALUES (:uid, :type, :details, CURRENT_TIMESTAMP, :ip)");
                        $stmt->execute([
                            ':uid' => $userId,
                            ':type' => $type,
                            ':details' => $details,
                            ':ip' => $ipToUse,
                        ]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO USER_LOGS (LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) VALUES (:type, :details, CURRENT_TIMESTAMP, :ip)");
                        $stmt->execute([
                            ':type' => $type,
                            ':details' => $details,
                            ':ip' => $ipToUse,
                        ]);
                    }
                } catch (Throwable $dbLogErr) {
                    // swallow DB logging errors
                }
            }
        } catch (Throwable $outer) {
            // final guard, do nothing
        }
    }
}

?>
