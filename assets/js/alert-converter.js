/**
 * Convertir alertas Bootstrap antiguas a Swift Alerts
 * Se ejecuta automáticamente al cargar la página
 */

document.addEventListener('DOMContentLoaded', function() {
    // Buscar todas las alertas Bootstrap antiguas
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Obtener el tipo de alerta
        let type = 'info';
        if (alert.classList.contains('alert-success')) type = 'success';
        else if (alert.classList.contains('alert-danger')) type = 'danger';
        else if (alert.classList.contains('alert-warning')) type = 'warning';
        else if (alert.classList.contains('alert-info')) type = 'info';
        
        // Obtener el mensaje (solo el texto, sin el botón close)
        const message = alert.textContent.replace('×', '').trim();
        
        // Mostrar Swift Alert
        new SwiftAlert(message, type, 5000);
        
        // Remover la alerta antigua del DOM
        alert.style.display = 'none';
    });
});
