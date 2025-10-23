// Funciones generales de la aplicación
class VMBolApp {
    constructor() {
        this.init();
    }

    init() {
        this.initSidebar();
        this.initNotifications();
        this.initForms();
    }

    // Sidebar toggle para móviles
    initSidebar() {
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }
    }

    // Sistema de notificaciones
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.notification-container');
        if (container) {
            container.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    }

    // Manejo de formularios
    initForms() {
        const forms = document.querySelectorAll('form[data-ajax="true"]');
        forms.forEach(form => {
            form.addEventListener('submit', this.handleAjaxForm.bind(this));
        });
    }

    async handleAjaxForm(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(result.message, 'success');
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000);
                }
            } else {
                this.showNotification(result.message, 'danger');
            }
        } catch (error) {
            this.showNotification('Error de conexión', 'danger');
        }
    }

    // Cálculo de costos de importación
    calcularCostoImportacion(precio, peso, tipo) {
        const impuesto = 0.15;
        const flete = Math.max(10, peso * 2.5);
        return precio + (precio * impuesto) + flete;
    }
}

// Inicializar la aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.vmbolApp = new VMBolApp();
});