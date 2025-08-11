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
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

// Mensajes para mostrar al usuario
$message = '';
$message_type = 'info';
$server_info = getServerInfo();
$debug_info = [];

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
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            max-width: 1200px;
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
            max-height: 500px;
            overflow-y: auto;
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
});
</script>
</body>
</html>s