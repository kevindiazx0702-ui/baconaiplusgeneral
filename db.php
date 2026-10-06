<?php
// db.php - SISTEMA DE ALMACENAMIENTO SEGURO (VERSIÓN CORREGIDA)

function loadData($filename) {
    // Si no tiene extensión .php, se la ponemos
    if (strpos($filename, '.php') === false) {
        $filename .= '.php';
    }
    
    if (!file_exists($filename)) {
        return []; // Retorna array vacío si no existe el archivo
    }
    
    // Incluimos el archivo
    $data = include $filename;
    
    // BLINDAJE: Si el archivo está corrupto o devuelve 1 (éxito de include), forzar array
    if (!is_array($data)) {
        return [];
    }
    
    return $data;
}

function saveData($filename, $data) {
    if (strpos($filename, '.php') === false) {
        $filename .= '.php';
    }
    
    // Aseguramos que data sea un array
    if (!is_array($data)) {
        $data = [];
    }
    
    $content = "<?php\ndefined('SECURE_ACCESS') or die('Acceso denegado');\nreturn " . var_export($data, true) . ";\n";
    
    // Usamos LOCK_EX para evitar conflictos de escritura
    return file_put_contents($filename, $content, LOCK_EX);
}
?>