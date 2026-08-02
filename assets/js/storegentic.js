/**
 * Storegentic sul negozio.
 *
 * JavaScript nativo, nessuna dipendenza, nessun passaggio di compilazione: il
 * plugin si installa da uno zip e questo file e' quello che gira. Deve
 * funzionare su temi che non caricano jQuery e su temi che ne caricano tre
 * versioni.
 *
 * TRE COMPONENTI, UNA REGOLA. Il pannello di ricerca, la pagina dei risultati
 * e l'assistente condividono il trasporto e il modo di disegnare un risultato.
 * Il markup delle schede NON si costruisce qui: arriva gia' fatto dal server
 * (vedi Frontend\Scheda). Cosi' esiste una sola definizione di com'e' fatto un
 * risultato, invece di due che divergono al primo ritocco.
 *
 * TUTTO DEGRADA. Senza JavaScript la barra resta un modulo GET che porta alla
 * pagina dei risultati, i risultati sono gia' scritti nel documento, e i
 * filtri semplicemente non compaiono. Nessuna funzione essenziale dipende da
 * questo file.
 *
 * Il browser non parla mai con Storegentic: parla con WordPress, che ha la
 * chiave. Vedi Frontend\Ponte.
 */
(function () {
  'use strict';

  var C = window.storegenticConfig;
  if (!C || !C.ponte) { return; }

  var T = C.testi || {};

  /* ===================================================== utili di base */

  function $(selettore, dove) { return (dove || document).querySelector(selettore); }

  function $$(selettore, dove) {
    return Array.prototype.slice.call((dove || document).querySelectorAll(selettore));
  }

  function elemento(tag, classe, testo) {
    var e = document.createElement(tag);
    if (classe) { e.className = classe; }
    if (testo !== undefined) { e.textContent = testo; }
    return e;
  }

  /**
   * Il primo figlio di un frammento di HTML che arriva dal server.
   *
   * Il markup e' gia' passato per l'escape di WordPress: qui si trasforma
   * soltanto in nodi. Non si concatenano mai stringhe con dati.
   */
  function daHtml(html) {
    var contenitore = document.createElement('div');
    contenitore.innerHTML = html;
    return contenitore.firstElementChild;
  }

  /** Una chiamata al ponte. Il messaggio d'errore e' quello del server. */
  function chiama(rotta, corpo, segnale) {
    return fetch(C.ponte + rotta, {
      method: 'POST',
      credentials: 'same-origin',
      signal: segnale,
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
      body: JSON.stringify(corpo)
    }).then(function (r) {
      return r.json().then(function (dati) {
        if (!r.ok) { throw new Error((dati && dati.message) || T.errore); }
        return dati;
      });
    });
  }

  /** L'analisi non e' mai un motivo per far fallire una ricerca. */
  function segnala(tipo, dati) {
    if (!C.analitica) { return; }
    try {
      chiama('/evento', { eventType: tipo, sessionId: sessione(), data: dati || {} })['catch'](function () {});
    } catch (e) { /* si tace */ }
  }

  var _sessione = null;

  /*
   * Una sessione per visita: lega fra loro gli eventi di analisi (ho cercato,
   * ho aperto un prodotto). Sta in sessionStorage, quindi muore con la scheda
   * del browser e non e' un identificativo persistente.
   */
  function sessione() {
    if (_sessione) { return _sessione; }
    try {
      _sessione = window.sessionStorage.getItem('sg_sessione');
      if (!_sessione) {
        _sessione = 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        window.sessionStorage.setItem('sg_sessione', _sessione);
      }
    } catch (e) { _sessione = 'sess_effimera'; }
    return _sessione;
  }

  /** Lo storico delle ricerche resta nel browser di chi cerca, e basta. */
  var Storico = {
    CHIAVE: 'sg_recenti',
    QUANTE: 6,

    leggi: function () {
      try {
        var e = JSON.parse(window.localStorage.getItem(this.CHIAVE) || '[]');
        return Array.isArray(e) ? e.filter(function (v) { return typeof v === 'string'; }) : [];
      } catch (e) { return []; }
    },

    aggiungi: function (domanda) {
      domanda = (domanda || '').trim();
      if (domanda.length < 2) { return; }
      try {
        var elenco = this.leggi().filter(function (d) { return d.toLowerCase() !== domanda.toLowerCase(); });
        elenco.unshift(domanda);
        window.localStorage.setItem(this.CHIAVE, JSON.stringify(elenco.slice(0, this.QUANTE)));
      } catch (e) { /* navigazione privata: si rinuncia allo storico */ }
    },

    svuota: function () {
      try { window.localStorage.removeItem(this.CHIAVE); } catch (e) {}
    }
  };

  function indirizzoRicerca(domanda) {
    return C.pagina + (domanda ? '?q=' + encodeURIComponent(domanda) : '');
  }

  /* ================================================= foto: preparazione
   *
   * Una foto scattata con un telefono pesa fra i tre e i dodici megabyte, e in
   * base64 cresce di un terzo. Spedirla intera vorrebbe dire far aspettare
   * quindici secondi in 4G per una ricerca che poi dura due.
   *
   * Si rimpicciolisce nel browser: il lato lungo scende a 1024 pixel, che e'
   * piu' di quanto serva a un confronto visivo, e si ricomprime in JPEG. Da
   * otto megabyte si arriva sotto i duecento kilobyte.
   */
  function preparaFoto(file) {
    return new Promise(function (risolvi, rifiuta) {
      if (!file || !/^image\//.test(file.type)) { rifiuta(new Error(T.fotoErrore)); return; }

      var lettore = new FileReader();
      lettore.onerror = function () { rifiuta(new Error(T.fotoErrore)); };

      lettore.onload = function () {
        var img = new Image();

        /*
         * Un iPhone puo' consegnare un HEIC. Safari lo decodifica, gli altri
         * no: la si prende come "foto illeggibile" invece di spedire al
         * servizio qualcosa che non e' un'immagine.
         */
        img.onerror = function () { rifiuta(new Error(T.fotoErrore)); };

        img.onload = function () {
          var lato = C.fotoLato || 1024;
          var scala = Math.min(1, lato / Math.max(img.width, img.height));
          var l = Math.max(1, Math.round(img.width * scala));
          var a = Math.max(1, Math.round(img.height * scala));

          var tela = document.createElement('canvas');
          tela.width = l;
          tela.height = a;
          tela.getContext('2d').drawImage(img, 0, 0, l, a);

          var dati;
          try { dati = tela.toDataURL('image/jpeg', 0.82); }
          catch (e) { rifiuta(new Error(T.fotoErrore)); return; }

          risolvi({ base64: dati.split(',')[1], mime: 'image/jpeg', anteprima: dati });
        };

        img.src = lettore.result;
      };

      lettore.readAsDataURL(file);
    });
  }

  /** Cerca a partire da una foto, con gli agganci di chi ha chiamato. */
  function cercaConFoto(file, opzioni) {
    opzioni.stato(T.fotoInCorso, 'sg-stato--attesa');

    preparaFoto(file)
      .then(function (pronta) {
        if (opzioni.anteprima) { opzioni.anteprima(pronta.anteprima); }

        return chiama('/immagine', {
          foto: pronta.base64,
          mime: pronta.mime,
          query: opzioni.query || '',
          topK: opzioni.quanti || 24,
          forma: opzioni.forma || 'griglia'
        });
      })
      .then(function (dati) {
        segnala('image_search', { results: (dati.risultati || []).length });
        opzioni.esito(dati);
      })
      ['catch'](function (e) {
        opzioni.stato((e && e.message) || T.fotoErrore, 'sg-stato--male');
      });
  }

  /* ================================================ pannello di ricerca */

  var Pannello = {
    attesa: null,
    annulla: null,
    ultima: '',
    tornaA: null,

    foglio: function () { return $('#sg-pannello'); },
    esiti: function () { return $('[data-sg-esiti]'); },
    campo: function () { return $('#sg-campo-pannello'); },
    aperto: function () { var p = this.foglio(); return !!p && !p.hidden; },

    apri: function (precompila) {
      var p = this.foglio();
      if (!p) { return; }

      this.tornaA = document.activeElement;
      p.hidden = false;
      document.body.classList.add('sg-bloccato');

      var campo = this.campo();

      if (campo) {
        if (precompila) { campo.value = precompila; }
        window.requestAnimationFrame(function () { campo.focus(); campo.select(); });
      }

      if (campo && campo.value.trim().length >= 2) { this.suggerisci(campo.value); }
      else { this.iniziale(); }
    },

    chiudi: function () {
      var p = this.foglio();
      if (!p || p.hidden) { return; }

      p.hidden = true;
      document.body.classList.remove('sg-bloccato');

      if (this.annulla) { this.annulla.abort(); this.annulla = null; }
      if (this.tornaA && document.contains(this.tornaA)) { this.tornaA.focus(); }
      this.tornaA = null;
    },

    /**
     * Lo stato d'apertura non e' mai vuoto.
     *
     * Un pannello che si apre su un rettangolo bianco lascia chi lo guarda a
     * chiedersi che cosa ci si possa scrivere. Qui ci sono le sue ultime
     * ricerche, se ne ha, e degli esempi: dicono con quale linguaggio conviene
     * parlare a questa ricerca, che capisce le frasi e non solo le parole.
     */
    iniziale: function () {
      var esiti = this.esiti();
      if (!esiti) { return; }

      esiti.textContent = '';

      var recenti = Storico.leggi();
      if (recenti.length) { esiti.appendChild(this.gruppo(T.recenti, recenti, true)); }

      var esempi = (C.esempi || []).slice(0, 3);
      if (esempi.length) { esiti.appendChild(this.gruppo(T.suggeriti, esempi, false)); }
    },

    gruppo: function (titolo, voci, conCestino) {
      var self = this;
      var sezione = elemento('section', 'sg-gruppo');

      sezione.appendChild(elemento('h2', 'sg-gruppo__titolo', titolo));

      var lista = elemento('ul', 'sg-suggerimenti');

      voci.forEach(function (v) {
        var li = elemento('li');
        var a = elemento('a', 'sg-suggerimento', v);
        a.href = indirizzoRicerca(v);
        li.appendChild(a);
        lista.appendChild(li);
      });

      sezione.appendChild(lista);

      if (conCestino) {
        var via = elemento('button', 'sg-gruppo__via', T.pulisci);
        via.type = 'button';
        via.addEventListener('click', function () { Storico.svuota(); self.iniziale(); });
        sezione.appendChild(via);
      }

      return sezione;
    },

    stato: function (testo, classe) {
      var esiti = this.esiti();
      if (!esiti) { return; }

      esiti.textContent = '';
      esiti.appendChild(elemento('p', 'sg-stato ' + (classe || ''), testo));
    },

    /**
     * I suggerimenti mentre si scrive.
     *
     * Non e' la ricerca semantica: quella costa otto secondi e parte quando si
     * preme Invio. Qui rispondono i nomi dei prodotti e delle categorie del
     * negozio, che si trovano in pochi millisecondi. Vedi Frontend\Suggerimenti.
     */
    suggerisci: function (testo) {
      var self = this;
      testo = (testo || '').trim();

      window.clearTimeout(this.attesa);

      if (testo.length < 2) {
        this.ultima = '';
        this.iniziale();
        return;
      }

      if (testo === this.ultima) { return; }

      this.attesa = window.setTimeout(function () {
        self.ultima = testo;

        /*
         * Le risposte non tornano nell'ordine in cui partono: senza annullare
         * la precedente, quella di due lettere fa puo' arrivare per ultima e
         * sovrascrivere i suggerimenti giusti.
         */
        if (self.annulla) { self.annulla.abort(); }
        self.annulla = new AbortController();

        fetch(C.ponte + '/suggerimenti?q=' + encodeURIComponent(testo), {
          credentials: 'same-origin',
          signal: self.annulla.signal,
          headers: { 'X-WP-Nonce': C.nonce }
        })
          .then(function (r) { return r.json(); })
          .then(function (dati) {
            self.annulla = null;
            if (self.campo() && self.campo().value.trim() !== testo) { return; }
            self.mostraSuggerimenti(testo, (dati && dati.voci) || []);
          })
          ['catch'](function (e) {
            if (e && e.name === 'AbortError') { return; }
            self.annulla = null;
            // Restano comunque la riga "cerca" e lo storico: non si blocca niente.
            self.mostraSuggerimenti(testo, []);
          });
      }, 160);
    },

    mostraSuggerimenti: function (testo, voci) {
      var esiti = this.esiti();
      if (!esiti) { return; }

      esiti.textContent = '';

      /*
       * La prima riga e' sempre la ricerca vera: chi ha scritto una frase
       * ("regalo per una laurea") non trova nulla fra i nomi dei prodotti, e
       * deve poter arrivare alla ricerca semantica con un colpo solo.
       */
      var vai = elemento('a', 'sg-vai');
      vai.href = indirizzoRicerca(testo);
      vai.appendChild(elemento('span', 'sg-vai__lente', '\u2315'));
      vai.appendChild(elemento('span', 'sg-vai__testo', T.cercaNel.replace('%s', testo)));
      esiti.appendChild(vai);

      if (!voci.length) {
        var storico = Storico.leggi();
        if (storico.length) { esiti.appendChild(this.gruppo(T.recenti, storico, true)); }
        return;
      }

      var lista = elemento('ul', 'sg-voci');

      voci.forEach(function (v) {
        var li = elemento('li');
        var a = elemento('a', 'sg-voce sg-voce--' + v.tipo);
        a.href = v.url;
        a.appendChild(elemento('span', 'sg-voce__nome', v.etichetta));
        if (v.nota) { a.appendChild(elemento('span', 'sg-voce__nota', v.nota)); }
        li.appendChild(a);
        lista.appendChild(li);
      });

      esiti.appendChild(lista);
    },

    mostra: function (dati, domanda) {
      var esiti = this.esiti();
      if (!esiti) { return; }

      var risultati = dati.risultati || [];

      if (!risultati.length) { this.stato(T.nessuno, 'sg-stato--vuoto'); return; }

      esiti.textContent = '';

      if (domanda && (dati.categorie || []).length > 1) {
        var pastiglie = elemento('div', 'sg-pastiglie');
        dati.categorie.slice(0, 5).forEach(function (c) {
          var a = elemento('a', 'sg-pastiglia', c.etichetta);
          a.href = indirizzoRicerca(domanda + ' ' + c.etichetta);
          pastiglie.appendChild(a);
        });
        esiti.appendChild(pastiglie);
      }

      var lista = elemento('div', 'sg-righe');

      risultati.forEach(function (r) {
        var nodo = daHtml(r.html);
        if (!nodo) { return; }
        nodo.addEventListener('click', function () {
          segnala('search_result_click', { query: domanda, sku: r.sku });
        });
        lista.appendChild(nodo);
      });

      esiti.appendChild(lista);

      if (domanda) {
        var tutti = elemento('a', 'sg-tutti', T.tutti);
        tutti.href = indirizzoRicerca(domanda);
        esiti.appendChild(tutti);
      }
    },

    /** Le frecce muovono il fuoco fra i risultati; dal primo si torna su. */
    tastiera: function (e) {
      if (e.key === 'Escape') { e.preventDefault(); this.chiudi(); return; }
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }

      var voci = $$('.sg-vai, .sg-voce, .sg-riga, .sg-suggerimento', this.esiti());
      if (!voci.length) { return; }

      var posto = voci.indexOf(document.activeElement);
      e.preventDefault();

      if (e.key === 'ArrowDown') {
        voci[posto < 0 ? 0 : Math.min(posto + 1, voci.length - 1)].focus();
      } else if (posto <= 0) {
        var campo = this.campo();
        if (campo) { campo.focus(); }
      } else {
        voci[posto - 1].focus();
      }
    }
  };

  /* ============================================== pagina dei risultati */

  var PaginaRisultati = {
    avvia: function () {
      var radice = $('#sg-ricerca');
      if (!radice) { return; }

      this.radice = radice;
      this.griglia = $('[data-sg-griglia]', radice);
      this.conto = $('[data-sg-conto]', radice);
      this.niente = $('[data-sg-niente]', radice);

      this.agganciaFiltri();
      this.agganciaFoto();
    },

    celle: function () { return this.griglia ? $$('.sg-cella', this.griglia) : []; },

    agganciaFiltri: function () {
      var self = this;
      if (!this.griglia) { return; }

      $$('[data-sg-categoria], [data-sg-solo-disponibili], [data-sg-da], [data-sg-a]', this.radice)
        .forEach(function (campo) {
          campo.addEventListener(campo.type === 'checkbox' ? 'change' : 'input', function () { self.applica(); });
        });

      $$('[data-sg-azzera]', this.radice).forEach(function (b) {
        b.addEventListener('click', function () {
          $$('[data-sg-categoria], [data-sg-solo-disponibili]', self.radice).forEach(function (c) { c.checked = false; });
          $$('[data-sg-da], [data-sg-a]', self.radice).forEach(function (c) { c.value = ''; });
          self.applica();
        });
      });

      var ordina = $('[data-sg-ordina]', this.radice);
      if (ordina) { ordina.addEventListener('change', function () { self.ordina(ordina.value); }); }
    },

    applica: function () {
      var scelte = $$('[data-sg-categoria]:checked', this.radice).map(function (c) {
        return (c.getAttribute('data-sg-categoria') || '').toLowerCase();
      });

      var soloDisponibili = !!$('[data-sg-solo-disponibili]:checked', this.radice);
      var campoDa = $('[data-sg-da]', this.radice);
      var campoA = $('[data-sg-a]', this.radice);
      var da = campoDa ? parseFloat(campoDa.value) : NaN;
      var a = campoA ? parseFloat(campoA.value) : NaN;

      var visibili = 0;

      this.celle().forEach(function (cella) {
        var categoria = (cella.getAttribute('data-categoria') || '').toLowerCase();
        var grezzo = cella.getAttribute('data-valore');
        var valore = grezzo === '' || grezzo === null ? null : parseFloat(grezzo);
        var passa = true;

        if (scelte.length && scelte.indexOf(categoria) === -1) { passa = false; }
        if (soloDisponibili && cella.getAttribute('data-disponibile') !== '1') { passa = false; }

        /*
         * Un prodotto senza prezzo non puo' soddisfare un filtro di prezzo: si
         * nasconde, invece di passare come se costasse zero.
         */
        if (!isNaN(da) && (valore === null || valore < da)) { passa = false; }
        if (!isNaN(a) && (valore === null || valore > a)) { passa = false; }

        cella.hidden = !passa;
        if (passa) { visibili++; }
      });

      this.conteggio(visibili);

      var attivi = scelte.length > 0 || soloDisponibili || !isNaN(da) || !isNaN(a);
      var azzera = $('.sg-filtri__azzera', this.radice);
      if (azzera) { azzera.hidden = !attivi; }
      if (this.niente) { this.niente.hidden = visibili > 0; }
    },

    conteggio: function (quanti) {
      if (!this.conto) { return; }
      this.conto.textContent = quanti === 1 ? '1 gioiello' : quanti + ' gioielli';
    },

    /*
     * Si riordina con la proprieta' `order` della griglia invece di spostare i
     * nodi: nessun elemento viene tolto e rimesso, quindi le foto gia'
     * scaricate non si ricaricano e il fuoco della tastiera non si perde.
     */
    ordina: function (come) {
      this.celle().forEach(function (cella) {
        var valore = parseFloat(cella.getAttribute('data-valore'));
        var ordine;

        if (come === 'rilevanza') {
          ordine = parseInt(cella.getAttribute('data-ordine'), 10) || 0;
        } else if (isNaN(valore)) {
          // Senza prezzo non si puo' ordinare per prezzo: vanno in fondo.
          ordine = 99999;
        } else {
          ordine = come === 'crescente' ? Math.round(valore) : 99998 - Math.round(valore);
        }

        cella.style.order = String(ordine);
      });
    },

    agganciaFoto: function () {
      var self = this;
      var input = $('[data-sg-file]', this.radice);
      var scelta = $('[data-sg-foto-scelta]', this.radice);
      var posto = $('[data-sg-foto-posto]', this.radice);
      var testo = $('[data-sg-foto-stato]', this.radice);

      $$('[data-sg-scegli-foto]', this.radice).forEach(function (b) {
        b.addEventListener('click', function () { if (input) { input.click(); } });
      });

      var via = $('[data-sg-foto-via]', this.radice);
      if (via) {
        via.addEventListener('click', function () {
          var campo = $('#sg-q');
          window.location.href = indirizzoRicerca(campo ? campo.value.trim() : '');
        });
      }

      if (!input) { return; }

      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) { return; }

        if (scelta) { scelta.hidden = false; }

        var campo = $('#sg-q');

        cercaConFoto(file, {
          query: campo ? campo.value.trim() : '',
          quanti: 48,
          forma: 'griglia',
          anteprima: function (dati) {
            if (!posto) { return; }
            posto.textContent = '';
            var img = elemento('img', 'sg-foto-scelta__img');
            img.src = dati;
            img.alt = '';
            img.width = 48;
            img.height = 48;
            posto.appendChild(img);
          },
          stato: function (messaggio, classe) {
            if (testo) {
              testo.textContent = messaggio;
              testo.className = 'sg-foto-scelta__testo ' + (classe || '');
            }
          },
          esito: function (dati) { self.sostituisci(dati, testo); }
        });
      });
    },

    /** I risultati della foto prendono il posto di quelli delle parole. */
    sostituisci: function (dati, testo) {
      var risultati = dati.risultati || [];

      if (testo) {
        testo.className = 'sg-foto-scelta__testo';
        testo.textContent = risultati.length ? T.fotoSimili : T.nessuno;
      }

      /*
       * Si arriva qui anche quando la pagina era in stato vuoto, e la griglia
       * non esiste: invece di costruire a mano una struttura che il server sa
       * gia' fare, si ricarica con una domanda dentro.
       */
      if (!this.griglia) { return; }

      this.griglia.textContent = '';

      risultati.forEach(function (r, i) {
        var cella = elemento('div', 'sg-cella');
        cella.setAttribute('data-ordine', String(i));
        cella.setAttribute('data-categoria', r.categoria || '');
        cella.setAttribute('data-valore', r.valore === null || r.valore === undefined ? '' : String(r.valore));
        cella.setAttribute('data-disponibile', r.disponibile ? '1' : '0');

        var nodo = daHtml(r.html);
        if (nodo) { cella.appendChild(nodo); }

        this.griglia.appendChild(cella);
      }, this);

      this.conteggio(risultati.length);
      if (this.niente) { this.niente.hidden = risultati.length > 0; }
    }
  };

  /* ======================================================= assistente */

  var Assistente = {
    storia: [],
    inCorso: false,

    avvia: function () {
      var self = this;
      var foglio = $('#sg-assistente');
      if (!foglio) { return; }

      this.foglio = foglio;
      this.conversazione = $('[data-sg-conversazione]', foglio);
      this.campo = $('[data-sg-chat-campo]', foglio);
      this.invia = $('[data-sg-chat-invia]', foglio);

      $$('[data-sg-apri-assistente]').forEach(function (b) {
        b.addEventListener('click', function () { self.apri(); });
      });

      $$('[data-sg-chiudi-assistente]', foglio).forEach(function (b) {
        b.addEventListener('click', function () { self.chiudi(); });
      });

      var pulisci = $('[data-sg-pulisci-chat]', foglio);
      if (pulisci) { pulisci.addEventListener('click', function () { self.ricomincia(); }); }

      var ferma = $('[data-sg-ferma]', foglio);
      if (ferma) { ferma.addEventListener('click', function () { self.ferma(); }); }

      var modulo = $('[data-sg-chiedi]', foglio);
      if (modulo) {
        modulo.addEventListener('submit', function (e) {
          e.preventDefault();
          self.chiedi(self.campo ? self.campo.value : '');
        });
      }

      if (this.campo) {
        // Invio manda, Maiusc+Invio va a capo: la convenzione di ogni chat.
        this.campo.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            self.chiedi(self.campo.value);
          }
        });

        // Il campo cresce con il testo, fino a un tetto.
        this.campo.addEventListener('input', function () {
          self.campo.style.height = 'auto';
          self.campo.style.height = Math.min(120, self.campo.scrollHeight) + 'px';
        });
      }

      this.ripristina();
    },

    aperto: function () { return !!this.foglio && !this.foglio.hidden; },

    apri: function () {
      var self = this;
      this.tornaA = document.activeElement;
      this.foglio.hidden = false;
      document.body.classList.add('sg-bloccato');
      $$('[data-sg-apri-assistente]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });

      if (!this.storia.length) { this.saluta(); }

      window.requestAnimationFrame(function () { if (self.campo) { self.campo.focus(); } });
    },

    chiudi: function () {
      if (!this.aperto()) { return; }

      this.foglio.hidden = true;
      document.body.classList.remove('sg-bloccato');
      $$('[data-sg-apri-assistente]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });

      if (this.tornaA && document.contains(this.tornaA)) { this.tornaA.focus(); }
    },

    saluta: function () {
      var self = this;
      var benvenuto = this.bolla('assistente', C.saluto);
      var esempi = (C.esempi || []).slice(0, 3);

      if (!esempi.length) { return; }

      var lista = elemento('div', 'sg-avvii');

      esempi.forEach(function (e) {
        var b = elemento('button', 'sg-avvio', e);
        b.type = 'button';
        b.addEventListener('click', function () { self.chiedi(e); });
        lista.appendChild(b);
      });

      benvenuto.appendChild(lista);
      this.inFondo();
    },

    /**
     * Una bolla della conversazione.
     *
     * @returns {Element} la riga; dentro c'e' il corpo, che cresce mentre la
     *                    risposta arriva.
     */
    bolla: function (chi, testo) {
      var riga = elemento('div', 'sg-msg sg-msg--' + chi);
      riga.appendChild(elemento('div', 'sg-msg__testo', testo || ''));
      this.conversazione.appendChild(riga);
      this.inFondo();
      return riga;
    },

    inFondo: function () {
      if (this.conversazione) { this.conversazione.scrollTop = this.conversazione.scrollHeight; }
    },

    chiedi: function (domanda) {
      var self = this;
      domanda = (domanda || '').trim();

      if (!domanda || this.inCorso) { return; }

      this.inCorso = true;

      if (this.campo) { this.campo.value = ''; this.campo.style.height = 'auto'; }
      if (this.invia) { this.invia.disabled = true; }

      var pulisci = $('[data-sg-pulisci-chat]', this.foglio);
      if (pulisci) { pulisci.hidden = false; }

      // Gli avvii suggeriti servono solo finche' non si e' chiesto nulla.
      $$('.sg-avvii', this.conversazione).forEach(function (n) { n.remove(); });

      this.bolla('cliente', domanda);

      var risposta = this.bolla('assistente', T.sto);
      var corpo = $('.sg-msg__testo', risposta);
      risposta.classList.add('sg-msg--attesa');

      /*
       * Misurato sul servizio: una risposta arriva dopo venti-trentacinque
       * secondi, e arriva tutta insieme — il flusso a pezzi che il contratto
       * dichiara oggi non e' progressivo. Un'attesa cosi' lunga senza nulla a
       * schermo si legge come un guasto: dopo otto secondi si dice che il
       * lavoro e' in corso, e si offre di smettere.
       */
      this.nota = window.setTimeout(function () {
        if (!self.inCorso) { return; }
        var n = elemento('p', 'sg-msg__nota', T.staCercando);
        risposta.appendChild(n);
        self.inFondo();
      }, 8000);

      this.mostraFermata(true);

      // La storia da mandare e' quella PRIMA di questa domanda.
      var storia = this.storia.slice();
      this.storia.push({ chi: 'cliente', testo: domanda });

      var accumulato = '';

      this.ascolta(domanda, storia, {
        testo: function (pezzo) {
          if (!accumulato) {
            corpo.textContent = '';
            risposta.classList.remove('sg-msg--attesa');
          }
          accumulato += pezzo;
          corpo.textContent = accumulato;
          self.inFondo();
        },

        prodotti: function (elenco) {
          if (!elenco || !elenco.length) { return; }

          var lista = elemento('div', 'sg-righe sg-righe--chat');

          elenco.forEach(function (r) {
            var nodo = daHtml(r.html);
            if (nodo) { lista.appendChild(nodo); }
          });

          risposta.appendChild(lista);
          self.inFondo();
        },

        errore: function (messaggio) {
          risposta.classList.remove('sg-msg--attesa');
          risposta.classList.add('sg-msg--male');
          corpo.textContent = messaggio || T.assErrore;
        },

        fine: function () {
          self.inCorso = false;
          window.clearTimeout(self.nota);
          self.mostraFermata(false);
          if (self.invia) { self.invia.disabled = false; }
          risposta.classList.remove('sg-msg--attesa');
          $$('.sg-msg__nota', risposta).forEach(function (n) { n.remove(); });

          if (accumulato) {
            self.storia.push({ chi: 'assistente', testo: accumulato });
            self.ricorda();
          } else if (!risposta.classList.contains('sg-msg--male')) {
            corpo.textContent = T.assErrore;
            risposta.classList.add('sg-msg--male');
          }

          if (self.campo) { self.campo.focus(); }
        }
      });
    },

    /**
     * Legge la risposta mentre arriva.
     *
     * Non si usa EventSource: quello sa fare solo richieste GET e non puo'
     * mandare il nonce in un'intestazione. Con fetch si legge il corpo a
     * pezzi, che e' la stessa cosa con piu' controllo.
     */
    /** Durante l'attesa il pulsante d'invio diventa un pulsante per fermare. */
    mostraFermata: function (attiva) {
      var ferma = $('[data-sg-ferma]', this.foglio);
      if (ferma) { ferma.hidden = !attiva; }
      if (this.invia) { this.invia.hidden = attiva; }
    },

    ferma: function () {
      if (this.annulla) { this.annulla.abort(); }
    },

    ascolta: function (domanda, storia, su) {
      var finito = false;
      this.annulla = new AbortController();

      function finisci() {
        if (finito) { return; }
        finito = true;
        su.fine();
      }

      fetch(C.ponte + '/assistente', {
        method: 'POST',
        credentials: 'same-origin',
        signal: this.annulla.signal,
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
        body: JSON.stringify({ messaggio: domanda, storia: storia })
      }).then(function (r) {
        if (!r.ok || !r.body) { throw new Error(T.assErrore); }

        var lettore = r.body.getReader();
        var decoder = new TextDecoder();
        var cuscinetto = '';

        function passo() {
          return lettore.read().then(function (pezzo) {
            if (pezzo.done) { finisci(); return; }

            cuscinetto += decoder.decode(pezzo.value, { stream: true });

            /*
             * Un evento finisce con una riga vuota. Il resto resta nel
             * cuscinetto: un pacchetto di rete puo' tagliare un evento a
             * meta', e leggerlo cosi' com'e' darebbe JSON incompleto.
             */
            var taglio;

            while ((taglio = cuscinetto.indexOf('\n\n')) !== -1) {
              var blocco = cuscinetto.slice(0, taglio);
              cuscinetto = cuscinetto.slice(taglio + 2);

              blocco.split('\n').forEach(function (riga) {
                if (riga.indexOf('data:') !== 0) { return; }

                var dati;
                try { dati = JSON.parse(riga.slice(5).trim()); }
                catch (e) { return; }

                if (dati.testo) { su.testo(dati.testo); }
                if (dati.prodotti) { su.prodotti(dati.prodotti); }
                if (dati.errore) { su.errore(dati.errore); }
                if (dati.fine) { finisci(); }
              });
            }

            return passo();
          });
        }

        return passo();
      })['catch'](function (e) {
        // Chi ha premuto "ferma" non ha bisogno che gli si dica che c'e' stato
        // un errore: l'errore l'ha voluto lui.
        su.errore(e && e.name === 'AbortError' ? T.fermata : ((e && e.message) || T.assErrore));
        finisci();
      });
    },

    ricomincia: function () {
      this.storia = [];

      if (this.conversazione) { this.conversazione.textContent = ''; }

      try { window.sessionStorage.removeItem('sg_chat'); } catch (e) {}

      var pulisci = $('[data-sg-pulisci-chat]', this.foglio);
      if (pulisci) { pulisci.hidden = true; }

      this.saluta();
    },

    /*
     * La conversazione vive nella scheda del browser. Il servizio non tiene
     * il filo del discorso — il suo indirizzo accetta un messaggio per volta,
     * senza identificativo di sessione — quindi o lo tiene il browser o non
     * esiste. Si usa sessionStorage e non localStorage: finita la visita,
     * finita la conversazione.
     */
    ricorda: function () {
      try { window.sessionStorage.setItem('sg_chat', JSON.stringify(this.storia.slice(-10))); }
      catch (e) {}
    },

    ripristina: function () {
      var salvata;

      try { salvata = JSON.parse(window.sessionStorage.getItem('sg_chat') || '[]'); }
      catch (e) { return; }

      if (!Array.isArray(salvata) || !salvata.length) { return; }

      var self = this;
      this.storia = salvata;
      salvata.forEach(function (t) { self.bolla(t.chi === 'cliente' ? 'cliente' : 'assistente', t.testo); });

      var pulisci = $('[data-sg-pulisci-chat]', this.foglio);
      if (pulisci) { pulisci.hidden = false; }
    }
  };

  /* ================================================ fuoco nei pannelli */

  function catturaFuoco(e, pannello) {
    if (e.key !== 'Tab' || !pannello || pannello.hidden) { return; }

    var dentro = $$('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', pannello)
      .filter(function (n) { return n.offsetParent !== null; });

    if (!dentro.length) { return; }

    var primo = dentro[0];
    var ultimo = dentro[dentro.length - 1];

    if (e.shiftKey && document.activeElement === primo) { e.preventDefault(); ultimo.focus(); }
    else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primo.focus(); }
  }

  /* =========================================================== avvio */

  function avvia() {
    /*
     * L'INTERCETTAZIONE DELLA RICERCA DEL TEMA SI FA IN FASE DI CATTURA.
     *
     * Quando Storegentic si prende la ricerca del sito, il comando del tema
     * deve aprire il nostro pannello e NON il suo. Con un ascoltatore normale
     * non basta: `preventDefault()` annulla l'azione predefinita del browser,
     * non gli altri ascoltatori, e un tema che ascolta anch'esso il documento
     * apre il proprio pannello lo stesso.
     *
     * Succedeva davvero su questo negozio: si aprivano tutti e due i pannelli,
     * quello del tema stava sopra per un punto di z-index, e il fuoco della
     * tastiera finiva nel campo del nostro, nascosto sotto. Chi cercava
     * scriveva in un campo che non vedeva.
     *
     * La fase di cattura scende dalla radice verso il bersaglio, quindi passa
     * di qui prima di qualunque ascoltatore in fase di risalita, chiunque
     * l'abbia registrato e in qualunque ordine siano stati caricati i file.
     * `stopPropagation()` chiude la questione senza guerre di z-index e senza
     * dover conoscere il markup del tema.
     */
    document.addEventListener('click', function (e) {
      if (!C.sostituisci || !(C.inneschi || []).length) { return; }

      // Dentro i nostri pannelli i comandi sono i nostri: non si intercetta.
      if (e.target.closest('#sg-pannello, #sg-assistente, #sg-ricerca')) { return; }

      var innesco = e.target.closest(C.inneschi.join(','));
      if (!innesco) { return; }

      e.preventDefault();
      e.stopPropagation();

      var suo = innesco.querySelector ? innesco.querySelector('input[type="search"], input[name="s"]') : null;
      Pannello.apri(suo ? suo.value : '');
    }, true);

    /*
     * I comandi dentro il pannello, invece, si riconoscono al clic e non
     * all'avvio: il pannello si stampa a fine pagina, dopo questo script, e
     * un tema o un plugin di cache possono spostare i tag. Cercarli al momento
     * del clic toglie del tutto la dipendenza dall'ordine del documento — che
     * in WordPress non e' una cosa su cui si possa contare.
     */
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-sg-chiudi]')) { e.preventDefault(); Pannello.chiudi(); return; }

      var pulisci = e.target.closest('[data-sg-pulisci]');
      if (pulisci) {
        var campo = Pannello.campo();
        if (campo) { campo.value = ''; campo.focus(); }
        pulisci.hidden = true;
        Pannello.ultima = '';
        Pannello.iniziale();
      }
    });

    document.addEventListener('input', function (e) {
      var campo = e.target.closest('#sg-campo-pannello');
      if (!campo) { return; }

      var pulisci = $('[data-sg-pulisci]');
      if (pulisci) { pulisci.hidden = campo.value.length === 0; }

      Pannello.suggerisci(campo.value);
    });

    /*
     * Invio nella barra porta alla pagina dei risultati. Il modulo e' un GET
     * vero verso quella pagina: senza JavaScript fa la stessa cosa da solo, e
     * non ci sono due percorsi da tenere allineati.
     */
    document.addEventListener('submit', function (e) {
      var modulo = e.target.closest('[data-sg-barra]');
      if (!modulo) { return; }

      var campo = $('[data-sg-campo]', modulo);
      var domanda = campo ? campo.value.trim() : '';

      if (!domanda) { e.preventDefault(); return; }

      Storico.aggiungi(domanda);
    });

    document.addEventListener('keydown', function (e) {
      if (Pannello.aperto()) {
        catturaFuoco(e, Pannello.foglio());
        Pannello.tastiera(e);
        return;
      }

      if (Assistente.aperto()) {
        catturaFuoco(e, Assistente.foglio);
        if (e.key === 'Escape') { e.preventDefault(); Assistente.chiudi(); }
      }
    });

    /* --- la foto, dal pannello --- */

    var pannello = $('#sg-pannello');

    if (pannello) {
      var bottone = $('[data-sg-scegli-foto]', pannello);
      var file = $('[data-sg-file]', pannello);

      if (bottone && file) {
        bottone.addEventListener('click', function () { file.click(); });

        file.addEventListener('change', function () {
          var scelto = file.files && file.files[0];
          if (!scelto) { return; }

          cercaConFoto(scelto, {
            quanti: 8,
            forma: 'riga',
            stato: function (messaggio, classe) { Pannello.stato(messaggio, classe); },
            esito: function (dati) {
              Pannello.mostra(dati, '');

              var esiti = Pannello.esiti();
              if (esiti) {
                esiti.insertBefore(elemento('p', 'sg-stato sg-stato--nota', T.fotoSimili), esiti.firstChild);
              }
            }
          });

          // Rimettendo a zero, la stessa foto si puo' riprovare.
          file.value = '';
        });
      }
    }

    PaginaRisultati.avvia();

    if (C.assistente) { Assistente.avvia(); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', avvia);
  } else {
    avvia();
  }
}());
