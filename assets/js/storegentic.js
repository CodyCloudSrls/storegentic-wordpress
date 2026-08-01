/**
 * Storegentic sul negozio.
 *
 * JavaScript nativo, nessuna dipendenza: il plugin deve funzionare su temi
 * che non caricano jQuery e su temi che ne caricano tre versioni.
 *
 * Il browser non parla mai con Storegentic: parla con WordPress, che ha la
 * chiave. Vedi Frontend\Ponte.
 */
(function () {
  'use strict';

  var cfg = window.storegenticConfig;
  if (!cfg || !cfg.ponte) { return; }

  var pannello = document.getElementById('sg-pannello');
  var esiti = pannello ? pannello.querySelector('[data-sg-esiti]') : null;
  var campoPannello = pannello ? pannello.querySelector('[data-sg-campo]') : null;
  var ultimoFuoco = null;
  var richiestaInCorso = null;
  var attesa = null;

  /* Una sessione per visita: serve a legare fra loro gli eventi di analisi
     (ho cercato, ho visto i risultati, ho aperto un prodotto). Sta in
     sessionStorage, quindi muore con la scheda del browser e non e' un
     identificativo persistente. */
  var sessione = (function () {
    try {
      var s = sessionStorage.getItem('sg-sessione');
      if (!s) {
        s = 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        sessionStorage.setItem('sg-sessione', s);
      }
      return s;
    } catch (e) { return 'sess_effimera'; }
  }());

  /* ------------------------------------------------------------ rete */

  function chiama(rotta, corpo) {
    return fetch(cfg.ponte + rotta, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify(corpo)
    }).then(function (r) {
      if (!r.ok) { throw new Error('stato ' + r.status); }
      return r.json();
    });
  }

  function segnala(tipo, dati) {
    if (!cfg.analitica) { return; }
    // Non blocca nulla e non deve mai far fallire la ricerca.
    chiama('/evento', {
      eventType: tipo, sessionId: sessione, mode: 'agent_search', data: dati || {}
    }).catch(function () {});
  }

  /* --------------------------------------------------------- pannello */

  function apri() {
    if (!pannello) { return; }
    ultimoFuoco = document.activeElement;
    pannello.hidden = false;
    document.documentElement.classList.add('sg-bloccato');
    window.requestAnimationFrame(function () {
      if (campoPannello) { campoPannello.focus(); }
    });
  }

  function chiudi() {
    if (!pannello || pannello.hidden) { return; }
    pannello.hidden = true;
    document.documentElement.classList.remove('sg-bloccato');
    if (ultimoFuoco && document.contains(ultimoFuoco)) { ultimoFuoco.focus(); }
    ultimoFuoco = null;
  }

  /** Tiene il fuoco dentro il pannello finche' resta aperto. */
  function catturaFuoco(e) {
    if (e.key !== 'Tab' || !pannello || pannello.hidden) { return; }
    var f = pannello.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!f.length) { return; }
    var primo = f[0], ultimo = f[f.length - 1];
    if (e.shiftKey && document.activeElement === primo) { e.preventDefault(); ultimo.focus(); }
    else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primo.focus(); }
  }

  /* --------------------------------------------------------- ricerca */

  function cerca(query) {
    query = (query || '').trim();
    if (query.length < 2) { return; }

    apri();
    mostraStato(cfg.testi.inCorso);
    segnala('query_sent', { query: query });

    var mia = {};
    richiestaInCorso = mia;

    chiama('/ricerca', { query: query, topK: 12 })
      .then(function (d) {
        // Una risposta lenta non deve sovrascrivere una ricerca piu' recente.
        if (richiestaInCorso !== mia) { return; }
        disegna(d, query);
        segnala('results_returned', { query: query, resultsCount: (d.risultati || []).length });
      })
      .catch(function () {
        if (richiestaInCorso !== mia) { return; }
        mostraStato(cfg.testi.errore);
      });
  }

  function mostraStato(testo) {
    if (!esiti) { return; }
    esiti.innerHTML = '';
    var p = document.createElement('p');
    p.className = 'sg-stato';
    p.textContent = testo;
    esiti.appendChild(p);
  }

  function disegna(d, query) {
    if (!esiti) { return; }
    esiti.innerHTML = '';

    var risultati = d.risultati || [];

    if (!risultati.length) { mostraStato(cfg.testi.nessuno); return; }

    if ((d.categorie || []).length) {
      var nav = document.createElement('nav');
      nav.className = 'sg-categorie';
      nav.setAttribute('aria-label', cfg.testi.categorie);
      d.categorie.forEach(function (c) {
        if (!c.percorso) { return; }
        var s = document.createElement('span');
        s.className = 'sg-categorie__voce';
        s.textContent = c.percorso.split('/').pop().replace(/-/g, ' ');
        nav.appendChild(s);
      });
      if (nav.childNodes.length) { esiti.appendChild(nav); }
    }

    var ul = document.createElement('ul');
    ul.className = 'sg-esiti';

    risultati.forEach(function (r, i) {
      var li = document.createElement('li');
      li.className = 'sg-esito';

      var a = document.createElement('a');
      a.className = 'sg-esito__link';
      a.href = r.url || '#';
      a.addEventListener('click', function () {
        segnala('result_clicked', { query: query, sku: r.sku, position: i + 1 });
      });

      if (r.immagine) {
        var img = document.createElement('img');
        img.className = 'sg-esito__foto';
        img.src = r.immagine;
        img.alt = '';
        img.loading = 'lazy';
        img.decoding = 'async';
        a.appendChild(img);
      }

      var testo = document.createElement('span');
      testo.className = 'sg-esito__testo';

      if (r.marchio) {
        var m = document.createElement('span');
        m.className = 'sg-esito__marchio';
        m.textContent = r.marchio;
        testo.appendChild(m);
      }

      var n = document.createElement('span');
      n.className = 'sg-esito__nome';
      // textContent, non innerHTML: il nome arriva da fuori.
      n.textContent = r.nome || '';
      testo.appendChild(n);

      if (r.prezzo) {
        var p = document.createElement('span');
        p.className = 'sg-esito__prezzo';
        p.textContent = r.prezzo;
        testo.appendChild(p);
      }

      a.appendChild(testo);
      li.appendChild(a);
      ul.appendChild(li);
    });

    esiti.appendChild(ul);
  }

  /* ------------------------------------------------------- ascoltatori */

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-sg-barra]');
    if (!form) { return; }
    e.preventDefault();
    var campo = form.querySelector('[data-sg-campo]');
    cerca(campo ? campo.value : '');
  });

  /* Ricerca mentre si scrive, ma non a ogni tasto: si aspetta che chi
     scrive si fermi, altrimenti una parola di otto lettere costerebbe otto
     chiamate e otto punti di quota. */
  document.addEventListener('input', function (e) {
    var campo = e.target.closest('[data-sg-campo]');
    if (!campo) { return; }
    window.clearTimeout(attesa);
    var v = campo.value;
    attesa = window.setTimeout(function () { cerca(v); }, 350);
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-storegentic]')) { e.preventDefault(); apri(); return; }
    if (e.target.closest('[data-sg-chiudi]')) { e.preventDefault(); chiudi(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { chiudi(); return; }
    catturaFuoco(e);
  });
}());
