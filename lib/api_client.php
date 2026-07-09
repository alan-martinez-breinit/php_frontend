<?php

/**
 * Cliente seguro para el backend FastAPI.
 * - Todas las llamadas son servidor-a-servidor.
 * - Protección básica contra SSRF (solo se permite llamar a FASTAPI_BASE_URL).
 * - Timeouts configurados y verificación SSL.
 */

define('PHP_UA', 'DCA-Frontend/1.0');
define('API_TIMEOUT_DEFAULT', 220);
define('API_TIMEOUT_POST', 15);
define('API_TIMEOUT_PARALELO', 200);
define('API_CACHE_TTL', 60);

/* =========================================================================
 * Helpers internos (cURL / headers / JSON)
 * ========================================================================= */

/**
 * Construye los headers comunes para toda petición al gateway,
 * incluyendo el Bearer token si hay sesión activa.
 */
function apiBuildHeaders(bool $conJson = false): array
{
  $headers = ['Accept: application/json', 'User-Agent: ' . PHP_UA];
  if ($conJson) {
    $headers[] = 'Content-Type: application/json';
  }
  if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['auth_token'])) {
    $headers[] = 'Authorization: Bearer ' . $_SESSION['auth_token'];
  }
  return $headers;
}

/**
 * Aplica las opciones de seguridad y timeout comunes a un handle cURL.
 */
function apiConfigurarCurl(\CurlHandle $ch, array $headers, int $timeout): void
{
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_MAXREDIRS      => 0,
  ]);
}

/**
 * Decodifica un body JSON del gateway.
 * Devuelve array normalizado o un array de error si el JSON es inválido.
 */
function apiDecodificarBody(string $body): array
{
  $decoded = json_decode($body, true);
  if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    return ['error' => true, 'message' => 'Respuesta invalida del gateway', 'raw' => $body];
  }
  return is_array($decoded) ? $decoded : ['data' => $decoded];
}

/* =========================================================================
 * Caché en disco
 * ========================================================================= */

/**
 * Caché de respuestas del gateway en disco (directorio data/cache, no web-accesible).
 * Reduce latencia en dashboards y amortigua caídas breves del backend.
 * Se omite cuando el endpoint lleva refrescar=true (forzar lectura fresca).
 */
function apiCacheDir(): string
{
  $dir = __DIR__ . '/../data/cache';
  if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
  }
  return $dir;
}

function apiCacheArchivo(string $endpoint): string
{
  return apiCacheDir() . '/' . md5($endpoint) . '.json';
}

/**
 * Lee y valida una entrada de caché. Devuelve el registro completo
 * ['exp' => ..., 'data' => ..., 'meta' => ...] o null si no existe/expiró.
 */
function apiCacheLeer(string $endpoint): ?array
{
  $file = apiCacheArchivo($endpoint);
  if (!is_file($file)) {
    return null;
  }
  $raw = @file_get_contents($file);
  if ($raw === false) {
    return null;
  }
  $dec = json_decode($raw, true);
  if (!is_array($dec) || !isset($dec['exp'], $dec['data']) || time() > $dec['exp']) {
    @unlink($file);
    return null;
  }
  return $dec;
}

function apiCacheGet(string $endpoint): ?array
{
  if (strpos($endpoint, 'refrescar=true') !== false) {
    return null;
  }
  $registro = apiCacheLeer($endpoint);
  return $registro['data'] ?? null;
}

function apiCacheGetMeta(string $endpoint): ?array
{
  $registro = apiCacheLeer($endpoint);
  return $registro['meta'] ?? null;
}

function apiCacheSet(string $endpoint, array $data, int $ttl = API_CACHE_TTL, ?array $meta = null): void
{
  if (strpos($endpoint, 'refrescar=true') !== false) {
    return;
  }
  @file_put_contents(
    apiCacheArchivo($endpoint),
    json_encode(['exp' => time() + $ttl, 'data' => $data, 'meta' => $meta], JSON_UNESCAPED_SLASHES),
    LOCK_EX
  );
}

/* =========================================================================
 * Validación y parsing
 * ========================================================================= */

/**
 * Valida que un endpoint no intente salir del host configurado.
 */
function apiValidateEndpoint(string $endpoint): bool
{
  if ($endpoint === '') {
    return false;
  }
  // Rechazar endpoints absolutos (podrían apuntar a otro host)
  if (preg_match('#^https?://#i', $endpoint)) {
    return false;
  }
  // Rechazar secuencias de path traversal
  if (strpos($endpoint, '..') !== false) {
    return false;
  }
  // Rechazar null bytes
  if (strpos($endpoint, "\0") !== false) {
    return false;
  }
  return true;
}

/**
 * Extrae metadatos de paginación de las cabeceras del gateway
 * (X-Total-Count, X-Has-More, X-Next-Offset) devueltas por los endpoints .get("/").
 */
