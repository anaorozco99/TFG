// app.js — funciones JavaScript compartidas por todas las páginas

// Abre un modal por su ID (añade la clase 'abierto')
function abrirModal(id) { document.getElementById(id)?.classList.add('abierto'); }
// Cierra un modal por su ID
function cerrarModal(id) { document.getElementById(id)?.classList.remove('abierto'); }

// Cerrar modal si se hace clic en el fondo oscuro
document.addEventListener('click', e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('abierto'); });
// Cerrar modal con la tecla Escape
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.abierto').forEach(m=>m.classList.remove('abierto')); });

// Toggle del sidebar en móvil
function abrirSidebar() {
    document.querySelector('.sidebar')?.classList.add('abierto');
    document.getElementById('sidebar-overlay')?.classList.add('activo');
}
function cerrarSidebar() {
    document.querySelector('.sidebar')?.classList.remove('abierto');
    document.getElementById('sidebar-overlay')?.classList.remove('activo');
}

// confirm personalizado (reemplaza el alert nativo del navegador)
var _confirmCb = null;

function confirmar(msg, cb, tipo) {
    var btn = document.getElementById('confirm-si');
    if (!btn) { if (cb) cb(); return; } // fallback si no hay modal
    document.getElementById('confirm-msg').textContent = msg;
    btn.className = 'btn btn-' + (tipo || 'rojo');
    _confirmCb = cb;
    abrirModal('modal-confirm');
}

function ejecutarConfirm() {
    cerrarModal('modal-confirm');
    if (_confirmCb) { var fn = _confirmCb; _confirmCb = null; fn(); }
}

// para confirmar un link (GET)
function confirmarLink(msg, url, tipo) {
    confirmar(msg, function() { window.location.href = url; }, tipo);
}
