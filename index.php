<?php
// Mensaje para mostrar al usuario, inicialmente vacío.
$message = '';
$message_type = 'error'; // Para cambiar el color del mensaje (error o success)

// Verificar si el formulario ha sido enviado usando el método POST.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- NUEVO: Diagnóstico detallado del error de subida ---
    if (isset($_FILES["archivoParaSubir"])) {
        $upload_error = $_FILES["archivoParaSubir"]["error"];
        
        if ($upload_error !== UPLOAD_ERR_OK) {
            switch ($upload_error) {
                case UPLOAD_ERR_INI_SIZE:
                    $message = "Error: El archivo excede el tamaño máximo permitido por el servidor (upload_max_filesize en php.ini).";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $message = "Error: El archivo excede el tamaño máximo especificado en el formulario HTML.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message = "Error: El archivo fue solo parcialmente subido.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message = "Error: No se seleccionó ningún archivo para subir.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = "Error Crítico: Falta la carpeta temporal del servidor. Contacta al administrador.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = "Error Crítico: No se pudo escribir el archivo en el disco. Permisos incorrectos en la carpeta temporal.";
                    break;
                default:
                    $message = "Error desconocido durante la subida del archivo.";
                    break;
            }
        } else {
            // Si no hubo errores iniciales, procedemos con las validaciones
            $target_dir = ""; // Guardar en la carpeta actual
            $target_file = $target_dir . basename($_FILES["archivoParaSubir"]["name"]);
            $uploadOk = 1;
            $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // --- NUEVO: Chequeo explícito de permisos en la carpeta de destino ---
            if (!is_writable(getcwd())) {
                 $message = "¡ERROR DE PERMISOS! El script no tiene permiso para escribir en la carpeta: " . getcwd();
                 $uploadOk = 0;
            }

            if ($uploadOk && file_exists($target_file)) {
                $message = "Error: El archivo ya existe en el destino.";
                $uploadOk = 0;
            }
            
            // Si todo está bien hasta ahora, intentamos mover el archivo
            if ($uploadOk) {
                if (move_uploaded_file($_FILES["archivoParaSubir"]["tmp_name"], $target_file)) {
                    $fileName = htmlspecialchars(basename($_FILES["archivoParaSubir"]["name"]));
                    $message = "¡Éxito! El archivo ". $fileName . " ha sido subido correctamente.";
                    $message_type = 'success';
                } else {
                    // Si move_uploaded_file falla, damos más detalles si es posible
                    $last_error = error_get_last();
                    $message = "Error final: No se pudo mover el archivo. Posible causa: " . ($last_error['message'] ?? 'Permisos incorrectos o ruta inválida.');
                }
            }
        }
    } else {
        $message = "Error: No se recibió ninguna información de archivo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Archivos (Modo Diagnóstico)</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; width: 500px; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 20px; }
        .message { margin-top: 20px; font-size: 1.1em; padding: 10px; border-radius: 5px; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
<div class="container">
    <h2>Sube tu Archivo</h2>
    <form action="index.php" method="post" enctype="multipart/form-data">
        <input type="file" name="archivoParaSubir" id="archivoParaSubir">
        <br>
        <input type="submit" value="Subir Archivo" name="submit">
    </form>
    <?php if (!empty($message)): ?>
        <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
    <?php endif; ?>
</div>
</body>
</html>s