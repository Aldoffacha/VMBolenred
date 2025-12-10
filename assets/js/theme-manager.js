// theme-manager.js - Gestor de temas persistente
class ThemeManager {
    constructor() {
        this.themeKey = 'vmbolenred_theme'; // Consistente con el script del head
        this.init();
    }

    init() {
        // Cargar tema guardado o usar modo oscuro por defecto
        const savedTheme = this.getSavedTheme();
        this.applyTheme(savedTheme);
        
        // Crear botón de cambio de tema
        this.createThemeSwitch();
    }

    getSavedTheme() {
        return localStorage.getItem(this.themeKey) || 'dark'; // Por defecto oscuro
    }

    saveTheme(theme) {
        localStorage.setItem(this.themeKey, theme);
    }

    applyTheme(theme) {
        const body = document.body;
        const html = document.documentElement;
        
        // Limpiar ambas clases primero
        body.classList.remove('modo-claro', 'modo-oscuro');
        html.removeAttribute('data-theme');
        
        // Aplicar la correcta
        if (theme === 'light') {
            body.classList.add('modo-claro');
            html.setAttribute('data-theme', 'light');
        } else {
            body.classList.add('modo-oscuro');
            html.setAttribute('data-theme', 'dark');
        }
    }

    toggleTheme() {
        const currentTheme = this.getSavedTheme();
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        this.saveTheme(newTheme);
        this.applyTheme(newTheme);
        this.updateThemeButton(newTheme);
    }

    updateThemeButton(theme) {
        const themeBtn = document.querySelector('.theme-switch-btn');
        if (themeBtn) {
            if (theme === 'light') {
                themeBtn.innerHTML = '🌙';
                themeBtn.title = 'Cambiar a modo oscuro';
            } else {
                themeBtn.innerHTML = '☀️';
                themeBtn.title = 'Cambiar a modo claro';
            }
        }
    }

    createThemeSwitch() {
        // Evitar duplicados
        if (document.querySelector('.theme-switch')) return;

        const themeSwitch = document.createElement('div');
        themeSwitch.className = 'theme-switch';
        
        const currentTheme = this.getSavedTheme();
        const buttonIcon = currentTheme === 'light' ? '🌙' : '☀️';
        const buttonTitle = currentTheme === 'light' ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro';

        themeSwitch.innerHTML = `
            <button class="theme-switch-btn" onclick="themeManager.toggleTheme()" title="${buttonTitle}">
                ${buttonIcon}
            </button>
        `;

        document.body.appendChild(themeSwitch);
    }
}

// Inicializar el gestor de temas
const themeManager = new ThemeManager();