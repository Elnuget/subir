<?php
// Habilitar el reporte completo de errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para obtener información detallada del servidor
function getServerInfo() {
    $info = [];
    
    // Información de PHP
    $info['php_version'] = phpversion();
    $info['upload_max_filesize'] = ini_get('upload_max_filesize');
    $info['post_max_size'] = ini_get('post_max_size');
    $info['max_execution_time'] = ini_get('max_execution_time');
    $info['memory_limit'] = ini_get('memory_limit');
    $info['tmp_dir'] = sys_get_temp_dir();
    
    // Información del servidor web
    $info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido';
    $info['server_name'] = $_SERVER['SERVER_NAME'] ?? 'Desconocido';
    $info['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? 'Desconocido';
    $info['script_filename'] = $_SERVER['SCRIPT_FILENAME'] ?? 'Desconocido';
    
    // Verificar permisos del directorio actual
    $current_dir = getcwd();
    $info['current_dir'] = $current_dir;
    $info['dir_writable'] = is_writable($current_dir) ? 'Sí' : 'No';
    $info['dir_permissions'] = decoct(fileperms($current_dir) & 0777);
    
    // Verificar directorio temporal
    $temp_dir = sys_get_temp_dir();
    $info['temp_dir_writable'] = is_writable($temp_dir) ? 'Sí' : 'No';
    
    return $info;
}

// Función para validar el archivo subido
function validateUploadedFile($file) {
    $errors = [];
    
    // Verificar si se seleccionó un archivo
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "No se seleccionó ningún archivo.";
        return $errors;
    }
    
    // Verificar el tamaño del archivo
    $maxSize = min(
        parseIniSize(ini_get('upload_max_filesize')),
        parseIniSize(ini_get('post_max_size'))
    );
    
    if ($file['size'] > $maxSize) {
        $errors[] = "El archivo es demasiado grande. Tamaño máximo: " . formatBytes($maxSize);
    }
    
    // Verificar extensiones peligrosas
    $filename = $file['name'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'cmd'];
    
    if (in_array($extension, $dangerousExtensions)) {
        $errors[] = "Tipo de archivo no permitido por seguridad: .$extension";
    }
    
    // Verificar si el archivo temporal existe
    if (!file_exists($file['tmp_name'])) {
        $errors[] = "El archivo temporal no existe. Posible problema con el servidor.";
    }
    
    return $errors;
}

// Función para convertir tamaños de PHP ini a bytes
function parseIniSize($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    }
    return round($size);
}

// Función para formatear bytes
function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

// Función para obtener la lista de archivos subidos
function getUploadedFiles($directory = 'uploads/') {
    $files = [];
    
    if (!is_dir($directory)) {
        return $files;
    }
    
    $scandir = scandir($directory);
    foreach ($scandir as $file) {
        if ($file !== '.' && $file !== '..') {
            $filepath = $directory . $file;
            if (is_file($filepath)) {
                $files[] = [
                    'name' => $file,
                    'path' => $filepath,
                    'size' => filesize($filepath),
                    'modified' => filemtime($filepath),
                    'type' => mime_content_type($filepath) ?: 'Desconocido',
                    'extension' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
                ];
            }
        }
    }
    
    // Ordenar por fecha de modificación (más reciente primero)
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    return $files;
}

// Función para eliminar archivo
function deleteFile($filename, $directory = 'uploads/') {
    $filepath = $directory . basename($filename);
    
    // Verificar que el archivo existe y está en el directorio permitido
    if (file_exists($filepath) && strpos(realpath($filepath), realpath($directory)) === 0) {
        if (unlink($filepath)) {
            return ['success' => true, 'message' => 'Archivo eliminado correctamente'];
        } else {
            return ['success' => false, 'message' => 'Error al eliminar el archivo'];
        }
    }
    
    return ['success' => false, 'message' => 'Archivo no encontrado o ruta inválida'];
}

