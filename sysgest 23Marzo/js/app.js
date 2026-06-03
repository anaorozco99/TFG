// ============================================================
// SysGest — JavaScript mínimo
// Solo gestión de modales y pequeñas utilidades
// ============================================================

/** Abre un modal por su id */
function abrirModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('abierto');
}

/** Cierra un modal por su id */
function cerrarModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('abierto');
}

// Cerrar modal al pulsar fuera del cuadro
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('abierto');
    }
});

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.abierto').forEach(function(m) {
            m.classList.remove('abierto');
        });
    }
});

// Auto-ocultar alertas tras 5 segundos
document.addEventListener('DOMContentLoaded', function() {
    var alertas = document.querySelectorAll('.alerta');
    alertas.forEach(function(a) {
        setTimeout(function() {
            a.style.transition = 'opacity .5s';
            a.style.opacity = '0';
            setTimeout(function() { a.remove(); }, 500);
        }, 5000);
    });
});
