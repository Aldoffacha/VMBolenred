/**
 * Swift Alerts - Sistema de alertas deslizantes desde la derecha
 * Las alertas se cierran automáticamente después de 5 segundos
 */

class SwiftAlert {
    constructor(message, type = 'info', duration = 5000) {
        this.message = message;
        this.type = type; // 'success', 'danger', 'warning', 'info'
        this.duration = duration;
        this.element = null;
        this.show();
    }

    show() {
        // Crear contenedor de alertas si no existe
        let container = document.getElementById('swift-alerts-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'swift-alerts-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }

        // Crear elemento de alerta
        this.element = document.createElement('div');
        this.element.className = `swift-alert swift-alert-${this.type}`;
        this.element.style.cssText = `
            background: ${this.getBackgroundColor()};
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 300px;
            max-width: 400px;
            word-wrap: break-word;
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.4s ease-out;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        `;

        // Añadir icono
        const icon = document.createElement('i');
        icon.className = this.getIconClass();
        icon.style.cssText = `
            min-width: 20px;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        `;

        // Contenedor de texto
        const textContainer = document.createElement('div');
        textContainer.style.cssText = `
            flex: 1;
        `;
        textContainer.innerHTML = this.message;

        // Botón de cerrar
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '×';
        closeBtn.style.cssText = `
            background: transparent;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            margin-left: 8px;
            opacity: 0.7;
            transition: opacity 0.2s;
        `;
        closeBtn.onmouseover = () => { closeBtn.style.opacity = '1'; };
        closeBtn.onmouseout = () => { closeBtn.style.opacity = '0.7'; };
        closeBtn.onclick = () => this.hide();

        this.element.appendChild(icon);
        this.element.appendChild(textContainer);
        this.element.appendChild(closeBtn);

        container.appendChild(this.element);

        // Auto-cerrar después del duration
        this.timeout = setTimeout(() => this.hide(), this.duration);

        // Pausar auto-cierre si el usuario pasa el ratón encima
        this.element.onmouseenter = () => clearTimeout(this.timeout);
        this.element.onmouseleave = () => {
            this.timeout = setTimeout(() => this.hide(), this.duration);
        };
    }

    getBackgroundColor() {
        const colors = {
            'success': '#10b981',
            'danger': '#ef4444',
            'warning': '#f59e0b',
            'info': '#3b82f6'
        };
        return colors[this.type] || colors['info'];
    }

    getIconClass() {
        const icons = {
            'success': 'fas fa-check-circle',
            'danger': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        };
        return icons[this.type] || icons['info'];
    }

    hide() {
        if (!this.element) return;
        
        this.element.style.animation = 'slideOutRight 0.4s ease-in';
        
        setTimeout(() => {
            if (this.element && this.element.parentNode) {
                this.element.parentNode.removeChild(this.element);
            }
        }, 400);

        clearTimeout(this.timeout);
    }
}

// Funciones de helper globales
window.showAlert = function(message, type = 'info', duration = 5000) {
    return new SwiftAlert(message, type, duration);
};

window.showSuccess = function(message, duration = 5000) {
    return new SwiftAlert(message, 'success', duration);
};

window.showError = function(message, duration = 5000) {
    return new SwiftAlert(message, 'danger', duration);
};

window.showWarning = function(message, duration = 5000) {
    return new SwiftAlert(message, 'warning', duration);
};

window.showInfo = function(message, duration = 5000) {
    return new SwiftAlert(message, 'info', duration);
};

// Confirmación con Swift Alert
window.swiftConfirm = function(message, onConfirm, onCancel = null) {
    const container = document.getElementById('swift-alerts-container') || document.createElement('div');
    if (!document.getElementById('swift-alerts-container')) {
        container.id = 'swift-alerts-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }

    const confirmElement = document.createElement('div');
    confirmElement.style.cssText = `
        background: white;
        color: #333;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        min-width: 320px;
        max-width: 420px;
        pointer-events: auto;
        animation: slideInRight 0.4s ease-out;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    `;

    confirmElement.innerHTML = `
        <div style="margin-bottom: 16px; font-size: 14px; line-height: 1.5;">
            ${message}
        </div>
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <button id="confirm-cancel" style="
                padding: 8px 16px;
                background: #f3f4f6;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 500;
                color: #374151;
                transition: background 0.2s;
            ">Cancelar</button>
            <button id="confirm-ok" style="
                padding: 8px 16px;
                background: #3b82f6;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 500;
                color: white;
                transition: background 0.2s;
            ">Confirmar</button>
        </div>
    `;

    container.appendChild(confirmElement);

    const removeConfirm = () => {
        confirmElement.style.animation = 'slideOutRight 0.4s ease-in';
        setTimeout(() => {
            if (confirmElement.parentNode) {
                confirmElement.parentNode.removeChild(confirmElement);
            }
        }, 400);
    };

    document.getElementById('confirm-ok').onclick = () => {
        removeConfirm();
        if (onConfirm) onConfirm();
    };

    document.getElementById('confirm-cancel').onclick = () => {
        removeConfirm();
        if (onCancel) onCancel();
    };

    // Hover effects
    document.getElementById('confirm-ok').onmouseover = function() {
        this.style.background = '#2563eb';
    };
    document.getElementById('confirm-ok').onmouseout = function() {
        this.style.background = '#3b82f6';
    };

    document.getElementById('confirm-cancel').onmouseover = function() {
        this.style.background = '#e5e7eb';
    };
    document.getElementById('confirm-cancel').onmouseout = function() {
        this.style.background = '#f3f4f6';
    };
};

// Inyectar estilos de animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }

    .swift-alert {
        animation-duration: 0.4s;
    }
`;
document.head.appendChild(style);
