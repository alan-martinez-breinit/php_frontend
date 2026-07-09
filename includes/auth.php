<?php

/**
 * Seguridad de autenticación y sesión.
 *
 * - Sesión con cookie HttpOnly, Secure y SameSite Strict.
 * - CSRF tokens con hash_equals.
 * - Rate limiting por IP/correo.
 * - Regeneración de ID de sesión en cambios de privilegio.
 * - Headers de seguridad OWASP recomendados.
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = isRequestHttps();

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.gc_maxlifetime', '3600'); // 1 hora de inactividad

    session_set_cookie_params([
        'lifetime'   => 0,
        'path'       => '/',
        'domain'     => '',
        'secure'     => $isHttps,
        'httponly'   => true,
        'samesite'   => 'Strict',
    ]);

    session_start();
}

function isRequestHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['auth_token']) && !empty($_SESSION['user']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $base = dirname($_SERVER['SCRIPT_NAME']);
        $base = str_replace(['\pages', '\auth'], '', $base);
        header('Location: ' . $base . '/auth/login.php');
        exit;
    }
}

function currentUser(): array
{
    return $_SESSION['user'] ?? ['name' => 'Usuario', 'email' => ''];
}

function ensureModulosCargados(): void
{
    if (!empty($_SESSION['modulos'])) {
        return;
    }
    $codigoCliente = $_SESSION['user']['codigo_cliente'] ?? null;
    if (!$codigoCliente) {
        $_SESSION['modulos'] = [];
        return;
    }
    require_once __DIR__ . '/modulos.php';
    $_SESSION['modulos'] = modulosPorCliente($codigoCliente);
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/* -------------------- CSRF -------------------- */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function csrfValidate(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfRefresh(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* -------------------- Rate limiting (persistente, por IP+email) -------------------- */
/* Almacenamiento en archivo (directorio data/, no web-accesible). Así el límite
   sobrevive a sesiones nuevas y es efectivo contra brute-force anónimo, a diferencia
   de la versión previa que guardaba el contador en $_SESSION (se reiniciaba por request). */

function clientIdentifier(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Si hay un proxy de confianza, podría usarse X-Forwarded-For, pero por seguridad usamos REMOTE_ADDR
    return $ip;
}

function loginRateLimitKey(?string $email = null): string
{
    $id = clientIdentifier();
    $email = $email !== null ? strtolower(trim($email)) : strtolower(trim($_POST['email'] ?? ''));
    return 'login_attempts_' . md5($id . '|' . $email);
}

function rateLimitDataDir(): string
{
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

function rateLimitLock(): mixed
{
    $lockPath = rateLimitDataDir() . '/rate_limit.lock';
    $fh = @fopen($lockPath, 'c');
    if ($fh === false) {
        return false;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return false;
    }
    return $fh;
}

function rateLimitLoadAll(): array
{
    $path = rateLimitDataDir() . '/rate_limit.json';
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function rateLimitSaveAll(array $data, mixed $fh): void
{
    $path = rateLimitDataDir() . '/rate_limit.json';
    $tmp = $path . '.tmp';
    @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
    @rename($tmp, $path);
    flock($fh, LOCK_UN);
    fclose($fh);
}

function loginRateLimitCheck(?string $email = null): bool
{
    $key = loginRateLimitKey($email);
    $now = time();

    $fh = rateLimitLock();
    if ($fh === false) {
        return true; // fail-open si no se puede adquirir el lock
    }

    $all = rateLimitLoadAll();
    $record = $all[$key] ?? ['count' => 0, 'first' => $now, 'blocked_until' => 0];

    if ($now - $record['first'] > 900) {
        $record = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    }

    if (!empty($record['blocked_until']) && $now < $record['blocked_until']) {
        $all[$key] = $record;
        rateLimitSaveAll($all, $fh);
        return false;
    }

    $all[$key] = $record;
    rateLimitSaveAll($all, $fh);
    return true;
}

function loginRateLimitRegister(bool $success, ?string $email = null): void
{
    $key = loginRateLimitKey($email);
    $now = time();

    $fh = rateLimitLock();
    if ($fh === false) {
        return;
    }

    $all = rateLimitLoadAll();

    if ($success) {
        unset($all[$key]);
        rateLimitSaveAll($all, $fh);
        return;
    }

    $record = $all[$key] ?? ['count' => 0, 'first' => $now, 'blocked_until' => 0];

    if ($now - $record['first'] > 900) {
        $record = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    }

    $record['count']++;

    if ($record['count'] >= 5) {
        $record['blocked_until'] = $now + 900; // bloqueo 15 min
    }

    $all[$key] = $record;

    // Poda de entradas antiguas (ventana > 15 min y sin bloqueo activo)
    foreach ($all as $k => $v) {
        if (($v['blocked_until'] ?? 0) === 0 && $now - ($v['first'] ?? $now) > 900) {
            unset($all[$k]);
        }
    }

    rateLimitSaveAll($all, $fh);
}

/* -------------------- Validación de inputs -------------------- */

function sanitizeEmail(string $email): string
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeDate(string $date): string
{
    $date = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return '';
    }
    $parts = explode('-', $date);
    if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
        return '';
    }
    return $date;
}

function sanitizeYearMonth(string $ym): string
{
    $ym = trim($ym);
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        return '';
    }
    $parts = explode('-', $ym);
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        return '';
    }
    return $ym;
}