// Función para renombrar archivo
function renameFile($oldName, $newName, $directory = 'uploads/') {
    $oldPath = $directory . basename($oldName);
    $newPath = $directory . basename($newName);
    
    // Verificar que el archivo original existe
    if (!file_exists($oldPath)) {
        return ['success' => false, 'message' => 'Archivo original no encontrado'];
    }
    
    // Verificar que el nuevo nombre no existe
    if (file_exists($newPath)) {
        return ['success' => false, 'message' => 'Ya existe un archivo con ese nombre'];
    }
    
    // Verificar que ambos archivos están en el directorio permitido
    if (strpos(realpath(dirname($oldPath)), realpath($directory)) !== 0) {
        return ['success' => false, 'message' => 'Operación no permitida'];
    }
    
    // Validar nombre de archivo (sin caracteres peligrosos)
    if (preg_match('/[<>:"|*?\\\\\/]/', $newName)) {
        return ['success' => false, 'message' => 'El nombre contiene caracteres no válidos'];
    }
    
    if (rename($oldPath, $newPath)) {
        return ['success' => true, 'message' => 'Archivo renombrado correctamente'];
    } else {
        return ['success' => false, 'message' => 'Error al renombrar el archivo'];
    }
}

// Función para obtener el icono según la extensión del archivo
function getFileIcon($extension) {
    $icons = [
        'pdf' => '📄',
        'doc' => '📝', 'docx' => '📝',
        'xls' => '📊', 'xlsx' => '📊',
        'ppt' => '📺', 'pptx' => '📺',
        'txt' => '📃',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'bmp' => '🖼️',
        'mp4' => '🎬', 'avi' => '🎬', 'mkv' => '🎬', 'mov' => '🎬',
        'mp3' => '🎵', 'wav' => '🎵', 'flac' => '🎵',
        'zip' => '📦', 'rar' => '📦', '7z' => '📦',
        'html' => '🌐', 'css' => '🎨', 'js' => '⚡',
        'php' => '🐘', 'py' => '🐍', 'java' => '☕'
    ];
    
    return $icons[$extension] ?? '📄';
}

// Mensajes para mostrar al usuario
$message = '';
$message_type = 'info';
$server_info = getServerInfo();
$debug_info = [];
$uploaded_files = [];

// Manejar acciones AJAX para eliminar y renombrar archivos
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'delete':
            $filename = $_POST['filename'] ?? '';
            $result = deleteFile($filename);
            echo json_encode($result);
            exit;
            
        case 'rename':
            $oldName = $_POST['oldName'] ?? '';
            $newName = $_POST['newName'] ?? '';
            $result = renameFile($oldName, $newName);
            echo json_encode($result);
            exit;
            
        case 'getFiles':
            $files = getUploadedFiles();
            echo json_encode(['files' => $files]);
            exit;
    }
}

// Obtener lista de archivos subidos
$uploaded_files = getUploadedFiles();

