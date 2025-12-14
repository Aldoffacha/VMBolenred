-- Crear tabla productos_exterior para productos destacados de Amazon y eBay
CREATE TABLE IF NOT EXISTS productos_exterior (
    id_producto_exterior SERIAL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio NUMERIC(10,2) NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    plataforma VARCHAR(20) NOT NULL CHECK (plataforma IN ('amazon', 'ebay')),
    enlace TEXT NOT NULL,
    imagen TEXT,
    peso NUMERIC(5,2) DEFAULT 0.50,
    estado INTEGER DEFAULT 1,
    destacado INTEGER DEFAULT 1,
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT check_plataforma CHECK (plataforma IN ('amazon', 'ebay'))
);

-- Crear índices para búsquedas rápidas
CREATE INDEX idx_productos_exterior_plataforma ON productos_exterior(plataforma);
CREATE INDEX idx_productos_exterior_categoria ON productos_exterior(categoria);
CREATE INDEX idx_productos_exterior_estado ON productos_exterior(estado);
CREATE INDEX idx_productos_exterior_destacado ON productos_exterior(destacado);

-- Insertar algunos productos de ejemplo
INSERT INTO productos_exterior (nombre, descripcion, precio, categoria, plataforma, enlace, imagen, peso) VALUES
('Razer DeathAdder Essential - Mouse Gaming', 'Mouse gaming Razer con sensor óptico de 6400 DPI, 5 botones programables y diseño ergonómico para diestros.', 29.99, 'electronico', 'amazon', 'https://amazon.com/dp/B07QSCM51V', 'https://images.unsplash.com/photo-1527814050087-3793815479db?w=400&h=300&fit=crop', 0.3),
('Sony WH-1000XM4 - Audífonos Inalámbricos', 'Audífonos noise canceling con sonido de alta resolución, 30 horas de batería y asistente de voz integrado.', 348.00, 'electronico', 'amazon', 'https://amazon.com/dp/B0863TXGM3', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&h=300&fit=crop', 0.5),
('Logitech G Pro X - Headset Gaming', 'Headset gaming con sonido surround 7.1, micrófono desmontable Blue Voice y memoria integrada para perfiles.', 89.99, 'electronico', 'ebay', 'https://ebay.com/itm/Logitech-G-PRO-X-Gaming-Headset', 'https://images.unsplash.com/photo-1599669454699-248893623440?w=400&h=300&fit=crop', 0.4),
('SteelSeries Apex Pro - Teclado Mecánico', 'Teclado gaming mecánico con switches ajustables OmniPoint, iluminación RGB y reposamuñecas magnético.', 179.99, 'electronico', 'ebay', 'https://ebay.com/itm/SteelSeries-Apex-Pro-TKL-Gaming-Keyboard', 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=400&h=300&fit=crop', 0.8);
