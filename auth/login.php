<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/api_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/modulos.php';

securityHeaders();

if (isLoggedIn()) {
    header('Location: ../pages/dashboard.php');
    exit;
}

$error = null;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate()) {
        $error = 'Solicitud no válida. Por favor intenta de nuevo.';
        logSecurityEvent('csrf_validation_failed', ['uri' => $_SERVER['REQUEST_URI']]);
        csrfRefresh();
    } elseif (!loginRateLimitCheck()) {
        $error = 'Demasiados intentos fallidos. Cuenta temporalmente bloqueada. Intenta en 15 minutos.';
        logSecurityEvent('login_rate_limited', ['reason' => 'too_many_attempts']);
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $emailValue = $email;

        if ($email === '' || $password === '') {
            $error = 'Ingresa tu correo y contraseña.';
            loginRateLimitRegister(false);
            logSecurityEvent('login_failed', ['reason' => 'empty_credentials']);
        } elseif (!validateEmail($email)) {
            $error = 'El correo ingresado no tiene un formato válido.';
            loginRateLimitRegister(false);
            logSecurityEvent('login_failed', ['reason' => 'invalid_email_format', 'email' => $email]);
        } elseif (strlen($password) > 256) {
            $error = 'Credenciales inválidas.';
            loginRateLimitRegister(false);
            logSecurityEvent('login_failed', ['reason' => 'password_too_long', 'email' => $email]);
        } else {
            $respuesta = apiPost('/api/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

            if (!empty($respuesta['error']) || empty($respuesta['token'])) {
                $error = 'Credenciales inválidas o el gateway no está disponible.';
                loginRateLimitRegister(false);
                logSecurityEvent('login_failed', ['reason' => 'invalid_credentials_or_gateway', 'email' => $email]);
            } else {
                loginRateLimitRegister(true);

                // Regenerar ID de sesión para prevenir session fixation
                session_regenerate_id(true);

                $codigoCliente = $respuesta['codigo_cliente'] ?? null;

                $_SESSION['auth_token'] = $respuesta['token'];
                $_SESSION['user'] = [
                    'name' => $respuesta['nombre'] ?? $email,
                    'email' => $respuesta['user'] ?? $email,
                    'codigo_cliente' => $codigoCliente,
                    'user_master' => $respuesta['user_master'] ?? null,
                ];

                if ($codigoCliente) {
                    $_SESSION['modulos'] = modulosPorCliente($codigoCliente);
                } else {
                    $_SESSION['modulos'] = [];
                }

                csrfRefresh();
                logSecurityEvent('login_success', ['email' => $email]);

                header('Location: ../pages/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="es">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>DCA - Desarrollo | Acceso Seguro</title>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <header>
        <div class="brand">DCA - Desarrollo</div>
        <div class="lang">
            <span class="material-symbols-outlined">language</span>
            <span>Español</span>
        </div>
    </header>

    <main>
        <div class="login-card">
            <div class="login-hero">
                <div class="deco">
                    <span class="material-symbols-outlined">shield</span>
                </div>
                <div class="content">
                    <div class="status">
                        <div class="dot"></div>
                        <span>Estado del Sistema: Activo</span>
                    </div>
                    <h1>Seguridad de Datos de Nivel Empresarial.</h1>
                    <p>Protegemos su infraestructura con los estándares más altos de encriptación y control de acceso.</p>
                    <div class="security-list">
                        <div class="security-item delay-1">
                            <span class="material-symbols-outlined">lock</span>
                            <div>
                                <h3>Encriptación de Extremo a Extremo</h3>
                                <p>AES-256 bits para todos los datos en tránsito y reposo.</p>
                            </div>
                        </div>
                        <div class="security-item delay-2">
                            <span class="material-symbols-outlined">verified_user</span>
                            <div>
                                <h3>Autenticación Multi-factor (MFA)</h3>
                                <p>Capa extra de seguridad obligatoria para accesos externos.</p>
                            </div>
                        </div>
                        <div class="security-item delay-3">
                            <span class="material-symbols-outlined">security</span>
                            <div>
                                <h3>Auditoría en Tiempo Real</h3>
                                <p>Monitoreo constante de actividades y registros de acceso.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="login-form-wrap">
                <div class="inner">
                    <div class="title-block">
                        <h2>Acceso Seguro</h2>
                        <p>Identifíquese para continuar al panel de control.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="login-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="login.php">
                        <?= csrfField() ?>
                        <div class="form-field">
                            <label for="email">Correo Corporativo</label>
                            <div class="input-wrap">
                                <span class="icon"><span class="material-symbols-outlined">alternate_email</span></span>
                                <input id="email" name="email" placeholder="usuario@dca-desarrollo.com" type="email" required autocomplete="email" value="<?= htmlspecialchars($emailValue) ?>">
                            </div>
                        </div>

                        <div class="form-field">
                            <div class="form-field-row">
                                <label for="password">Contraseña</label>
                                <a class="label-link" href="#">¿Olvidó su clave?</a>
                            </div>
                            <div class="input-wrap">
                                <span class="icon"><span class="material-symbols-outlined">key</span></span>
                                <input id="password" name="password" placeholder="••••••••" type="password" required autocomplete="current-password">
                            </div>
                        </div>

                        <div class="remember-row">
                            <input id="remember" name="remember" type="checkbox">
                            <label for="remember">Mantener sesión iniciada</label>
                        </div>

                        <button class="btn-submit" type="submit">
                            Iniciar Sesión Segura
                            <span class="material-symbols-outlined">lock_open</span>
                        </button>

                        <div class="form-footer">
                            <p>¿Problemas para acceder? <a href="#">Soporte de Seguridad</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="footer-inner">
            <div class="copyright">
                <span class="material-symbols-outlined icon-xs">copyright</span>
                2026 DCA - Desarrollo. Todos los derechos reservados.
            </div>
            <div class="footer-links">
                <a href="#">Política de Privacidad</a>
                <a href="#">Términos de Seguridad</a>
            </div>
        </div>
    </footer>
</body>

</html>