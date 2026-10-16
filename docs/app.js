(() => {
  const input = document.getElementById('number');
  const button = document.getElementById('search');
  const feedback = document.getElementById('feedback');
  const result = document.getElementById('result');

  function searchSample() {
    const value = input.value.trim();
    result.hidden = true;
    feedback.className = 'feedback';

    if (!/^\d{10}$/.test(value)) {
      feedback.textContent = 'Escribe exactamente 10 dígitos para hacer la búsqueda de ejemplo.';
      feedback.classList.add('error');
      input.focus();
      return;
    }

    if (value >= '0000000000' && value <= '0000000999') {
      feedback.textContent = 'Coincidencia encontrada en el conjunto ficticio.';
      feedback.classList.add('success');
      result.hidden = false;
      return;
    }

    feedback.textContent = 'No hay coincidencias: la demo solo contiene el rango ficticio 0000000000–0000000999.';
    feedback.classList.add('muted');
  }

  button.addEventListener('click', searchSample);
  input.addEventListener('keydown', event => {
    if (event.key === 'Enter') searchSample();
  });
})();
