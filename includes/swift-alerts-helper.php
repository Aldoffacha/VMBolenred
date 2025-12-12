<?php
/**
 * Helper para Swift Alerts - Genera JavaScript para mostrar alertas desde PHP
 * Uso: 
 *   swiftAlert("Mensaje", "success", 5000);
 *   swiftSuccess("Operación completada");
 *   swiftError("Error al procesar");
 */

/**
 * Mostrar alerta Swift directamente desde PHP
 * @param string $message Mensaje a mostrar
 * @param string $type Tipo: 'success', 'danger', 'warning', 'info'
 * @param int $duration Duración en ms
 */
function swiftAlert($message, $type = 'info', $duration = 5000) {
    // Escapar comillas para JavaScript
    $message = addslashes($message);
    echo "<script>
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                showAlert('$message', '$type', $duration);
            });
        } else {
            showAlert('$message', '$type', $duration);
        }
    </script>";
}

function swiftSuccess($message, $duration = 5000) {
    swiftAlert($message, 'success', $duration);
}

function swiftError($message, $duration = 5000) {
    swiftAlert($message, 'danger', $duration);
}

function swiftWarning($message, $duration = 5000) {
    swiftAlert($message, 'warning', $duration);
}

function swiftInfo($message, $duration = 5000) {
    swiftAlert($message, 'info', $duration);
}

/**
 * Generar confirmación Swift
 * @param string $message Mensaje de confirmación
 * @param string $onConfirm Función JS a ejecutar si confirma
 * @param string $onCancel Función JS a ejecutar si cancela
 */
function swiftConfirmDialog($message, $onConfirm = 'console.log("Confirmado")', $onCancel = null) {
    $message = addslashes($message);
    $onCancelCode = $onCancel ? $onCancel : 'null';
    echo "<script>
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                swiftConfirm('$message', function() { $onConfirm; }, function() { $onCancelCode; });
            });
        } else {
            swiftConfirm('$message', function() { $onConfirm; }, function() { $onCancelCode; });
        }
    </script>";
}
?>
