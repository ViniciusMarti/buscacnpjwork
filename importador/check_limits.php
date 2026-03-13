<?php
/**
 * Diagnóstico de Limites de Upload
 * Local: /public_html/importador/check_limits.php
 */
echo "<h1>PHP Upload Limits</h1>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "max_input_time: " . ini_get('max_input_time') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
?>
