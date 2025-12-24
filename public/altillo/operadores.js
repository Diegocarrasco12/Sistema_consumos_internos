document.addEventListener('DOMContentLoaded', () => {

  const input = document.getElementById('operador');
  const box   = document.getElementById('op-sugerencias');

  if (!input || !box) {
    console.error('❌ Elementos de operador no encontrados');
    return;
  }

  let operadores = [];

  // 1️⃣ Cargar operadores desde API
  fetch('/CONSUMO_PAPEL/public/altillo/api/get_operadores.php')
    .then(res => {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.json();
    })
    .then(data => {
      operadores = data.map(o => o.nombre);
    })
    .catch(err => {
      console.error('❌ Error cargando operadores:', err);
    });

  // 2️⃣ Escuchar escritura
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    box.innerHTML = '';

    if (q.length === 0) {
      box.style.display = 'none';
      return;
    }

    const matches = operadores.filter(nombre =>
      nombre.toLowerCase().includes(q)
    );

    if (matches.length === 0) {
      box.style.display = 'none';
      return;
    }

    matches.forEach(nombre => {
      const div = document.createElement('div');
      div.className = 'op-item';
      div.textContent = nombre;

      div.addEventListener('click', () => {
        input.value = nombre;
        box.style.display = 'none';
      });

      box.appendChild(div);
    });

    box.style.display = 'block';
  });

  // 3️⃣ Cerrar dropdown al hacer click fuera
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.op-wrap')) {
      box.style.display = 'none';
    }
  });

});