// Verificar si el formulario ha sido enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_FILES["archivoParaSubir"])) {
        $file = $_FILES["archivoParaSubir"];
        
        // Diagnóstico detallado del error de subida
        if ($file["error"] !== UPLOAD_ERR_OK) {
            switch ($file["error"]) {
                case UPLOAD_ERR_INI_SIZE:
                    $message = "Error: El archivo excede upload_max_filesize (" . ini_get('upload_max_filesize') . ") en php.ini";
                    $debug_info[] = "Tamaño del archivo: " . formatBytes($file['size']);
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $message = "Error: El archivo excede MAX_FILE_SIZE del formulario HTML";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message = "Error: El archivo fue parcialmente subido (conexión interrumpida)";
                    $debug_info[] = "Posibles causas: timeout del servidor, problemas de red, límite de Nginx";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message = "Error: No se seleccionó ningún archivo";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = "Error del Servidor: Directorio temporal no encontrado";
                    $debug_info[] = "Directorio temporal configurado: " . sys_get_temp_dir();
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = "Error del Servidor: No se puede escribir en disco";
                    $debug_info[] = "Verificar permisos del directorio temporal";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $message = "Error: Subida detenida por una extensión PHP";
                    break;
                default:
                    $message = "Error desconocido de subida: " . $file["error"];
                    break;
            }
            $message_type = 'error';
        } else {
            // Validaciones adicionales
            $validation_errors = validateUploadedFile($file);
            
            if (!empty($validation_errors)) {
                $message = "Errores de validación: " . implode(", ", $validation_errors);
                $message_type = 'error';
            } else {
                // Proceder con la subida
                $target_dir = "uploads/";
                
                // Crear directorio si no existe
                if (!is_dir($target_dir)) {
                    if (!mkdir($target_dir, 0755, true)) {
                        $message = "Error: No se pudo crear el directorio de destino";
                        $message_type = 'error';
                        $debug_info[] = "Verificar permisos del directorio padre";
                    }
                }
                
                if ($message_type !== 'error') {
                    // Generar nombre único para evitar sobrescritura
                    $filename = $file["name"];
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    $basename = pathinfo($filename, PATHINFO_FILENAME);
                    $counter = 1;
                    $target_file = $target_dir . $filename;
                    
                    while (file_exists($target_file)) {
                        $target_file = $target_dir . $basename . "_" . $counter . "." . $extension;
                        $counter++;
                    }
                    
                    // Intentar mover el archivo
                    if (move_uploaded_file($file["tmp_name"], $target_file)) {
                        $message = "¡Éxito! Archivo subido: " . htmlspecialchars(basename($target_file));
                        $message_type = 'success';
                        $debug_info[] = "Tamaño: " . formatBytes($file['size']);
                        $debug_info[] = "Tipo MIME: " . $file['type'];
                        $debug_info[] = "Ruta: " . $target_file;
                    } else {
                        $last_error = error_get_last();
                        $message = "Error: No se pudo mover el archivo";
                        $message_type = 'error';
                        if ($last_error) {
                            $debug_info[] = "Error PHP: " . $last_error['message'];
                        }
                        $debug_info[] = "Verificar permisos del directorio de destino";
                        $debug_info[] = "Directorio destino: " . realpath($target_dir);
                    }
                }
            }
        }
    } else {
        $message = "Error: No se recibió información del archivo";
        $message_type = 'error';
        $debug_info[] = "Verificar configuración del formulario y límites del servidor";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Subida de Archivos - Diagnóstico Avanzado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .main-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            max-width: 1400px;
            width: 100%;
        }

        .container, .info-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .container {
            text-align: center;
        }

        .info-container {
            max-height: 700px;
            overflow-y: auto;
        }

        /* Estilos del Gestor de Archivos */
        .file-manager {
            margin-top: 30px;
            text-align: left;
        }

        .file-manager h3 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.4em;
        }

        .no-files {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 1.1em;
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .file-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .file-card:hover {
            border-color: #007bff;
            background: #e3f2fd;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,123,255,0.15);
        }

        .file-card.selected {
            border-color: #28a745;
            background: #d4edda;
        }

        .file-icon {
            font-size: 2.5em;
            flex-shrink: 0;
        }

        .file-info {
            flex-grow: 1;
            min-width: 0;
        }

        .file-name {
            font-weight: bold;
            color: #333;
            font-size: 1em;
            margin-bottom: 5px;
            word-break: break-word;
        }

        .file-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.85em;
            color: #6c757d;
        }

        .file-size {
            font-weight: 500;
        }

        .file-date {
            font-style: italic;
        }

        .file-actions {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .file-card:hover .file-actions {
            opacity: 1;
        }

        .btn-action {
            background: none;
            border: none;
            font-size: 1.2em;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            background: rgba(255,255,255,0.8);
            transform: scale(1.1);
        }

        .btn-download:hover { color: #007bff; }
        .btn-rename:hover { color: #ffc107; }
        .btn-delete:hover { color: #dc3545; }

        .files-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-refresh, .btn-select-all, .btn-delete-selected {
            background: linear-gradient(45deg, #6c757d, #495057);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .btn-refresh:hover {
            background: linear-gradient(45deg, #5a6268, #343a40);
            transform: translateY(-2px);
        }

        .btn-select-all:hover {
            background: linear-gradient(45deg, #007bff, #0056b3);
        }

        .btn-delete-selected {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }

        .btn-delete-selected:hover {
            background: linear-gradient(45deg, #c82333, #a71e2a);
        }

        /* Modal para renombrar */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal h3 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }

        .modal input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        .modal input[type="text"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-confirm {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }

        .btn-cancel:hover, .btn-confirm:hover {
            transform: translateY(-2px);
        }

        h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.8em;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .upload-area {
            border: 3px dashed #007bff;
            border-radius: 10px;
            padding: 40px 20px;
            margin: 20px 0;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: #0056b3;
            background: #e3f2fd;
        }

        .upload-area.dragover {
            border-color: #28a745;
            background: #d4edda;
        }

        input[type="file"] {
            display: none;
        }

        .file-input-label {
            display: inline-block;
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .file-input-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }

        input[type="submit"] {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }

        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
            word-wrap: break-word;
        }

        .error {
            color: #721c24;
            background-color: #f8d7da;
            border: 2px solid #f5c6cb;
            animation: shake 0.5s;
        }

        .success {
            color: #155724;
            background-color: #d4edda;
            border: 2px solid #c3e6cb;
            animation: slideIn 0.5s;
        }

        .info {
            color: #0c5460;
            background-color: #d1ecf1;
            border: 2px solid #b6d4da;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .server-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .server-info h3 {
            color: #495057;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .info-item {
            margin: 8px 0;
            font-size: 0.9em;
        }

        .info-label {
            font-weight: bold;
            color: #6c757d;
        }

        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-top: 15px;
        }

        .debug-info ul {
            margin-left: 20px;
        }

        .debug-info li {
            margin: 5px 0;
            font-size: 0.9em;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin: 10px 0;
            display: none;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            width: 0%;
            transition: width 0.3s ease;
        }

        .file-preview {
            text-align: left;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            display: none;
        }

        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .container, .info-container {
                padding: 20px;
            }

            .files-grid {
                grid-template-columns: 1fr;
            }

            .file-card {
                padding: 12px;
            }

            .files-actions {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="container">
        <h2>🚀 Sistema de Subida Avanzado</h2>
        <form action="index.php" method="post" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" id="uploadArea">
                <label for="archivoParaSubir" class="file-input-label">
                    📁 Seleccionar Archivo
                </label>
                <input type="file" name="archivoParaSubir" id="archivoParaSubir" accept="*/*">
                <div class="file-preview" id="filePreview">
                    <strong>Archivo seleccionado:</strong>
                    <div id="fileName"></div>
                    <div id="fileSize"></div>
                    <div id="fileType"></div>
                </div>
                <p>O arrastra y suelta un archivo aquí</p>
            </div>
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <input type="submit" value="⬆️ Subir Archivo" name="submit">
        </form>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <strong><?php echo $message; ?></strong>
                <?php if (!empty($debug_info)): ?>
                    <div class="debug-info">
                        <strong>Información de diagnóstico:</strong>
                        <ul>
                            <?php foreach ($debug_info as $info): ?>
                                <li><?php echo htmlspecialchars($info); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Gestor de Archivos -->
        <div class="file-manager" id="fileManager">
            <h3>📂 Archivos Subidos (<?php echo count($uploaded_files); ?>)</h3>
            
            <?php if (empty($uploaded_files)): ?>
                <div class="no-files">
                    <p>🗂️ No hay archivos subidos todavía</p>
                </div>
            <?php else: ?>
                <div class="files-grid" id="filesGrid">
                    <?php foreach ($uploaded_files as $file): ?>
                        <div class="file-card" data-filename="<?php echo htmlspecialchars($file['name']); ?>">
                            <div class="file-icon">
                                <?php echo getFileIcon($file['extension']); ?>
                            </div>
                            <div class="file-info">
                                <div class="file-name" title="<?php echo htmlspecialchars($file['name']); ?>">
                                    <?php echo htmlspecialchars(strlen($file['name']) > 20 ? substr($file['name'], 0, 17) . '...' : $file['name']); ?>
                                </div>
                                <div class="file-details">
                                    <div class="file-size"><?php echo formatBytes($file['size']); ?></div>
                                    <div class="file-date"><?php echo date('d/m/Y H:i', $file['modified']); ?></div>
                                </div>
                            </div>
                            <div class="file-actions">
                                <button class="btn-action btn-download" onclick="downloadFile('<?php echo htmlspecialchars($file['name']); ?>')" title="Descargar">
                                    ⬇️
                                </button>
                                <button class="btn-action btn-rename" onclick="renameFile('<?php echo htmlspecialchars($file['name']); ?>')" title="Renombrar">
                                    ✏️
                                </button>
                                <button class="btn-action btn-delete" onclick="deleteFile('<?php echo htmlspecialchars($file['name']); ?>')" title="Eliminar">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="files-actions">
                    <button class="btn-refresh" onclick="refreshFiles()">🔄 Actualizar</button>
                    <button class="btn-select-all" onclick="selectAllFiles()">☑️ Seleccionar todo</button>
                    <button class="btn-delete-selected" onclick="deleteSelectedFiles()" style="display: none;">🗑️ Eliminar seleccionados</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-container">
        <div class="server-info">
            <h3>📊 Información del Servidor</h3>
            <div class="info-item">
                <span class="info-label">Servidor Web:</span> <?php echo htmlspecialchars($server_info['server_software']); ?>
            </div>
            <div class="info-item">
                <span class="info-label">PHP Version:</span> <?php echo $server_info['php_version']; ?>
            </div>
            <div class="info-item">
                <span class="info-label">Tamaño máximo de subida:</span> <?php echo $server_info['upload_max_filesize']; ?>
            </div>
            <div class="info-item">
                <span class="info-label">POST máximo:</span> <?php echo $server_info['post_max_size']; ?>
            </div>
            <div class="info-item">
                <span class="info-label">Memoria límite:</span> <?php echo $server_info['memory_limit']; ?>
            </div>
            <div class="info-item">
                <span class="info-label">Tiempo máximo:</span> <?php echo $server_info['max_execution_time']; ?>s
            </div>
        </div>

        <div class="server-info">
            <h3>📁 Información de Directorios</h3>
            <div class="info-item">
                <span class="info-label">Directorio actual:</span> <?php echo htmlspecialchars($server_info['current_dir']); ?>
            </div>
            <div class="info-item">
                <span class="info-label">Escritura permitida:</span> 
                <span style="color: <?php echo $server_info['dir_writable'] === 'Sí' ? 'green' : 'red'; ?>">
                    <?php echo $server_info['dir_writable']; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Permisos:</span> <?php echo $server_info['dir_permissions']; ?>
            </div>
            <div class="info-item">
                <span class="info-label">Dir. temporal:</span> <?php echo htmlspecialchars($server_info['tmp_dir']); ?>
            </div>
            <div class="info-item">
                <span class="info-label">Temp escribible:</span> 
                <span style="color: <?php echo $server_info['temp_dir_writable'] === 'Sí' ? 'green' : 'red'; ?>">
                    <?php echo $server_info['temp_dir_writable']; ?>
                </span>
            </div>
        </div>

        <div class="server-info">
            <h3>ℹ️ Consejos y Notas</h3>
            <div class="info-item">• Archivos .php, .exe y similares están bloqueados por seguridad</div>
            <div class="info-item">• Se crearán nombres únicos si el archivo ya existe</div>
            <div class="info-item">• Los archivos se guardan en la carpeta 'uploads/'</div>
            <div class="info-item">• Límites configurados por Nginx y PHP</div>
            <div class="info-item">• Drag & drop soportado en navegadores modernos</div>
        </div>
    </div>
</div>

<!-- Modal para renombrar archivos -->
<div id="renameModal" class="modal">
    <div class="modal-content">
        <h3>✏️ Renombrar Archivo</h3>
        <input type="text" id="newFileName" placeholder="Nuevo nombre del archivo">
        <div class="modal-buttons">
            <button class="btn-cancel" onclick="closeRenameModal()">Cancelar</button>
            <button class="btn-confirm" onclick="confirmRename()">Renombrar</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('archivoParaSubir');
    const uploadArea = document.getElementById('uploadArea');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileType = document.getElementById('fileType');
    const progressBar = document.getElementById('progressBar');
    const progressFill = document.getElementById('progressFill');
    const form = document.getElementById('uploadForm');

    let selectedFiles = new Set();
    let currentFileToRename = '';

    // Manejo del drag & drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            showFilePreview(files[0]);
        }
    });

    // Mostrar preview del archivo seleccionado
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            showFilePreview(e.target.files[0]);
        }
    });

    function showFilePreview(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileType.textContent = file.type || 'Tipo desconocido';
        filePreview.style.display = 'block';
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Simulación de barra de progreso al enviar
    form.addEventListener('submit', function(e) {
        if (fileInput.files.length === 0) {
            alert('Por favor selecciona un archivo primero.');
            e.preventDefault();
            return;
        }

        progressBar.style.display = 'block';
        let progress = 0;
        const interval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress >= 90) {
                progress = 90;
                clearInterval(interval);
            }
            progressFill.style.width = progress + '%';
        }, 100);
    });

    // Funciones del gestor de archivos
    window.downloadFile = function(filename) {
        window.open('download.php?file=' + encodeURIComponent(filename), '_blank');
    };

    window.deleteFile = function(filename) {
        if (confirm('¿Estás seguro de que quieres eliminar "' + filename + '"?')) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete&filename=' + encodeURIComponent(filename)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    refreshFiles();
                } else {
                    showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('❌ Error de conexión', 'error');
                console.error('Error:', error);
            });
        }
    };

    window.renameFile = function(filename) {
        currentFileToRename = filename;
        document.getElementById('newFileName').value = filename;
        document.getElementById('renameModal').style.display = 'block';
        
        // Seleccionar el nombre sin la extensión
        const input = document.getElementById('newFileName');
        const lastDotIndex = filename.lastIndexOf('.');
        if (lastDotIndex > 0) {
            setTimeout(() => {
                input.setSelectionRange(0, lastDotIndex);
                input.focus();
            }, 100);
        }
    };

    window.closeRenameModal = function() {
        document.getElementById('renameModal').style.display = 'none';
        currentFileToRename = '';
    };

    window.confirmRename = function() {
        const newName = document.getElementById('newFileName').value.trim();
        
        if (newName === '') {
            alert('Por favor ingresa un nombre válido.');
            return;
        }

        if (newName === currentFileToRename) {
            closeRenameModal();
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=rename&oldName=' + encodeURIComponent(currentFileToRename) + '&newName=' + encodeURIComponent(newName)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('✅ ' + data.message, 'success');
                refreshFiles();
                closeRenameModal();
            } else {
                showNotification('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('❌ Error de conexión', 'error');
            console.error('Error:', error);
        });
    };

    window.refreshFiles = function() {
        location.reload();
    };

    window.selectAllFiles = function() {
        const fileCards = document.querySelectorAll('.file-card');
        const allSelected = selectedFiles.size === fileCards.length;
        
        if (allSelected) {
            // Deseleccionar todos
            selectedFiles.clear();
            fileCards.forEach(card => card.classList.remove('selected'));
            document.querySelector('.btn-select-all').textContent = '☑️ Seleccionar todo';
            document.querySelector('.btn-delete-selected').style.display = 'none';
        } else {
            // Seleccionar todos
            fileCards.forEach(card => {
                const filename = card.dataset.filename;
                selectedFiles.add(filename);
                card.classList.add('selected');
            });
            document.querySelector('.btn-select-all').textContent = '❌ Deseleccionar todo';
            document.querySelector('.btn-delete-selected').style.display = 'inline-block';
        }
    };

    window.deleteSelectedFiles = function() {
        if (selectedFiles.size === 0) return;
        
        if (confirm(`¿Estás seguro de que quieres eliminar ${selectedFiles.size} archivo(s) seleccionado(s)?`)) {
            const promises = Array.from(selectedFiles).map(filename => {
                return fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete&filename=' + encodeURIComponent(filename)
                }).then(response => response.json());
            });

            Promise.all(promises).then(results => {
                const successful = results.filter(r => r.success).length;
                const failed = results.length - successful;
                
                if (successful > 0) {
                    showNotification(`✅ ${successful} archivo(s) eliminado(s) correctamente`, 'success');
                }
                if (failed > 0) {
                    showNotification(`❌ ${failed} archivo(s) no pudieron ser eliminados`, 'error');
                }
                
                refreshFiles();
            });
        }
    };

    // Manejar clicks en las tarjetas de archivo para selección múltiple
    document.addEventListener('click', function(e) {
        if (e.target.closest('.file-card') && !e.target.closest('.btn-action')) {
            const card = e.target.closest('.file-card');
            const filename = card.dataset.filename;
            
            if (e.ctrlKey || e.metaKey) {
                // Selección múltiple
                if (selectedFiles.has(filename)) {
                    selectedFiles.delete(filename);
                    card.classList.remove('selected');
                } else {
                    selectedFiles.add(filename);
                    card.classList.add('selected');
                }
                
                // Actualizar botones
                if (selectedFiles.size > 0) {
                    document.querySelector('.btn-delete-selected').style.display = 'inline-block';
                } else {
                    document.querySelector('.btn-delete-selected').style.display = 'none';
                }
            }
        }
    });

    function showNotification(message, type) {
        // Crear y mostrar notificación temporal
        const notification = document.createElement('div');
        notification.className = `message ${type}`;
        notification.innerHTML = `<strong>${message}</strong>`;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.maxWidth = '400px';
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 4000);
    }

    // Cerrar modal al hacer clic fuera
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('renameModal');
        if (e.target === modal) {
            closeRenameModal();
        }
    });

    // Manejar Enter en el campo de renombrar
    document.getElementById('newFileName').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            confirmRename();
        } else if (e.key === 'Escape') {
            closeRenameModal();
        }
    });
});
</script>
</body>
</html>s