function sanitizeInt(string $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false) {
        return $min;
    }
    return max($min, min($max, $filtered));
}

function sanitizeString(string $value, int $maxLength = 255): string
{
    $value = trim($value);
    if ($maxLength > 0) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

/* -------------------- Logging de seguridad -------------------- */

function logSecurityEvent(string $event, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $logFile = $logDir . '/security-' . date('Y-m-d') . '.log';
    $entry = [
        'ts'      => date('c'),
        'event'   => $event,
        'ip'      => clientIdentifier(),
        'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
        'uri'     => $_SERVER['REQUEST_URI'] ?? '',
        'context' => $context,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/* -------------------- Headers de seguridad -------------------- */

function cspStyleNonce(): string
{
    if (empty($_SESSION['csp_style_nonce'])) {
        $_SESSION['csp_style_nonce'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csp_style_nonce'];
}

function securityHeaders(): void
{
    removeServerFingerprinting();

    if (!headers_sent()) {
        $nonce = cspStyleNonce();

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()');

        $csp = "default-src 'self'; "
             . "script-src 'self' 'nonce-" . $nonce . "'; "
             . "style-src 'self' https://fonts.googleapis.com 'nonce-" . $nonce . "'; "
             . "font-src 'self' https://fonts.gstatic.com; "
             . "img-src 'self' data:; "
             . "connect-src 'self'; "
             . "frame-ancestors 'none'; "
             . "base-uri 'self'; "
             . "form-action 'self'; "
             . "object-src 'none';";
        header('Content-Security-Policy: ' . $csp);

        if (isRequestHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
}

function removeServerFingerprinting(): void
{
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
        header_remove('Server');
    }
}

/* -------------------- Manejo seguro de errores -------------------- */

function safeErrorHandler(int $severity, string $message, string $file, int $line): bool
{
    logSecurityEvent('php_error', [
        'severity' => $severity,
        'message'  => $message,
        'file'     => basename($file),
        'line'     => $line,
    ]);

    if (ini_get('display_errors') !== '1') {
        return true; // No mostrar al usuario
    }
    return false;
}

function safeExceptionHandler(Throwable $e): void
{
    logSecurityEvent('php_exception', [
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ]);

    http_response_code(500);
    if (ini_get('display_errors') === '1') {
        echo 'Error interno del servidor.';
    } else {
        echo 'Error interno del servidor. El incidente ha sido registrado.';
    }
    exit;
}

set_error_handler('safeErrorHandler');
set_exception_handler('safeExceptionHandler');

