/**
 * L'anteprima dei colori.
 *
 * Aggiorna un fac-simile dell'assistente mentre si sceglie, senza ricaricare.
 * Non manda niente da nessuna parte: legge i campi e scrive delle variabili
 * CSS su un contenitore.
 */
(function () {
  'use strict';

  var C = window.storegenticAdmin || {};

  var radice = document.querySelector('[data-sg-anteprima]');
  if (!radice) { return; }

  var propria = document.querySelector('[data-sg-propria]');

  function scelta() {
    var s = document.querySelector('input[name="palette"]:checked');
    return s ? s.value : 'tema';
  }

  function colori() {
    var nome = scelta();

    /*
     * Con "personalizzata" contano i selettori; con tutte le altre contano i
     * colori della combinazione, che il PHP ha gia' passato qui. "Dal tema"
     * non ha colori propri: si mostra il neutro, che e' cio' che si vede
     * quando il tema non dichiara nulla.
     */
    if (nome === 'propria') {
      var scelti = {};
      Array.prototype.forEach.call(document.querySelectorAll('[data-sg-colore]'), function (campo) {
        scelti[campo.getAttribute('data-sg-colore')] = campo.value;
      });
      return scelti;
    }

    return (C.preparate && C.preparate[nome]) || (C.preparate && C.preparate.neutro) || {};
  }

  function aggiorna() {
    var c = colori();
    var raggio = parseInt((document.querySelector('[data-sg-raggio]') || {}).value, 10);
    if (isNaN(raggio)) { raggio = 10; }

    radice.style.setProperty('--ap-sfondo', c.sfondo || '');
    radice.style.setProperty('--ap-superficie', c.superficie || '');
    radice.style.setProperty('--ap-testo', c.testo || '');
    radice.style.setProperty('--ap-testo-tenue', c.testo_tenue || '');
    radice.style.setProperty('--ap-bordo', c.bordo || '');
    radice.style.setProperty('--ap-accento', c.accento || '');
    radice.style.setProperty('--ap-su-accento', c.su_accento || '');
    radice.style.setProperty('--ap-raggio', raggio + 'px');
    radice.style.setProperty('--ap-raggio-s', Math.max(2, Math.round(raggio * 0.6)) + 'px');
    radice.style.setProperty('--ap-raggio-l', (raggio + 6) + 'px');

    if (propria) { propria.hidden = scelta() !== 'propria'; }
  }

  document.addEventListener('change', function (e) {
    if (e.target.closest('input[name="palette"], [data-sg-colore], [data-sg-raggio]')) { aggiorna(); }
  });

  document.addEventListener('input', function (e) {
    if (e.target.closest('[data-sg-colore], [data-sg-raggio]')) { aggiorna(); }
  });

  aggiorna();
}());