function parsePaginationHeaders(string $blob): array
{
  $meta = ['total' => null, 'has_more' => false, 'next_offset' => null];
  if ($blob === '') {
    return $meta;
  }
  $lines = preg_split('/\r\n|\n|\r/', $blob) ?: [];
  foreach ($lines as $line) {
    if (strpos($line, ':') === false) {
      continue;
    }
    [$name, $val] = explode(':', $line, 2);
    $name = strtolower(trim($name));
    $val = trim($val);
    if ($name === 'x-total-count') {
      $meta['total'] = is_numeric($val) ? (int)$val : null;
    } elseif ($name === 'x-has-more') {
      $meta['has_more'] = in_array(strtolower($val), ['true', '1', 'on', 'yes'], true);
    } elseif ($name === 'x-next-offset') {
      $meta['next_offset'] = is_numeric($val) ? (int)$val : null;
    }
  }
  return $meta;
}

/* =========================================================================
 * Llamadas al gateway
 * ========================================================================= */

function apiGet(string $endpoint, int $ttl = API_CACHE_TTL, ?array &$meta = null): array
{
  if (!apiValidateEndpoint($endpoint)) {
    return ['error' => true, 'message' => 'Endpoint no permitido'];
  }

  if ($ttl > 0) {
    $cached = apiCacheGet($endpoint);
    if ($cached !== null) {
      if ($meta !== null) {
        $meta = apiCacheGetMeta($endpoint)
          ?? ['total' => null, 'has_more' => false, 'next_offset' => null];
      }
      return $cached;
    }
  }

  $ch = curl_init(FASTAPI_BASE_URL . $endpoint);
  apiConfigurarCurl($ch, apiBuildHeaders(), API_TIMEOUT_DEFAULT);
  curl_setopt($ch, CURLOPT_HEADER, true);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    return ['error' => true, 'message' => 'Error de conexion con el gateway'];
  }

  $headerBlob = $headerSize > 0 ? substr($response, 0, $headerSize) : '';
  $body = $headerSize > 0 ? substr($response, $headerSize) : $response;
  $respMeta = parsePaginationHeaders($headerBlob);

  if ($httpCode >= 400) {
    $decoded = json_decode($body, true);
    $msg = 'El gateway respondio con un error';
    if (is_array($decoded) && !empty($decoded['detail'])) {
      $msg .= ': ' . json_encode($decoded['detail']);
    }
    return [
      'error' => true,
      'message' => $msg,
      'http_code' => $httpCode,
      'raw' => $body,
    ];
  }

  $out = apiDecodificarBody($body);
  if (!empty($out['error'])) {
    return $out;
  }

  if ($ttl > 0) {
    apiCacheSet($endpoint, $out, $ttl, $respMeta);
  }
  if ($meta !== null) {
    $meta = $respMeta;
  }
  return $out;
}

function apiGetParalelo(array $endpoints): array
{
  $headers = apiBuildHeaders();
  $multiHandle = curl_multi_init();
  $canales = [];
  $resultados = [];

  foreach ($endpoints as $clave => $endpoint) {
    if (!apiValidateEndpoint($endpoint)) {
      $resultados[$clave] = ['error' => true, 'message' => 'Endpoint no permitido'];
      continue;
    }

    $cached = apiCacheGet($endpoint);
    if ($cached !== null) {
      $resultados[$clave] = $cached;
      continue;
    }

    $ch = curl_init(FASTAPI_BASE_URL . $endpoint);
    apiConfigurarCurl($ch, $headers, API_TIMEOUT_PARALELO);
    curl_multi_add_handle($multiHandle, $ch);
    $canales[$clave] = $ch;
  }

  $activos = null;
  do {
    $estado = curl_multi_exec($multiHandle, $activos);
    if ($activos) {
      curl_multi_select($multiHandle, 1.0);
    }
  } while ($activos && $estado === CURLM_OK);

  foreach ($canales as $clave => $ch) {
    $respuesta = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);

    if ($error) {
      $resultados[$clave] = ['error' => true, 'message' => 'Error de conexion con el gateway'];
      continue;
    }
    if ($httpCode >= 400) {
      $resultados[$clave] = ['error' => true, 'message' => 'El gateway respondio con un error', 'raw' => $respuesta];
      continue;
    }

    $out = apiDecodificarBody($respuesta);
    if (!empty($out['error'])) {
      $resultados[$clave] = $out;
      continue;
    }

    apiCacheSet($endpoints[$clave], $out);
    $resultados[$clave] = $out;
  }

  curl_multi_close($multiHandle);

  return $resultados;
}

function apiPost(string $endpoint, array $payload): array
{
  if (!apiValidateEndpoint($endpoint)) {
    return ['error' => true, 'message' => 'Endpoint no permitido'];
  }

  $ch = curl_init(FASTAPI_BASE_URL . $endpoint);
  apiConfigurarCurl($ch, apiBuildHeaders(true), API_TIMEOUT_POST);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    return ['error' => true, 'message' => 'Error de conexion con el gateway'];
  }

  if ($httpCode >= 400) {
    return [
      'error' => true,
      'message' => 'El gateway respondio con un error',
      'raw' => $response,
    ];
  }

  return apiDecodificarBody($response);
}
