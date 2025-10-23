class ProductManager {
    constructor() {
        this.init();
    }

    init() {
        this.initProductSearch();
        this.initImportCalculator();
    }

    initProductSearch() {
        const searchInput = document.getElementById('productSearch');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(this.searchProducts.bind(this), 300));
        }
    }

    initImportCalculator() {
        const calculator = document.getElementById('importCalculator');
        if (calculator) {
            calculator.addEventListener('submit', this.calculateImportCost.bind(this));
        }
    }

    async searchProducts(e) {
        const query = e.target.value;
        if (query.length > 2) {
            // Aquí integrarías con APIs de Amazon/Alibaba
            console.log('Buscando:', query);
        }
    }

    calculateImportCost(e) {
        e.preventDefault();
        const precio = parseFloat(document.getElementById('precioProducto').value);
        const peso = parseFloat(document.getElementById('pesoProducto').value);
        
        const costo = precio * 1.15 + (peso * 2.5); // Impuesto 15% + flete
        document.getElementById('resultadoCosto').textContent = `Costo total: $${costo.toFixed(2)}`;
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}