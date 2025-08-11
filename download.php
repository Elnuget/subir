<?php
// Archivo para manejar descargas seguras
if (!isset($_GET['file'])) {
    http_response_code(400);
    die('Archivo no especificado');
}

$filename = basename($_GET['file']);
$filepath = 'uploads/' . $filename;

// Verificar que el archivo existe
if (!file_exists($filepath)) {
    http_response_code(404);
    die('Archivo no encontrado');
}

// Verificar que está en el directorio correcto (seguridad)
if (strpos(realpath($filepath), realpath('uploads/')) !== 0) {
    http_response_code(403);
    die('Acceso denegado');
}

// Obtener información del archivo
$filesize = filesize($filepath);
$mimetype = mime_content_type($filepath) ?: 'application/octet-stream';

// Configurar headers para descarga
header('Content-Type: ' . $mimetype);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Leer y enviar el archivo
readfile($filepath);
exit;
?>
