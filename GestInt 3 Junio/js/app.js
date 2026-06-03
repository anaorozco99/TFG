function abrirModal(id) { document.getElementById(id)?.classList.add('abierto'); }
function cerrarModal(id) { document.getElementById(id)?.classList.remove('abierto'); }
document.addEventListener('click', e => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('abierto'); });
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.abierto').forEach(m=>m.classList.remove('abierto')); });
