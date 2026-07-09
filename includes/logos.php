<?php
function logoClienteRuta(?string $codigoCliente): string
{
    $base = '../assets/logos/';

    if (!$codigoCliente || !preg_match('/^[A-Za-z0-9_-]+$/', $codigoCliente)) {
        return $base . 'default.svg';
    }

    $carpeta = __DIR__ . '/../assets/logos/';
    $extensionesPermitidas = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

    foreach ($extensionesPermitidas as $ext) {
        $archivo = $carpeta . $codigoCliente . '.' . $ext;
        $realPath = realpath($archivo);
        $realBase = realpath($carpeta);

        if (
            $realPath !== false
            && $realBase !== false
            && strpos($realPath, $realBase . DIRECTORY_SEPARATOR) === 0
            && file_exists($realPath)
            && is_file($realPath)
        ) {
            return $base . $codigoCliente . '.' . $ext;
        }
    }

    return $base . 'default.svg';
}
