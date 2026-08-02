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

  /* ==================================================== la finestra
   *
   * UN CONTENITORE, TRE MODI. La finestra si apre da un pulsante che il plugin
   * disegna da se': non si aggancia a niente del tema, perche' un plugin
   * universale non puo' sapere se il tema ha una lente, dove sta, e cosa fa
   * quando la si tocca.
   *
   * Ogni modo ha il proprio pannello, il proprio corpo che scorre e la propria
   * riga di comando in basso. Cambiare modo non ricarica nulla e non perde
   * nulla: chi ha cercato "collana", e' passato alla foto e torna indietro,
   * ritrova i suoi risultati.
   */

  var Finestra = {
    modo: null,
    tornaA: null,
    stato: {},        // per ogni modo: che cosa c'e' dentro adesso
    annulla: null,

    radice: function () { return $('#sg-finestra'); },
    corpo: function (modo) { return $('[data-sg-corpo="' + (modo || this.modo) + '"]'); },
    aperta: function () { var r = this.radice(); return !!r && !r.hidden; },

    avvia: function () {
      var self = this;
      var radice = this.radice();
      if (!radice) { return; }

      this.modi = (C.modi || []).slice();
      this.modo = this.modi[0] || null;

      $$('[data-sg-modo]', radice).forEach(function (b) {
        b.addEventListener('click', function () { self.cambia(b.getAttribute('data-sg-modo')); });
      });

      // Frecce fra le linguette: e' il comportamento che ci si aspetta da un
      // gruppo di schede, ed e' l'unico modo di girarle senza mouse.
      var linguette = $$('[data-sg-modo]', radice);
      linguette.forEach(function (b, i) {
        b.addEventListener('keydown', function (e) {
          var passo = e.key === 'ArrowRight' ? 1 : (e.key === 'ArrowLeft' ? -1 : 0);
          if (!passo) { return; }
          e.preventDefault();
          var n = linguette[(i + passo + linguette.length) % linguette.length];
          n.focus();
          self.cambia(n.getAttribute('data-sg-modo'));
        });
      });

      this.agganciaCerca();
      this.agganciaFoto();
      if (this.modi.indexOf('chat') !== -1) { Assistente.avvia(this); }
    },

    /* ------------------------------------------------- apri e chiudi */

    apri: function (modo, precompila) {
      var self = this;
      var radice = this.radice();
      if (!radice) { return; }

      this.tornaA = document.activeElement;
      radice.hidden = false;
      document.body.classList.add('sg-bloccato', 'sg-finestra-aperta');
      $$('[data-sg-apri]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });

      if (modo && this.modi.indexOf(modo) !== -1) { this.cambia(modo); }
      else { this.cambia(this.modo, true); }

      if (precompila && this.modi.indexOf('cerca') !== -1) {
        var campo = $('[data-sg-campo-cerca]');
        if (campo) { campo.value = precompila; }
      }

      window.requestAnimationFrame(function () { self.fuocoIniziale(); });
      segnala('widget_open', { mode: this.modo });
    },

    chiudi: function () {
      var radice = this.radice();
      if (!radice || radice.hidden) { return; }

      radice.hidden = true;
      document.body.classList.remove('sg-bloccato', 'sg-finestra-aperta');
      $$('[data-sg-apri]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });

      if (this.annulla) { this.annulla.abort(); this.annulla = null; }
      if (this.tornaA && document.contains(this.tornaA)) { this.tornaA.focus(); }
      this.tornaA = null;
    },

    /*
     * Il fuoco entra dove si scrive, non sul primo elemento in ordine di
     * documento: aprendo la ricerca si vuole poter digitare subito. Nel modo
     * foto non c'e' un campo, e allora prende il pulsante che apre i file.
     */
    fuocoIniziale: function () {
      var pannello = $('[data-sg-pannello="' + this.modo + '"]');
      if (!pannello) { return; }

      var bersaglio = $('input[type="search"], textarea, [data-sg-scegli-foto]', pannello);
      if (bersaglio) { bersaglio.focus(); }
    },

    /* --------------------------------------------------- cambio modo */

    cambia: function (modo, forza) {
      if (!modo || (modo === this.modo && !forza)) { return; }

      this.modo = modo;

      $$('[data-sg-pannello]').forEach(function (p) {
        p.hidden = p.getAttribute('data-sg-pannello') !== modo;
      });

      $$('[data-sg-modo]').forEach(function (b) {
        var suo = b.getAttribute('data-sg-modo') === modo;
        b.setAttribute('aria-selected', suo ? 'true' : 'false');
        b.tabIndex = suo ? 0 : -1;
      });

      // Ogni modo che si apre per la prima volta dice cosa sa fare.
      if (!this.stato[modo]) { this.stato[modo] = 'iniziale'; this.iniziale(modo); }
    },

    iniziale: function (modo) {
      var corpo = this.corpo(modo);
      if (!corpo) { return; }

      if (modo === 'cerca') { this.inizialeCerca(corpo); }
      else if (modo === 'foto') { this.inizialeFoto(corpo); }
    },

    /* ------------------------------------------------------- stati */

    vuota: function (modo) {
      var corpo = this.corpo(modo);
      if (corpo) { corpo.textContent = ''; }
      return corpo;
    },

    dice: function (modo, testo, classe) {
      var corpo = this.vuota(modo);
      if (!corpo) { return; }
      corpo.appendChild(elemento('p', 'sg-stato ' + (classe || ''), testo));
    },

    attende: function (modo, testo) {
      var corpo = this.vuota(modo);
      if (!corpo) { return; }

      var blocco = elemento('div', 'sg-attesa');
      var punti = elemento('span', 'sg-punti');
      punti.setAttribute('aria-hidden', 'true');
      for (var i = 0; i < 3; i++) { punti.appendChild(document.createElement('i')); }
      blocco.appendChild(punti);
      blocco.appendChild(elemento('p', 'sg-attesa__testo', testo));
      corpo.appendChild(blocco);
    },

    /* ------------------------------------------------ modo: cerca */

    inizialeCerca: function (corpo) {
      corpo.textContent = '';

      var recenti = Storico.leggi();
      if (recenti.length) { corpo.appendChild(this.gruppo(T.recenti, recenti, true)); }

      var esempi = (C.esempi || []).slice(0, 4);
      if (esempi.length) { corpo.appendChild(this.gruppo(T.suggeriti, esempi, false)); }

      /*
       * Le categorie del negozio chiudono lo stato d'apertura. Senza, la
       * finestra si apriva su quattro righe di testo e settecento pixel di
       * vuoto: non diceva che cosa ci fosse in negozio, e chi non aveva una
       * parola in mente non aveva da dove cominciare.
       */
      var categorie = C.categorie || [];
      if (!categorie.length) { return; }

      var sezione = elemento('section', 'sg-gruppo');
      sezione.appendChild(elemento('h2', 'sg-gruppo__titolo', T.sfoglia));

      var griglia = elemento('div', 'sg-categorie');

      categorie.forEach(function (c) {
        var a = elemento('a', 'sg-categoria');
        a.href = c.url;
        a.appendChild(elemento('span', 'sg-categoria__nome', c.etichetta));
        a.appendChild(elemento('span', 'sg-categoria__conto', String(c.conteggio)));
        griglia.appendChild(a);
      });

      sezione.appendChild(griglia);
      corpo.appendChild(sezione);
    },

    gruppo: function (titolo, voci, conCestino) {
      var self = this;
      var sezione = elemento('section', 'sg-gruppo');
      sezione.appendChild(elemento('h2', 'sg-gruppo__titolo', titolo));

      var lista = elemento('ul', 'sg-suggerimenti');

      voci.forEach(function (v) {
        var li = elemento('li');
        var b = elemento('button', 'sg-suggerimento', v);
        b.type = 'button';
        b.addEventListener('click', function () {
          var campo = $('[data-sg-campo-cerca]');
          if (campo) { campo.value = v; }

          if (C.inPagina) { Storico.aggiungi(v); window.location.href = indirizzoRicerca(v); return; }

          self.cerca(v);
        });
        li.appendChild(b);
        lista.appendChild(li);
      });

      sezione.appendChild(lista);

      if (conCestino) {
        var via = elemento('button', 'sg-gruppo__via', T.pulisci);
        via.type = 'button';
        via.addEventListener('click', function () { Storico.svuota(); self.inizialeCerca(self.corpo('cerca')); });
        sezione.appendChild(via);
      }

      return sezione;
    },

    agganciaCerca: function () {
      var self = this;
      var modulo = $('[data-sg-cerca]');
      if (!modulo) { return; }

      var campo = $('[data-sg-campo-cerca]', modulo);
      var pulisci = $('[data-sg-pulisci]', modulo);

      /*
       * QUANDO IL NEGOZIO HA LA PAGINA DEI RISULTATI, L'INVIO CI PORTA.
       *
       * Il modulo e' un GET vero verso quella pagina: non si annulla nulla e
       * il browser naviga da solo — che e' anche il motivo per cui la ricerca
       * a parole funziona con JavaScript spento. La finestra tiene i risultati
       * solo quando quella pagina non c'e', ed e' allora che deve bastare a se
       * stessa.
       */
      modulo.addEventListener('submit', function (e) {
        var domanda = campo ? campo.value.trim() : '';

        if (domanda.length < 2) { e.preventDefault(); return; }

        Storico.aggiungi(domanda);

        if (C.inPagina) { return; }   // lascia navigare il modulo

        e.preventDefault();
        self.cerca(domanda);
      });

      if (campo) {
        campo.addEventListener('input', function () {
          if (pulisci) { pulisci.hidden = campo.value.length === 0; }
          self.suggerisci(campo.value);
        });
      }

      if (pulisci) {
        pulisci.addEventListener('click', function () {
          campo.value = '';
          pulisci.hidden = true;
          self.ultimoSuggerito = '';
          self.inizialeCerca(self.corpo('cerca'));
          campo.focus();
        });
      }
    },

    /*
     * Mentre si scrive rispondono i nomi dei prodotti, letti dal negozio in
     * pochi millisecondi. La ricerca semantica parte all'invio: costa secondi,
     * e lanciarla a ogni tasto sarebbe una fila di richieste inutili.
     * Vedi Frontend\Suggerimenti.
     */
    suggerisci: function (testo) {
      var self = this;
      testo = (testo || '').trim();

      window.clearTimeout(this.attesaSugg);

      if (testo.length < 2) {
        this.ultimoSuggerito = '';
        this.inizialeCerca(this.corpo('cerca'));
        return;
      }

      if (testo === this.ultimoSuggerito) { return; }

      this.attesaSugg = window.setTimeout(function () {
        self.ultimoSuggerito = testo;

        if (self.annullaSugg) { self.annullaSugg.abort(); }
        self.annullaSugg = new AbortController();

        fetch(C.ponte + '/suggerimenti?q=' + encodeURIComponent(testo), {
          credentials: 'same-origin',
          signal: self.annullaSugg.signal,
          headers: { 'X-WP-Nonce': C.nonce }
        })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            self.annullaSugg = null;
            var campo = $('[data-sg-campo-cerca]');
            if (campo && campo.value.trim() !== testo) { return; }
            self.mostraSuggerimenti(testo, (d && d.voci) || []);
          })
          ['catch'](function (e) {
            if (e && e.name === 'AbortError') { return; }
            self.annullaSugg = null;
            self.mostraSuggerimenti(testo, []);
          });
      }, 160);
    },

    mostraSuggerimenti: function (testo, voci) {
      var self = this;
      var corpo = this.vuota('cerca');
      if (!corpo) { return; }

      /*
       * La prima riga porta sempre alla ricerca vera: chi scrive una frase
       * intera non la trova fra i nomi dei prodotti, e deve poterci arrivare
       * senza rileggere l'elenco. E' un collegamento quando si va in pagina —
       * cosi' si apre in una scheda nuova col tasto centrale, come ci si
       * aspetta da un link — e un pulsante quando si resta dentro.
       */
      var vai;

      if (C.inPagina) {
        vai = elemento('a', 'sg-vai');
        vai.href = indirizzoRicerca(testo);
        vai.addEventListener('click', function () { Storico.aggiungi(testo); });
      } else {
        vai = elemento('button', 'sg-vai');
        vai.type = 'button';
        vai.addEventListener('click', function () { self.cerca(testo); });
      }

      vai.appendChild(elemento('span', 'sg-vai__segno', '⌕'));
      vai.appendChild(elemento('span', 'sg-vai__testo', (T.cercaNel || '%s').replace('%s', testo)));
      corpo.appendChild(vai);

      if (!voci.length) { return; }

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

      corpo.appendChild(lista);
    },

    cerca: function (domanda) {
      var self = this;
      domanda = (domanda || '').trim();

      if (domanda.length < 2) { return; }

      window.clearTimeout(this.attesaSugg);
      if (this.annullaSugg) { this.annullaSugg.abort(); this.annullaSugg = null; }

      this.attende('cerca', T.inCorso);
      Storico.aggiungi(domanda);

      if (this.annulla) { this.annulla.abort(); }
      this.annulla = new AbortController();

      chiama('/ricerca', { query: domanda, topK: 36, forma: 'griglia' }, this.annulla.signal)
        .then(function (dati) {
          self.annulla = null;
          self.risultati('cerca', dati, domanda);
          segnala('search_query', { query: domanda, results: (dati.risultati || []).length });
        })
        ['catch'](function (e) {
          if (e && e.name === 'AbortError') { return; }
          self.annulla = null;
          self.dice('cerca', (e && e.message) || T.errore, 'sg-stato--male');
        });
    },

    /* -------------------------------------------------- modo: foto */

    inizialeFoto: function (corpo) {
      corpo.textContent = '';

      var invito = elemento('div', 'sg-invito-foto');
      invito.appendChild(elemento('p', 'sg-invito-foto__titolo', T.fotoTitolo));
      invito.appendChild(elemento('p', 'sg-invito-foto__testo', T.fotoSpiega));
      corpo.appendChild(invito);
    },

    agganciaFoto: function () {
      var self = this;
      var pannello = $('[data-sg-pannello="foto"]');
      if (!pannello) { return; }

      var file = $('[data-sg-file]', pannello);
      var bottone = $('[data-sg-scegli-foto]', pannello);
      if (!file || !bottone) { return; }

      bottone.addEventListener('click', function () { file.click(); });

      file.addEventListener('change', function () {
        var scelto = file.files && file.files[0];
        if (scelto) { self.conFoto(scelto); }
        file.value = '';   // rimettendo a zero, la stessa foto si puo' riprovare
      });

      /*
       * Trascinare una foto dentro la finestra e' il gesto naturale su un
       * computer, e non costa niente: sono quattro ascoltatori.
       */
      pannello.addEventListener('dragover', function (e) {
        e.preventDefault();
        pannello.classList.add('sg-pannello--goccia');
      });
      pannello.addEventListener('dragleave', function () { pannello.classList.remove('sg-pannello--goccia'); });
      pannello.addEventListener('drop', function (e) {
        e.preventDefault();
        pannello.classList.remove('sg-pannello--goccia');
        var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) { self.conFoto(f); }
      });
    },

    conFoto: function (file) {
      var self = this;

      this.attende('foto', T.fotoInCorso);

      cercaConFoto(file, {
        quanti: 36,
        forma: 'griglia',
        anteprima: function (dati) { self.anteprimaFoto = dati; },
        stato: function (messaggio, classe) {
          if (classe === 'sg-stato--male') { self.dice('foto', messaggio, classe); }
        },
        esito: function (dati) { self.risultati('foto', dati, '', self.anteprimaFoto); }
      });
    },

    /* --------------------------------------------- i risultati, dentro */

    risultati: function (modo, dati, domanda, anteprima) {
      var self = this;
      var corpo = this.vuota(modo);
      if (!corpo) { return; }

      var elenco = dati.risultati || [];

      if (anteprima) {
        var riga = elemento('div', 'sg-con-foto');
        var img = elemento('img', 'sg-con-foto__img');
        img.src = anteprima;
        img.alt = '';
        riga.appendChild(img);
        riga.appendChild(elemento('p', 'sg-con-foto__testo', elenco.length ? T.fotoSimili : T.nessuno));
        var via = elemento('button', 'sg-con-foto__via', T.fotoAltra);
        via.type = 'button';
        via.addEventListener('click', function () {
          self.stato[modo] = 'iniziale';
          self.inizialeFoto(self.vuota(modo));
        });
        riga.appendChild(via);
        corpo.appendChild(riga);
      }

      if (!elenco.length) {
        if (!anteprima) { this.dice(modo, T.nessuno, 'sg-stato--vuoto'); }
        return;
      }

      // Barra di affinamento: conteggio, categorie, ordine.
      corpo.appendChild(this.barra(modo, dati, domanda));

      var griglia = elemento('div', 'sg-griglia');
      griglia.setAttribute('data-sg-griglia', modo);

      elenco.forEach(function (r, i) {
        var cella = elemento('div', 'sg-cella');
        cella.setAttribute('data-ordine', String(i));
        cella.setAttribute('data-categoria', r.categoria || '');
        cella.setAttribute('data-valore', r.valore === null || r.valore === undefined ? '' : String(r.valore));
        var nodo = daHtml(r.html);
        if (nodo) { cella.appendChild(nodo); }
        cella.addEventListener('click', function () {
          segnala('search_result_click', { query: domanda, sku: r.sku });
        });
        griglia.appendChild(cella);
      });

      corpo.appendChild(griglia);

      /*
       * Il collegamento alla pagina dei risultati c'e' solo se la pagina c'e',
       * e sta in fondo: e' un "e se non basta", non un comando. La finestra si
       * regge da sola anche quando quella pagina non e' raggiungibile da
       * nessun menu.
       */
      if (domanda && C.pagina) {
        var tutti = elemento('a', 'sg-tutti', T.tutti);
        tutti.href = indirizzoRicerca(domanda);
        corpo.appendChild(tutti);
      }

      corpo.scrollTop = 0;
    },

    barra: function (modo, dati, domanda) {
      var self = this;
      var barra = elemento('div', 'sg-barra-esiti');

      var conto = elemento('p', 'sg-barra-esiti__conto');
      conto.setAttribute('data-sg-conto', modo);
      conto.textContent = self.conteggio((dati.risultati || []).length);
      barra.appendChild(conto);

      var comandi = elemento('div', 'sg-barra-esiti__comandi');

      var categorie = (dati.categorie || []).slice(0, 6);
      if (categorie.length > 1) {
        var pastiglie = elemento('div', 'sg-filtri-veloci');
        categorie.forEach(function (c) {
          var b = elemento('button', 'sg-pastiglia', c.etichetta + ' ' + c.conteggio);
          b.type = 'button';
          b.setAttribute('aria-pressed', 'false');
          b.addEventListener('click', function () {
            var acceso = b.getAttribute('aria-pressed') === 'true';
            $$('.sg-pastiglia', pastiglie).forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
            b.setAttribute('aria-pressed', acceso ? 'false' : 'true');
            self.applica(modo, acceso ? '' : c.etichetta);
          });
          pastiglie.appendChild(b);
        });
        comandi.appendChild(pastiglie);
      }

      var ordina = elemento('select', 'sg-ordina');
      ordina.setAttribute('aria-label', T.ordina);
      [['rilevanza', T.piuPertinenti], ['crescente', T.prezzoSu], ['decrescente', T.prezzoGiu]].forEach(function (o) {
        var op = elemento('option', null, o[1]);
        op.value = o[0];
        ordina.appendChild(op);
      });
      ordina.addEventListener('change', function () { self.ordina(modo, ordina.value); });
      comandi.appendChild(ordina);

      barra.appendChild(comandi);

      return barra;
    },

    celle: function (modo) { return $$('[data-sg-griglia="' + modo + '"] .sg-cella'); },

    conteggio: function (n) {
      return n === 1 ? (T.unGioiello || '1 risultato') : (T.nGioielli || '%d risultati').replace('%d', n);
    },

    applica: function (modo, categoria) {
      var visibili = 0;
      var voluta = (categoria || '').toLowerCase();

      this.celle(modo).forEach(function (cella) {
        var sua = (cella.getAttribute('data-categoria') || '').toLowerCase();
        var passa = !voluta || sua === voluta;
        cella.hidden = !passa;
        if (passa) { visibili++; }
      });

      var conto = $('[data-sg-conto="' + modo + '"]');
      if (conto) { conto.textContent = this.conteggio(visibili); }
    },

    /*
     * Si riordina con la proprieta' `order` invece di spostare i nodi: nessun
     * elemento viene tolto e rimesso, quindi le foto gia' scaricate non si
     * ricaricano e il fuoco della tastiera non si perde.
     */
    ordina: function (modo, come) {
      this.celle(modo).forEach(function (cella) {
        var valore = parseFloat(cella.getAttribute('data-valore'));
        var ordine;

        if (come === 'rilevanza') { ordine = parseInt(cella.getAttribute('data-ordine'), 10) || 0; }
        else if (isNaN(valore)) { ordine = 99999; }   // senza prezzo, in fondo
        else { ordine = come === 'crescente' ? Math.round(valore) : 99998 - Math.round(valore); }

        cella.style.order = String(ordine);
      });
    }
  };

  /* ==================================================== l'assistente
   *
   * E' un modo della finestra, non una finestra sua: apertura, chiusura e
   * fuoco li gestisce la finestra. Qui c'e' solo la conversazione.
   */

  var Assistente = {
    storia: [],
    inCorso: false,
    annulla: null,

    avvia: function (finestra) {
      var self = this;
      this.finestra = finestra;

      this.corpo = $('[data-sg-corpo="chat"]');
      this.campo = $('[data-sg-campo-chat]');
      this.invia = $('[data-sg-invia-chat]');
      this.ferma = $('[data-sg-ferma]');

      if (!this.corpo) { return; }

      var modulo = $('[data-sg-chiedi]');
      if (modulo) {
        modulo.addEventListener('submit', function (e) {
          e.preventDefault();
          self.chiedi(self.campo ? self.campo.value : '');
        });
      }

      if (this.ferma) {
        this.ferma.addEventListener('click', function () {
          if (self.annulla) { self.annulla.abort(); }
        });
      }

      var pulisci = $('[data-sg-ricomincia]');
      if (pulisci) { pulisci.addEventListener('click', function () { self.ricomincia(); }); }

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
      if (!this.storia.length) { this.saluta(); }
    },

    saluta: function () {
      var self = this;
      var benvenuto = this.bolla('assistente', C.saluto);
      var esempi = (C.esempiChat || []).slice(0, 3);

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

    /** Una bolla: la riga, con dentro il corpo che cresce mentre si scrive. */
    bolla: function (chi, testo) {
      var riga = elemento('div', 'sg-msg sg-msg--' + chi);
      riga.appendChild(elemento('div', 'sg-msg__testo', testo || ''));
      this.corpo.appendChild(riga);
      this.inFondo();
      return riga;
    },

    inFondo: function () {
      if (this.corpo) { this.corpo.scrollTop = this.corpo.scrollHeight; }
    },

    mostraFermata: function (attiva) {
      if (this.ferma) { this.ferma.hidden = !attiva; }
      if (this.invia) { this.invia.hidden = attiva; }
    },

    chiedi: function (domanda) {
      var self = this;
      domanda = (domanda || '').trim();

      if (!domanda || this.inCorso) { return; }

      this.inCorso = true;
      if (this.campo) { this.campo.value = ''; this.campo.style.height = 'auto'; }
      if (this.invia) { this.invia.disabled = true; }
      this.mostraFermata(true);

      var ricomincia = $('[data-sg-ricomincia]');
      if (ricomincia) { ricomincia.hidden = false; }

      // Gli avvii suggeriti servono solo finche' non si e' chiesto nulla.
      $$('.sg-avvii', this.corpo).forEach(function (n) { n.remove(); });

      this.bolla('cliente', domanda);

      var risposta = this.bolla('assistente', '');
      var corpo = $('.sg-msg__testo', risposta);
      risposta.classList.add('sg-msg--attesa');

      /*
       * L'ATTESA NON PARLA. Il servizio impiega dieci secondi e passa, e una
       * frase ferma per quel tempo invecchia male. Tre pallini dicono "sono
       * vivo" senza promettere tempi. La frase resta per chi ascolta la pagina
       * invece di guardarla, altrimenti troverebbe una bolla vuota.
       */
      corpo.appendChild(elemento('span', 'sg-fuori-schermo', T.sto));
      var punti = elemento('span', 'sg-punti');
      punti.setAttribute('aria-hidden', 'true');
      for (var i = 0; i < 3; i++) { punti.appendChild(document.createElement('i')); }
      corpo.appendChild(punti);

      // La storia da mandare e' quella PRIMA di questa domanda.
      var storia = this.storia.slice();
      this.storia.push({ chi: 'cliente', testo: domanda });

      var accumulato = '';

      this.ascolta(domanda, storia, {
        testo: function (pezzo) {
          if (!accumulato) {
            corpo.textContent = '';   // via i pallini
            risposta.classList.remove('sg-msg--attesa');
          }
          accumulato += pezzo;
          corpo.textContent = accumulato;
          self.inFondo();
        },

        /*
         * L'assistente scrive in Markdown: elenchi, grassetti, collegamenti e
         * perfino le figure dei prodotti. Il server manda la versione
         * impaginata a risposta finita, gia' ripulita e passata da wp_kses:
         * qui si inserisce e basta, come per le schede.
         */
        impaginato: function (html) {
          corpo.innerHTML = html;
          risposta.classList.remove('sg-msg--attesa');
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
          self.mostraFermata(false);
          if (self.invia) { self.invia.disabled = false; }
          risposta.classList.remove('sg-msg--attesa');

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
    ascolta: function (domanda, storia, su) {
      var self = this;
      var finito = false;

      function finisci() {
        if (finito) { return; }
        finito = true;
        su.fine();
      }

      this.annulla = new AbortController();

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
                if (dati.html) { su.impaginato(dati.html); }
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
      }).then(function () { self.annulla = null; });
    },

    ricomincia: function () {
      this.storia = [];
      if (this.corpo) { this.corpo.textContent = ''; }

      try { window.sessionStorage.removeItem('sg_chat'); } catch (e) {}

      var ricomincia = $('[data-sg-ricomincia]');
      if (ricomincia) { ricomincia.hidden = true; }

      this.saluta();
    },

    /*
     * La conversazione vive nella scheda del browser. Il servizio non tiene il
     * filo del discorso — il suo indirizzo accetta un messaggio per volta,
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

      var ricomincia = $('[data-sg-ricomincia]');
      if (ricomincia) { ricomincia.hidden = false; }
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


  /* ============================================== fuoco dentro la finestra */

  /*
   * Il fuoco resta dentro finche' la finestra e' aperta. Senza, premendo Tab
   * si esce dietro il velo, si continua a navigare una pagina che non si vede,
   * e non si trova piu' il modo di tornare indietro.
   */
  function catturaFuoco(e, dentro) {
    if (e.key !== 'Tab' || !dentro || dentro.hidden) { return; }

    var fuocabili = $$('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', dentro)
      .filter(function (n) { return n.offsetParent !== null; });

    if (!fuocabili.length) { return; }

    var primo = fuocabili[0];
    var ultimo = fuocabili[fuocabili.length - 1];

    if (e.shiftKey && document.activeElement === primo) { e.preventDefault(); ultimo.focus(); }
    else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primo.focus(); }
  }

  /* ============================================================= avvio */

  function avvia() {
    Finestra.avvia();
    PaginaRisultati.avvia();

    document.addEventListener('click', function (e) {
      var apre = e.target.closest('[data-sg-apri]');
      if (apre) {
        e.preventDefault();
        Finestra.apri(apre.getAttribute('data-sg-modo-iniziale') || null);
        return;
      }

      if (e.target.closest('[data-sg-chiudi]')) { e.preventDefault(); Finestra.chiudi(); }
    });

    /*
     * L'INTERCETTAZIONE DELLA RICERCA DEL TEMA SI FA IN FASE DI CATTURA.
     *
     * Quando Storegentic si prende la ricerca del sito, il comando del tema
     * deve aprire la nostra finestra e NON la sua. Con un ascoltatore normale
     * non basta: preventDefault() annulla l'azione predefinita del browser,
     * non gli altri ascoltatori, e un tema che ascolta anch'esso il documento
     * apre il proprio pannello lo stesso — visto succedere, con i due pannelli
     * sovrapposti e il fuoco della tastiera in quello nascosto sotto.
     *
     * La fase di cattura scende dalla radice verso il bersaglio, quindi passa
     * di qui prima di chiunque altro, in qualunque ordine siano stati caricati
     * i file.
     */
    document.addEventListener('click', function (e) {
      if (!C.sostituisci || !(C.inneschi || []).length) { return; }
      if (e.target.closest('#sg-finestra, #sg-ricerca, [data-sg-apri]')) { return; }

      var innesco = e.target.closest(C.inneschi.join(','));
      if (!innesco) { return; }

      e.preventDefault();
      e.stopPropagation();

      var suo = innesco.querySelector ? innesco.querySelector('input[type="search"], input[name="s"]') : null;
      var scritto = suo ? suo.value.trim() : '';

      /*
       * Se il tema aveva gia' delle parole nel campo e il negozio ha la pagina
       * dei risultati, si va dritti li': aprire una finestra per far ripremere
       * Invio sarebbe un passaggio in piu' per niente.
       */
      if (C.inPagina && scritto.length >= 2) {
        Storico.aggiungi(scritto);
        window.location.href = indirizzoRicerca(scritto);
        return;
      }

      Finestra.apri('cerca', scritto);
    }, true);

    document.addEventListener('keydown', function (e) {
      if (!Finestra.aperta()) { return; }

      if (e.key === 'Escape') { e.preventDefault(); Finestra.chiudi(); return; }

      catturaFuoco(e, Finestra.radice());
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', avvia);
  } else {
    avvia();
  }
}());
