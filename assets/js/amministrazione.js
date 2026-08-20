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

  /*
   * IL CONTRASTO, MISURATO MENTRE SI SCEGLIE.
   *
   * Un contrasto non si vede a occhio: si misura. Lo dimostra il fatto che la
   * combinazione "Inchiostro e oro" e' rimasta sotto la soglia per settimane
   * senza che nessuno se ne accorgesse guardandola.
   *
   * Questa e' una copia in JavaScript della formula che sta in
   * Frontend\Palette::contrasto(). Serve perche' l'anteprima cambia a ogni
   * tocco del selettore e non puo' chiedere al server ogni volta; la
   * definizione buona resta quella in PHP, che e' anche quella che parla al
   * salvataggio e quella che il collaudo difende. Se una delle due cambia,
   * cambiano tutte e due.
   */
  function luce(esadecimale) {
    var h = String(esadecimale || '').replace('#', '');
    if (h.length === 3) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
    if (!/^[0-9a-f]{6}$/i.test(h)) { return null; }

    var canali = [0, 2, 4].map(function (posto) {
      var v = parseInt(h.substr(posto, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });

    return 0.2126 * canali[0] + 0.7152 * canali[1] + 0.0722 * canali[2];
  }

  function contrasto(primo, secondo) {
    var a = luce(primo);
    var b = luce(secondo);
    if (a === null || b === null) { return null; }
    return Math.round(((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)) * 100) / 100;
  }

  /* Gli stessi accostamenti di Palette::ACCOSTAMENTI, nello stesso ordine. */
  var ACCOSTAMENTI = [
    ['testo', 'superficie', 'Testo sulle schede'],
    ['testo', 'sfondo', 'Testo sul fondo'],
    ['testo_tenue', 'superficie', 'Note e categorie'],
    ['su_accento', 'accento', 'Testo nei pulsanti']
  ];

  var verdetto = document.querySelector('[data-sg-contrasto]');

  function misura(c) {
    if (!verdetto) { return; }

    verdetto.textContent = '';

    ACCOSTAMENTI.forEach(function (coppia) {
      var r = contrasto(c[coppia[0]], c[coppia[1]]);
      if (r === null) { return; }

      var voce = document.createElement('span');
      voce.className = 'sg-contrasto__voce' + (r < 4.5 ? ' sg-contrasto__voce--male' : '');
      voce.textContent = coppia[2] + ' ' + r.toFixed(1).replace('.', ',') + ':1';
      voce.title = r < 4.5
        ? 'Sotto il 4,5:1 richiesto: questo testo non si legge bene.'
        : 'Sopra il 4,5:1 richiesto.';
      verdetto.appendChild(voce);
    });
  }

  function aggiorna() {
    var c = colori();
    var raggio = parseInt((document.querySelector('[data-sg-raggio]') || {}).value, 10);
    if (isNaN(raggio)) { raggio = 10; }

    misura(c);

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
