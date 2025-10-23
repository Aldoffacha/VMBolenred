// ==============================
// Funciones específicas del ADMIN Dashboard
// ==============================
class AdminDashboard {
    constructor() {
        this.initCharts();
        this.initStats();
    }

    initCharts() {
        // Gráfico de ventas mensuales
        const salesCtx = document.getElementById('salesChart');
        if (salesCtx) {
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Ventas ($)',
                        data: [12000, 19000, 15000, 25000, 22000, 30000],
                        borderColor: '#9a031e', // rojo vino
                        tension: 0.1
                    }]
                }
            });
        }

        // Gráfico de productos más vendidos
        const productsCtx = document.getElementById('productsChart');
        if (productsCtx) {
            new Chart(productsCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Electrónicos', 'Ropa', 'Hogar', 'Deportes'],
                    datasets: [{
                        data: [40, 25, 20, 15],
                        backgroundColor: ['#9a031e', '#2c7be5', '#00d97e', '#f6c343']
                    }]
                }
            });
        }
    }

    initStats() {
        // Contadores animados
        const counters = document.querySelectorAll('.counter');
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'));
            const increment = target / 100;
            let current = 0;

            const updateCounter = () => {
                if (current < target) {
                    current += increment;
                    counter.textContent = Math.ceil(current).toLocaleString();
                    setTimeout(updateCounter, 20);
                } else {
                    counter.textContent = target.toLocaleString();
                }
            };

            updateCounter();
        });
    }
}

// ==============================
// Funciones específicas del CLIENTE
// ==============================
class ClienteDashboard {
    constructor() {
        console.log("Cliente Dashboard listo.");
        // Aquí iría la lógica del cliente si la necesitas
    }
}

// ==============================
// Funciones específicas del EMPLEADO
// ==============================
class EmpleadoDashboard {
    constructor() {
        console.log("Empleado Dashboard listo.");
        // Aquí iría la lógica del empleado si la necesitas
    }
}

// ==============================
// Funciones específicas del LOGIN
// ==============================
class LoginPage {
    constructor() {
        console.log("Login Page lista.");
        // Aquí iría la lógica del login si la necesitas
    }
}

// ==============================
// Inicializador Global
// ==============================
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.admin-dashboard')) {
        window.adminDashboard = new AdminDashboard();
    }
    if (document.querySelector('.product-grid')) {
        window.clienteDashboard = new ClienteDashboard();
    }
    if (document.querySelector('.empleado-dashboard')) {
        window.empleadoDashboard = new EmpleadoDashboard();
    }
    if (document.querySelector('.login-page')) {
        window.loginPage = new LoginPage();
    }
});
