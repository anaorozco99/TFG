function abrirModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('abierto');
}

function cerrarModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('abierto');
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('abierto');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.abierto')
            .forEach(m => m.classList.remove('abierto'));
    }
});
