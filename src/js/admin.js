// Libraries js
import 'bootstrap';
import DataTable, * as datatables from 'datatables.net-dt';

// Project js
import * as functies from '@muzieklijsten/functies';
import * as server from '@muzieklijsten/server';
import TypedEvent from '@muzieklijsten/TypedEvent';

// css
import '/src/scss/admin.scss';

// HTML
import html_resultaten_modal from '/src/html/admin-resultaten-modal.html';
import html_resultaten_nummer from '/src/html/admin-resultaten-nummer.html';
import html_resultaten_stem from '/src/html/admin-resultaten-stem.html';
import html_beheer_modal from '/src/html/admin-beheer-modal.html';

class Main {

  /** @type {?number} */
  lijst_id;
  /** @type {Promise<string>} */
  lijst_naam_promise;
  /** @type {HTMLBodyElement} */
  e_body;
  /** @type {HTMLSelectElement} */
  e_lijst_select;
  /** @type {HTMLTableSectionElement} */
  e_geselecteerd_lijst;
  /** @type {HTMLSpanElement} */
  e_aantal_geselecteerde_nummers;
  geselecteerde_nummers;
  tabel;

  constructor() {
    this.e_body = document.getElementsByTagName('body').item(0);
    this.e_lijst_select = document.getElementById('lijstselect');
    this.e_geselecteerd_lijst = document.getElementById('geselecteerd-lijst');
    this.e_aantal_geselecteerde_nummers = document.getElementById('aantal-geselecteerde-nummers');
    this.lijst_naam_promise = Promise.resolve('?');

    this.vul_metadata().then(() => {
      let params = new URLSearchParams(document.location.search);
      let lijst_id = params.get('lijst');
      for ( const e_option of this.e_lijst_select.options ) {
        if ( e_option.value == lijst_id ) {
          e_option.selected = true;
          this.set_lijst(lijst_id);
          break;
        }
      }
    });

    this.tabel = new DataTable(
      document.getElementById('beschikbare-nummers'),
      {
        processing: true,
        serverSide: true,
        ajax: (data, callback, settings) => {
          data.is_vrijekeuze = false;
          functies.vul_datatables(data, callback, settings);
        },
        columnDefs: [{
          targets: 0,
          searchable: false,
          orderable: false,
          className: 'dt-body-center',
          render: (nummer_id, type, [nummer_id2, titel, artiest, jaar], meta) => {
            let input = document.createElement('input');
            input.setAttribute('type', 'checkbox');
            return input.outerHTML;
          }
        }],
        order: [1, 'asc'],
        rowCallback: this.toon_geselecteerd.bind(this),
        language: {
          lengthMenu: '_MENU_ nummers per pagina',
          zeroRecords: 'Geen nummers gevonden',
          info: 'Pagina _PAGE_ van _PAGES_',
          infoEmpty: 'Geen nummers gevonden',
          infoFiltered: '(gefilterd van _MAX_ totaal)',
          search: 'Zoeken:',
          paginate: {
            first: 'Eerste',
            last: 'Laatste',
            next: 'Volgende',
            previous: 'Vorige'
          }
        }
      }
    );

    // Gebruiker klikt op een rij van de beschikbare nummers.
    $('#beschikbare-nummers').on('click', 'tbody>tr', this.checkbox_handler.bind(this));

    document.getElementById('lijstselect').addEventListener('change', this.lijst_select_handler.bind(this));

    // Bestaande lijst wijzigen
    document.getElementById('beheerknop').addEventListener('click', this.beheer_knop_handler.bind(this));

    // Nieuwe lijst maken
    document.getElementById('nieuwknop').addEventListener('click', this.nieuw_knop_handler.bind(this));

    // Resultaten dialoogvenster openen
    document.getElementById('resultatenknop').addEventListener('click', this.resultaten_knop_handler.bind(this));
  }

  /**
   * 
   * @param {number} lijst_id 
   * @return {Promise<void>}
   */
  async set_lijst(lijst_id) {
    this.lijst_id = lijst_id;
    let url = new URL(location.href);
    let params = url.searchParams;
    params.set('lijst', this.lijst_id);
    url.params = params;
    window.history.replaceState(null, null, url);

    this.e_body.classList.add('lijst-geselecteerd');
    for ( const bewerkknop of document.getElementsByClassName('bewerk-knop') ) {
      bewerkknop.removeAttribute('title');
    }

    const lijst_data_promise = this.vul_lijst_metadata();
    this.lijst_naam_promise = lijst_data_promise.then((lijst_data) => {
      return lijst_data.naam;
    });
    await lijst_data_promise;

    this.tabel.draw();
  }

  async vul_metadata() {
    const data = await server.post('get_metadata', {});
    for ( const e_organisatie of document.getElementsByClassName('organisatie') ) {
      e_organisatie.innerText = data.organisatie;
    }
    for ( const lijst of data.lijsten ) {
      this.e_lijst_select.add(new Option(lijst.naam, lijst.id));
    }
    document.getElementById('totaal-aantal-nummers').innerText = data.totaal_aantal_nummers;
    for ( const e_nimbus_url of document.getElementsByClassName('nimbus-url') ) {
      e_nimbus_url.href = data.nimbus_url;
    }
  }

  /**
   * 
   * @returns {Promise<unknown>}
   */
  async vul_lijst_metadata() {
    const data = await server.post('get_lijst_metadata', {
      lijst: this.lijst_id
    });
    for ( const e_naam of document.getElementsByClassName('lijst-naam') ) {
      e_naam.innerText = data.naam;
    }
    document.getElementsByTagName('title').item(0).innerText = `Muzieklijsten beheer – ${data.naam}`;
    for ( const e_iframe_url of document.getElementsByClassName('iframe-url') ) {
      e_iframe_url.value = data.iframe_url;
    }
    for ( const e_iframe_code of document.getElementsByClassName('iframe-code') ) {
      e_iframe_code.value = `<iframe src="${data.iframe_url}" frameborder="0" height="3000" style="width: 100%; height: 3000px; border: none;">`;
    }
    this.geselecteerde_nummers = data.nummer_ids;
    // Vult de tabel met geselecteerde nummers.
    this.vul_lijst_geselecteerde_nummers();
    $('#beschikbare-nummers_length select').addClass('form-control');
    $('#beschikbare-nummers_filter input').addClass('form-control');
    return data;
  }

  async checkbox_handler(e) {
    e.preventDefault();

    let $row = $(e.target).closest('tr');
    let $checkbox = $row.find('input[type="checkbox"]').addBack('input[type="checkbox"]');
    let nummer_id, titel, artiest, jaar;
    [nummer_id, titel, artiest, jaar] = this.tabel.row($row).data();

    // Determine whether row ID is in the list of selected row IDs 
    let index = $.inArray(nummer_id, this.geselecteerde_nummers);

    if ( index === -1 ) {
      try {
        await this.nummer_toevoegen(nummer_id);
        this.geselecteerde_nummers.push(nummer_id);
        $checkbox.prop('checked', true);
        $row.addClass('selected');
        await this.vul_lijst_geselecteerde_nummers();
      } catch (msg) {
        $checkbox.prop('checked', false);
        alert(msg);
      }
    } else {
      try {
        await this.nummer_verwijderen(nummer_id);
        this.geselecteerde_nummers.splice(index, 1);
        $checkbox.prop('checked', false);
        $row.removeClass('selected');
        await this.vul_lijst_geselecteerde_nummers();
      } catch (msg) {
        $checkbox.prop('checked', true);
        alert(msg);
      }
    }
  }

  /**
   * Gebruiker kiest een lijst in de dropdown.
   */
  lijst_select_handler(e) {
    if ( e.target.value > 0 ) {
      this.set_lijst(e.target.value);
    } else {
      this.unset_lijst();
    }
  }

  /**
   * Callback bij het renderen van elke rij.
   * Zet het vinkje geselecteerd aan of niet.
   */
  toon_geselecteerd(row, [nummer_id, titel, artiest, jaar], dataIndex) {
    // If row ID is in the list of selected row IDs
    if ($.inArray(nummer_id, this.geselecteerde_nummers) !== -1) {
      $(row).find('input[type="checkbox"]').prop('checked', true);
      $(row).addClass('selected');
    }
  }

  /**
   * Voegt een nummer toe aan de lijst.
   * @returns {Promise<void>}
   */
  nummer_toevoegen(nummer_id) {
    return server.post('lijst_nummer_toevoegen', {
      lijst: this.lijst_id,
      nummer: nummer_id
    });
  }

  /**
   * Verwijdert een nummer uit de lijst.
   * @returns {Promise<void>}
   */
  nummer_verwijderen(nummer_id) {
    return server.post('lijst_nummer_verwijderen', {
      lijst: this.lijst_id,
      nummer: nummer_id
    });
  }

  /**
   * Vult de rechterkolom met alle geselecteerde nummers.
   * De hele inhoud van het element wordt vervangen.
   */
  async vul_lijst_geselecteerde_nummers() {
    const nummers = await server.post('get_geselecteerde_nummers', {
      lijst: this.lijst_id
    });
    while ( this.e_geselecteerd_lijst.lastChild ) {
      this.e_geselecteerd_lijst.removeChild(this.e_geselecteerd_lijst.lastChild);
    }
    this.e_aantal_geselecteerde_nummers.innerText = nummers.length;
    for (const nummer of nummers) {
      let e_tr = document.createElement('tr');
      this.e_geselecteerd_lijst.appendChild(e_tr);
      let e_titel = document.createElement('td');
      e_tr.appendChild(e_titel);
      e_titel.appendChild(document.createTextNode(nummer.titel));
      let e_artiest = document.createElement('td');
      e_tr.appendChild(e_artiest);
      e_artiest.appendChild(document.createTextNode(nummer.artiest));
      let e_jaar = document.createElement('td');
      e_tr.appendChild(e_jaar);
      e_jaar.appendChild(document.createTextNode(nummer.jaar ?? ''));
    }
  }

  /**
   * 
   * @param {Event} e 
   */
  async resultaten_knop_handler(e) {
    e.preventDefault();
    new ResultatenModal(this.lijst_id, await this.lijst_naam_promise);
  }

  nieuw_knop_handler(e) {
    e.preventDefault();
    let modal = new BeheerModal();
    modal.on_lijst_gemaakt.on(this.lijst_toegevoegd.bind(this));
  }

  beheer_knop_handler(e) {
    e.preventDefault();
    let modal = new BeheerModal(this.lijst_id);
    modal.on_lijst_veranderd.on(this.lijst_veranderd.bind(this));
    modal.on_lijst_verwijderd.on(this.lijst_verwijderd.bind(this));
  }

  /**
   * 
   * @param {{id: number, naam: string}} data 
   */
  lijst_toegevoegd(data) {
    this.e_lijst_select.add(new Option(data.naam, data.id, false, true));
    this.set_lijst(data.id);
  }

  /**
   * 
   * @param {{id: number, naam: string}} data 
   */
  lijst_veranderd(data) {
    const e_option = this.e_lijst_select.item(this.e_lijst_select.selectedIndex);
    e_option.text = data.naam;
  }

  lijst_verwijderd() {
    this.e_lijst_select.remove(this.e_lijst_select.selectedIndex);
    this.unset_lijst();
  }

  unset_lijst(event) {
    this.lijst_id = undefined;

    let url = new URL(location.href);
    let params = url.searchParams;
    params.delete('lijst');
    url.params = params;
    window.history.replaceState(null, null, url);

    this.e_body.classList.remove('lijst-geselecteerd');

    document.getElementsByTagName('title').item(0).innerText = 'Muzieklijsten beheer';
    this.geselecteerde_nummers = [];
    // Vult de tabel met geselecteerde nummers.
    this.vul_lijst_geselecteerde_nummers();
  }
}

class ResultatenModal {

  /** @type {number} */
  lijst_id;
  /** @type {HTMLDivElement} */
  e_modal;
  /** @type {jQuery} */
  $modal;
  /** @type {HTMLSpanElement} */
  e_totaal_aantal_stemmen;
  /** @type {HTMLSpanElement} */
  e_totaal_aantal_stemmers;
  /** @type {HTMLFormElement} */
  e_filters_form;
  /** @type {HTMLTableSectionElement} */
  e_resultaten_tabel;
  /** @type {{[key: number]: ResultatenNummer}} */
  resultaten_nummers;
  /** @type {number} */
  totaal_aantal_stemmen;

  /**
   * 
   * @param {number} lijst_id 
   * @param {string} lijst_naam 
   */
  constructor(lijst_id, lijst_naam) {
    this.lijst_id = lijst_id;
    this.resultaten_nummers = {};
    this.totaal_aantal_stemmen = 0;

    this.e_modal = modal_template.cloneNode(true);
    this.e_totaal_aantal_stemmen = this.e_modal.getElementsByClassName('totaal-aantal-stemmen').item(0);
    this.e_totaal_aantal_stemmers = this.e_modal.getElementsByClassName('totaal-aantal-stemmers').item(0);
    this.e_filters_form = this.e_modal.querySelector('form.filters');
    this.e_filters_form.addEventListener('submit', this.filter.bind(this));
    this.e_filters_form.addEventListener('reset', this.filter_reset.bind(this));
    this.e_resultaten_tabel = this.e_modal.querySelector('.resultaten-tabel');
    this.e_resultaten_tabel.id = functies.get_random_string(12);

    this.set_totaal_aantal_stemmers();
    this.maak_resultaten_tabel();

    for ( const e_lijst_naam of this.e_modal.querySelectorAll('.lijst-naam') ) {
      e_lijst_naam.textContent = lijst_naam;
    }

    document.getElementsByTagName('body').item(0).appendChild(this.e_modal);
    //// Voor bootstrap 5
    // this.modal = new Modal(this.e_modal, {
    //   backdrop: true,
    //   focus: true,
    //   keyboard: true
    // });
    // this.e_modal.addEventListener('hidden.bs.modal', e => {
    //   this.e_modal.remove();
    // });
    this.$modal = $(this.e_modal);
    this.$modal.modal({
        backdrop: true,
        focus: true,
        keyboard: true
      });
    this.$modal.on('hidden.bs.modal', e => {
      this.e_modal.remove();
    });

    this.e_modal.addEventListener('click', this.click_handler.bind(this));
    this.e_modal.addEventListener('change', this.change_handler.bind(this));
    
    this.$modal.modal('show');
  }

  async maak_resultaten_tabel() {
    let labels_promise = server.post('get_resultaten_labels', {
      lijst: this.lijst_id
    });
    let nummers_stemmen = await server.post('get_resultaten', {
      lijst: this.lijst_id
    });
    this.totaal_aantal_stemmen = 0;
    for ( const nummer_stemmen of nummers_stemmen ) {
      let resultaten_nummer = new ResultatenNummer(this, this.e_resultaten_tabel);
      resultaten_nummer.on_stem_verwijderd.on(this.stem_verwijderd_handler.bind(this));
      resultaten_nummer.on_verwijderd.on(this.nummer_verwijderd_handler.bind(this));
      resultaten_nummer.maak_velden_labels(labels_promise);
      resultaten_nummer.set_nummer(nummer_stemmen.nummer);
      resultaten_nummer.maak_stemmen(nummer_stemmen.stemmen);
      this.resultaten_nummers[nummer_stemmen.nummer.id] = resultaten_nummer;
      this.add_totaal_aantal_stemmen(nummer_stemmen.stemmen.length);
    }
  }

  /**
   * 
   * @param {number} aantal 
   */
  add_totaal_aantal_stemmen(aantal) {
    this.totaal_aantal_stemmen += aantal;
    this.e_totaal_aantal_stemmen.innerText = this.totaal_aantal_stemmen;
  }

  async set_totaal_aantal_stemmers() {
    // const van = this.get_van();
    // const tot = this.get_tot();
    const request = {lijst: this.lijst_id};
    // if ( van != null ) {
    //   request.van = van.toISOString();
    // }
    // if ( tot != null ) {
    //   request.tot = tot.toISOString();
    // }
    this.e_totaal_aantal_stemmers.innerText = await server.post(
      'get_totaal_aantal_stemmers',
      request
    );
  }

  stem_verwijderd_handler() {
    this.add_totaal_aantal_stemmen(-1);
    this.set_totaal_aantal_stemmers();
  }

  /**
   * 
   * @param {number} aantal_stemmen 
   */
  nummer_verwijderd_handler(aantal_stemmen) {
    this.add_totaal_aantal_stemmen(-aantal_stemmen);
    this.set_totaal_aantal_stemmers();
  }

  /**
   * 
   * @param {Event} event 
   */
  async filter(event) {
    event.preventDefault();
    let nummers_tekst = this.e_filters_form.elements['filter-nummers'].value.toLowerCase();
    let stemmers_tekst = this.e_filters_form.elements['filter-stemmers'].value.toLowerCase();
    if ( nummers_tekst.length < 3 ) {
      nummers_tekst = '';
      this.e_filters_form.elements['filter-nummers'].value = '';
    }
    if ( stemmers_tekst.length < 3 ) {
      stemmers_tekst = '';
      this.e_filters_form.elements['filter-stemmers'].value = '';
    }
    for ( const [nummer_id, resultaten_nummer] of Object.entries(this.resultaten_nummers) ) {
      resultaten_nummer.filter(
        nummers_tekst,
        stemmers_tekst,
        this.get_van(),
        this.get_tot()
      );
    }
    // await this.set_totaal_aantal_stemmers();
  }

  /**
   * 
   * @param {Event} event 
   */
  filter_reset(event) {
    for ( const element of this.e_filters_form.elements ) {
      if ( element.type === 'text' || element.type === 'date' ) {
        element.value = '';
      }
    }
    this.filter(event);
  }

  /**
   * Handler voor alle kliks in het modal.
   * @param {Event} event 
   */
  click_handler(event) {
    const target = event.target;
    if ( !(target instanceof HTMLElement) ) {
      return;
    }
    if ( target.closest('.stem-verwijderen') ) {
      this.stem_verwijderen_handler(target.closest('.stem-verwijderen'));
    }
  }

  /**
   * Handler voor alle change events in het modal.
   * @param {Event} event 
   */
  change_handler(event) {
    const target = event.target;
    if ( !(target instanceof HTMLElement) ) {
      return;
    }
    if ( target.closest('.behandeld-check') ) {
      this.check_behandeld_handler(target.closest('.behandeld-check'));
    }
  }

  /**
   * Handler voor een klik op het kruisje om een stem te verwijderen.
   * @param {HTMLInputElement} elem - Knop waarop geklikt is.
   */
  async stem_verwijderen_handler(elem) {
    const stemmer_id = Number.parseInt(elem.closest('[data-stemmer-id]').getAttribute('data-stemmer-id'));
    const nummer_id = Number.parseInt(elem.closest('[data-nummer-id]').getAttribute('data-nummer-id'));
    /** @type {ResultatenNummer} */
    const resultaten_nummer = this.resultaten_nummers[nummer_id];
    const resultaten_stem = resultaten_nummer.get_stem(stemmer_id);
    await resultaten_stem.verwijderen();
  }
  
  /**
   * Change event op een vinkhokje is_behandeld.
   * @param {HTMLInputElement} elem - Vinkhokje dat veranderd is.
   */
  async check_behandeld_handler(elem) {
    const stemmer_id = Number.parseInt(elem.closest('[data-stemmer-id]').getAttribute('data-stemmer-id'));
    const nummer_id = Number.parseInt(elem.closest('[data-nummer-id]').getAttribute('data-nummer-id'));
    /** @type {ResultatenNummer} */
    const resultaten_nummer = this.resultaten_nummers[nummer_id];
    const resultaten_stem = resultaten_nummer.get_stem(stemmer_id);
    await resultaten_stem.behandeld_handler();
  }

  /**
   * @returns {Date|null}
   */
  get_van() {
    const van = new Date(this.e_filters_form.elements.van.value);
    if ( isNaN(van) ) {
      return null;
    } else {
      van.setHours(0);
      van.setMinutes(0);
      van.setSeconds(0);
      van.setMilliseconds(0);
      return van;
    }
  }

  /**
   * @returns {Date|null}
   */
  get_tot() {
    const tot = new Date(this.e_filters_form.elements.tot.value);
    if ( isNaN(tot) ) {
      return null;
    } else {
      tot.setDate(tot.getDate() + 1);
      tot.setHours(0);
      tot.setMinutes(0);
      tot.setSeconds(0);
      tot.setMilliseconds(0);
      return tot;
    }
}
  
}

class ResultatenNummer {

  /** @type {ResultatenModal} */
  resultaten_modal;
  /** @type {HTMLTableSectionElement} */
  e_container;
  /** @type {{[key: number]: ResultatenStem}} */
  resultaten_stemmen;
  /** @type {HTMLTableRowElement} */
  e_tr_uitklap;
  /** @type {HTMLTableRowElement} */
  e_tr_gegevens;
  /** @type {HTMLTableCellElement} */
  e_aantal_stemmen;
  /** @type {number} */
  nummer_id;
  /** @type {string} */
  titel;
  /** @type {string} */
  artiest;
  /** @type {TypedEvent<void>} */
  on_stem_verwijderd;
  /** @type {TypedEvent<number>} */
  on_verwijderd;
  /** @type {boolean} */
  is_zichtbaar;

  /**
   * 
   * @param {ResultatenModal} resultaten_modal 
   * @param {HTMLTableSectionElement} e_container 
   */
  constructor(resultaten_modal, e_container) {
    this.resultaten_stemmen = {};
    this.on_stem_verwijderd = new TypedEvent();
    this.on_verwijderd = new TypedEvent();
    this.resultaten_modal = resultaten_modal;
    this.e_container = e_container;
    this.resultaten_stemmen = [];
    this.is_zichtbaar = true;
    this.e_tr_uitklap = resultaten_nummer_template.item(0).cloneNode(true);
    this.e_tr_gegevens = resultaten_nummer_template.item(1).cloneNode(true);

    this.e_aantal_stemmen = this.e_tr_uitklap.getElementsByClassName('aantal-stemmen').item(0);

    // this.e_tr_uitklap.getElementsByClassName('verwijder-nummer').item(0).addEventListener('click', this.verwijderen.bind(this));

    this.e_container.appendChild(this.e_tr_uitklap);
    this.e_container.appendChild(this.e_tr_gegevens);
  }

  set_nummer({id, titel, artiest, is_vrijekeuze}) {
    this.nummer_id = id;
    this.titel = titel;
    this.artiest = artiest;

    this.e_tr_gegevens.id = `nummer-stemmers-${this.nummer_id}`;
    this.e_tr_uitklap.id = `nummer-${this.nummer_id}`;
    this.e_tr_uitklap.setAttribute('data-target', `#${this.e_tr_gegevens.id}`);
    this.e_tr_gegevens.setAttribute('data-nummer-id', this.nummer_id);
    this.e_tr_uitklap.setAttribute('data-nummer-id', this.nummer_id);

    for ( const e_nummer_titel of this.e_tr_uitklap.getElementsByClassName('nummer-titel') ) {
      e_nummer_titel.appendChild(document.createTextNode(this.titel));
    }
    for ( const e_nummer_artiest of this.e_tr_uitklap.getElementsByClassName('nummer-artiest') ) {
      e_nummer_artiest.appendChild(document.createTextNode(this.artiest));
    }
    if ( is_vrijekeuze ) {
      this.e_tr_uitklap.classList.add('vrijekeuze');
    }
  }

  /**
   * 
   * @param {Promise} labels_promise
   */
  async maak_velden_labels(labels_promise) {
    const e_container = this.e_tr_gegevens.querySelector('thead tr');
    const e_insert_before = e_container.firstChild;
    const labels = await labels_promise;
    for ( const label of labels ) {
      const e_label = document.createElement('th');
      e_label.appendChild(document.createTextNode(label));
      e_container.insertBefore(e_label, e_insert_before);
    }
  }

  maak_stemmen(stemmen) {
    const e_tbody = this.e_tr_gegevens.getElementsByTagName('tbody').item(0);
    if ( stemmen.length > 0 ) {
      this.e_tr_uitklap.classList.add('heeft-stemmen');
    }
    for ( const stem of stemmen ) {
      let resultaten_stem = new ResultatenStem(this, e_tbody, stem);
      resultaten_stem.on_change_is_behandeld.on(this.update_behandeld.bind(this));
      resultaten_stem.on_verwijderd.on(this.stem_verwijderd_handler.bind(this, resultaten_stem));
      this.resultaten_stemmen[stem.stemmer_id] = resultaten_stem;
    }
    this.update_aantal_stemmen();
    this.update_behandeld();
  }

  update_behandeld() {
    let is_behandeld;
    for ( const [stemmer_id, resultaten_stem] of Object.entries(this.resultaten_stemmen) ) {
      is_behandeld ??= resultaten_stem.is_behandeld;
      is_behandeld &= resultaten_stem.is_behandeld;
      if ( !is_behandeld ) {
        break;
      }
    }
    if ( is_behandeld ) {
      this.e_tr_uitklap.classList.add('success');
    } else {
      this.e_tr_uitklap.classList.remove('success');
    }
  }

  /**
   * 
   * @param {ResultatenStem} resultaten_stem 
   */
  stem_verwijderd_handler(resultaten_stem) {
    delete this.resultaten_stemmen[resultaten_stem.stemmer_id];
    this.on_stem_verwijderd.emit();
    this.update_aantal_stemmen();
    if ( this.get_aantal_stemmen() === 0 ) {
      // Nummer heeft geen stemmen meer.
      this.e_tr_gegevens.remove();
      this.e_tr_uitklap.remove();
    }
  }

  /**
   * @return {ResultatenStem[]}
   */
  get_stemmen() {
    return Object.values(this.resultaten_stemmen);
  }

  /**
   * 
   * @returns {number}
   */
  get_aantal_stemmen() {
    // if ( !this.is_zichtbaar ) {
    //   return 0;
    // }
    // const zichtbare_stemmen = this.get_stemmen().filter(stem => stem.is_zichtbaar);
    // return zichtbare_stemmen.length;
    return this.get_stemmen().length;
  }

  update_aantal_stemmen() {
    this.e_aantal_stemmen.innerText = this.get_aantal_stemmen();
  }

  // async verwijderen(e) {
  //   e.stopPropagation();
  //   if ( confirm('Dit nummer, inclusief alle stemmen hierop, verwijderen uit de stemlijst?') ) {
  //     const aantal_stemmen = this.get_aantal_stemmen();
  //     await server.post('verwijder_nummer', {
  //       lijst: this.lijst_id,
  //       nummer: this.nummer_id
  //     });
  //     this.e_tr_gegevens.remove();
  //     this.e_tr_uitklap.remove();
  //     this.on_verwijderd.emit(aantal_stemmen);
  //   }
  // }

  /**
   * 
   * @param {string} nummers_tekst 
   * @param {string} stemmers_tekst 
   * @param {Date|null} van 
   * @param {Date|null} tot 
   */
  filter(
    nummers_tekst,
    stemmers_tekst,
    van,
    tot
  ) {
    // Filter het nummer.
    this.is_zichtbaar =
      nummers_tekst == ''
      || this.titel.toLowerCase().includes(nummers_tekst)
      || this.artiest.toLowerCase().includes(nummers_tekst);

    if ( this.is_zichtbaar ) {
      // Filter de stemmen.
      let stem_zichtbaar = false; // Of er tenminste één stem zichtbaar is.
      for ( const [stemmer_id, resultaten_stem] of Object.entries(this.resultaten_stemmen) ) {
        resultaten_stem.filter(stemmers_tekst, van, tot);
        stem_zichtbaar |= resultaten_stem.is_zichtbaar;
      }
      this.is_zichtbaar &= stem_zichtbaar;
    }

    if ( this.is_zichtbaar ) {
      this.e_tr_uitklap.classList.remove('verborgen');
    } else {
      this.inklappen();
      this.e_tr_uitklap.classList.add('verborgen');
    }
  }

  inklappen() {
    $(this.e_tr_gegevens).collapse('hide');
  }

  uitklappen() {
    $(this.e_tr_gegevens).collapse('show');
  }

  /**
   * @param {number} stemmer_id
   * @returns {ResultatenStem}
   */
  get_stem(stemmer_id) {
    return this.resultaten_stemmen[stemmer_id];
  }

}

/**
 * Stem per nummer
 */
class ResultatenStem {

  /** @type {ResultatenNummer} */
  resultaten_nummer;
  /** @type {HTMLTableRowElement} */
  e_tr;
  /** @type {HTMLInputElement} */
  e_behandeld_input;
  /** @type {boolean} */
  is_behandeld;
  /** @type {number} */
  stemmer_id;
  /** @type {Array.<string>} */
  metadata_voor_filter;
  /** @type {Date} */
  timestamp;
  /** @type {TypedEvent<boolean>} */
  on_change_is_behandeld;
  /** @type {TypedEvent<void>} */
  on_verwijderd;
  /** @type {boolean} */
  is_zichtbaar;

  /**
   * 
   * @param {ResultatenNummer} resultaten_nummer 
   * @param {HTMLElement} e_container 
   * @param {any} param3 
   */
  constructor(
    resultaten_nummer,
    e_container,
    {
      stemmer_id,
      ip,
      is_behandeld,
      toelichting,
      timestamp,
      velden
    }) {
    this.on_change_is_behandeld = new TypedEvent();
    this.on_verwijderd = new TypedEvent();
    this.resultaten_nummer = resultaten_nummer;
    this.stemmer_id = stemmer_id;
    this.metadata_voor_filter = [];
    this.timestamp = new Date(timestamp);
    this.is_behandeld = is_behandeld;
    this.is_zichtbaar = true;
    this.e_tr = resultaten_stem_template.cloneNode(true);
    this.e_tr.setAttribute('data-stemmer-id', this.stemmer_id);
    this.e_behandeld_input = this.e_tr.getElementsByTagName('input').item(0);
    this.e_behandeld_input.checked = this.is_behandeld;
    this.update_behandeld();
    for ( const e_timestamp of this.e_tr.getElementsByClassName('stemmer-timestamp') ) {
      e_timestamp.appendChild(document.createTextNode(
          this.timestamp.toLocaleString('nl-NL', {
            dateStyle: "short",
            timeStyle: 'short'
          })
      ));
    }
    for ( const e_toelichting of this.e_tr.getElementsByClassName('stem-toelichting') ) {
      e_toelichting.appendChild(document.createTextNode(toelichting));
    }
    this.maak_velden(velden);
    this.e_tr.firstChild.setAttribute('title', ip);

    e_container.appendChild(this.e_tr);
  }

  maak_velden(velden) {
    const e_insert_before = this.e_tr.firstChild;
    for ( const item of velden ) {
      this.metadata_voor_filter.push(item.waarde);
      let e_td = document.createElement('td');
      if ( item.waarde !== null && item.waarde !== '' ) {
        if ( item.type === 'email' || item.type === 'tel' ) {
          let e_a = document.createElement('a');
          let prefix = '';
          if ( item.type === 'email' ) {
            prefix = 'mailto:';
          }
          if ( item.type === 'tel' ) {
            prefix = 'tel:';
          }
          e_a.setAttribute('href', `${prefix}${item.waarde}`);
          let tekst;
          if ( item.type === 'tel' ) {
            tekst = functies.format_telefoonnummer(item.waarde);
          } else {
            tekst = item.waarde;
          }
          e_a.appendChild(document.createTextNode(tekst));
          e_td.appendChild(e_a);
        } else {
          e_td.appendChild(document.createTextNode(item.waarde));
        }
        if ( item.type === 'email' || item.type === 'tel' || item.type === 'postcode' ) {
          e_td.classList.add('nobreak');
        }
      }
      this.e_tr.insertBefore(e_td, e_insert_before);
    }
  }

  async behandeld_handler() {
    try {
      await server.post('stem_set_behandeld', {
        nummer: this.resultaten_nummer.nummer_id,
        lijst: this.resultaten_nummer.resultaten_modal.lijst_id,
        stemmer: this.stemmer_id,
        waarde: this.e_behandeld_input.checked
      });
      this.is_behandeld = this.e_behandeld_input.checked;
      this.on_change_is_behandeld.emit(this.is_behandeld);
      this.update_behandeld();
    } catch (e) {
      this.e_behandeld_input.checked = !this.e_behandeld_input.checked;
    }
  }

  update_behandeld() {
    if ( this.is_behandeld ) {
      this.e_tr.classList.add('success');
    } else {
      this.e_tr.classList.remove('success');
    }
  }

  async verwijderen() {
    await server.post('verwijder_stem', {
      nummer: this.resultaten_nummer.nummer_id,
      lijst: this.resultaten_nummer.resultaten_modal.lijst_id,
      stemmer: this.stemmer_id
    });
    this.e_tr.remove();
    this.on_verwijderd.emit();
  }

  /**
   * 
   * @param {string} stemmers_tekst 
   * @param {Date|null} van 
   * @param {Date|null} tot 
   */
  filter(stemmers_tekst, van, tot) {
    let tekst_filter_ok = false;
    if ( stemmers_tekst === '' || stemmers_tekst === null ) {
      tekst_filter_ok = true;
    } else {
      for ( const waarde of this.metadata_voor_filter ) {
        if ( typeof waarde === 'string' ) {
          tekst_filter_ok |= waarde.toLowerCase().includes(stemmers_tekst);
        }
        if ( tekst_filter_ok ) {
          break;
        }
      }
    }
    this.is_zichtbaar =
      tekst_filter_ok
      && (
        van === null
        || this.timestamp >= van
      )
      && (
        tot === null
        || this.timestamp <= tot
      )
    if ( this.is_zichtbaar ) {
      this.e_tr.classList.remove('verborgen');
    } else {
      this.e_tr.classList.add('verborgen');
    }
  }

}

class BeheerModal {

  /** @type {?number} */
  lijst_id;
  /** @type {HTMLDivElement} */
  e_modal;
  /** @type {jQuery} */
  $modal;
  /** @type {HTMLFormElement} */
  e_form;
  /** @type {HTMLDivElement} */
  e_velden_zichtbaar_kolom;
  /** @type {HTMLDivElement} */
  e_velden_verplicht_kolom;
  /** @type {TypedEvent<{id: number, naam: string}>} */
  on_lijst_gemaakt;
  /** @type {TypedEvent<{id: number, naam: string}>} */
  on_lijst_veranderd;
  /** @type {TypedEvent<{id: number}>} */
  on_lijst_verwijderd;

  constructor(lijst_id) {
    this.on_lijst_gemaakt = new TypedEvent();
    this.on_lijst_veranderd = new TypedEvent();
    this.on_lijst_verwijderd = new TypedEvent();
    this.lijst_id = lijst_id;

    this.e_modal = beheer_modal_template.cloneNode(true);
    this.e_form = this.e_modal.getElementsByTagName('form').item(0);
    this.e_velden_zichtbaar_kolom = this.e_modal.getElementsByClassName('velden-zichtbaar-kolom').item(0);
    this.e_velden_verplicht_kolom = this.e_modal.getElementsByClassName('velden-verplicht-kolom').item(0);

    if ( this.is_nieuw() ) {
      this.e_modal.classList.add('is-nieuw');
      this.maak_lege_veld_checks();
    } else {
      this.vul_data();
    }
    document.getElementsByTagName('body').item(0).appendChild(this.e_modal);

    // Lijst opslaan
    this.e_form.addEventListener('submit', this.opslaan.bind(this));

    // Lijst verwijderen
    this.e_form.elements['verwijder-lijst'].addEventListener('click', this.verwijder_lijst.bind(this));

    //// Voor bootstrap 5
    // this.modal = new Modal(this.e_modal, {
    //   backdrop: true,
    //   focus: true,
    //   keyboard: true
    // });
    // this.e_modal.addEventListener('hidden.bs.modal', e => {
    //   this.e_modal.remove();
    // });
    this.$modal = $(this.e_modal);
    this.$modal.modal({
        backdrop: true,
        focus: true,
        keyboard: true
      });
    this.$modal.on('hidden.bs.modal', this.destroy.bind(this));
    
    this.$modal.modal('show');
  }

  /**
   * 
   * @returns {boolean}
   */
  is_nieuw() {
    return this.lijst_id === undefined;
  }

  async vul_data() {
    this.e_form.elements.lijst.value = this.lijst_id;
    const data = await server.post('get_beheer_lijstdata', {
      lijst: this.lijst_id
    });

    for ( const e_lijst_naam of this.e_modal.querySelectorAll('.lijst-naam') ) {
      e_lijst_naam.textContent = data.naam;
    }
    this.e_form.elements['is-actief'].checked = data.is_actief;
    this.e_form.elements.naam.value = data.naam;
    this.e_form.elements.minkeuzes.value = data.minkeuzes;
    this.e_form.elements.maxkeuzes.value = data.maxkeuzes;
    this.e_form.elements.vrijekeuzes.value = data.vrijekeuzes;
    this.e_form.elements['stemmen-per-ip'].value = data.stemmen_per_ip;
    this.e_form.elements['artiest-eenmalig'].checked = data.artiest_eenmalig;
    this.e_form.elements['mail-stemmers'].checked = data.mail_stemmers;
    this.e_form.elements['random-volgorde'].checked = data.random_volgorde;
    this.e_form.elements.recaptcha.checked = data.recaptcha;
    this.e_form.elements.email.value = data.email;
    this.e_form.elements['bedankt-tekst'].value = data.bedankt_tekst;

    for ( const veld of data.velden ) {
      this.maak_veld_checks(veld);
    }
  }

  async maak_lege_veld_checks() {
    const velden = await server.post('get_alle_velden', {});
    for ( const veld of velden ) {
      this.maak_veld_checks(veld);
    }
  }

  maak_veld_checks(veld) {
    const zichtbaar_id = functies.get_random_string(16);
    const verplicht_id = functies.get_random_string(16);

    let e_zichtbaar_container = document.createElement('div');
    let e_zichtbaar_label = document.createElement('label');
    let e_zichtbaar_check = document.createElement('input');
    let e_zichtbaar_label_tekst = document.createTextNode(veld.label);
    let e_verplicht_container = document.createElement('div');
    let e_verplicht_label = document.createElement('label');
    let e_verplicht_check = document.createElement('input');
    let e_verplicht_label_tekst = document.createTextNode('Verplicht');

    this.e_velden_zichtbaar_kolom.appendChild(e_zichtbaar_container);
    e_zichtbaar_container.appendChild(e_zichtbaar_label);
    e_zichtbaar_label.appendChild(e_zichtbaar_check);
    e_zichtbaar_label.appendChild(e_zichtbaar_label_tekst);
    this.e_velden_verplicht_kolom.appendChild(e_verplicht_container);
    e_verplicht_container.appendChild(e_verplicht_label);
    e_verplicht_label.appendChild(e_verplicht_check);
    e_verplicht_label.appendChild(e_verplicht_label_tekst);

    e_zichtbaar_container.classList.add('checkbox');
    e_verplicht_container.classList.add('checkbox');
    
    e_zichtbaar_check.type = 'checkbox';
    e_zichtbaar_check.id = zichtbaar_id;
    e_zichtbaar_check.name = `velden[${veld.id}][tonen]`;
    e_zichtbaar_check.checked = veld.tonen;
    e_zichtbaar_check.setAttribute('data-input-verplicht', verplicht_id);
    // Verplicht enablen/disablen na check/uncheck tonen
    e_zichtbaar_check.addEventListener('change', this.check_verplicht.bind(this));
    
    e_zichtbaar_label.for = zichtbaar_id;

    e_verplicht_check.type = 'checkbox';
    e_verplicht_check.id = verplicht_id;
    e_verplicht_check.name = `velden[${veld.id}][verplicht]`;
    e_verplicht_check.checked = veld.verplicht;
    e_verplicht_check.disabled = !veld.tonen;
    
    e_verplicht_label.for = verplicht_id;
  }

  /**
   * Lijst metadata opslaan.
   */
  async opslaan(e) {
    e.preventDefault();
    let fd = new FormData(this.e_form);
    if ( this.is_nieuw() ) {
      try {
        const lijst_id = await server.post('lijst_maken', fd);
        this.on_lijst_gemaakt.emit({
          id: lijst_id,
          naam: fd.get('naam')
        });
        this.$modal.modal('hide');
      } catch (msg) {
        alert(msg);
      }
    } else {
      try {
        await server.post('lijst_opslaan', fd);
        this.on_lijst_veranderd.emit({
          id: this.lijst_id,
          naam: fd.get('naam')
        });
        this.$modal.modal('hide');
      } catch (msg) {
        alert(msg);
      }
    }
  }

  async verwijder_lijst(e) {
    const vraag = 'Deze lijst verwijderen?\nOok alle stemmen op nummers uit deze lijst worden verwijderd.';
    if ( confirm(vraag) ) {
      try {
        await server.post('verwijder_lijst', {lijst: this.lijst_id});
        this.on_lijst_verwijderd.emit({
          id: this.lijst_id
        });
        this.$modal.modal('hide');
      } catch (msg) {
        alert(msg);
      }
    }
  }

  /**
   * Handler voor wanneer er een vinkje verandert in de invoervelden.
   * Wanneer een vinkje wordt uitgezet wordt het bijbehorende 'verplicht'-vinkje
   * uitgezet en disabled.
   * @param {Event} e 
   */
  check_verplicht(e) {
    let verplicht_id = e.target.getAttribute('data-input-verplicht');
    let verplicht_elem = document.getElementById(verplicht_id);
    if ( verplicht_elem instanceof HTMLInputElement ) {
      if ( e.target.checked ) {
        verplicht_elem.disabled = false;
      } else {
        verplicht_elem.disabled = true;
        verplicht_elem.checked = false;
      }
    }
  }

  destroy() {
    this.e_modal.remove();
  }

}

/** @type {HTMLElement} */
const modal_template = functies.get_html_template(html_resultaten_modal).item(0);
/** @type {HTMLCollection} */
const resultaten_nummer_template = functies.get_html_template(html_resultaten_nummer);
/** @type {HTMLTableRowElement} */
const resultaten_stem_template = functies.get_html_template(html_resultaten_stem).item(0);
/** @type {HTMLElement} */
const beheer_modal_template = functies.get_html_template(html_beheer_modal).item(0);

$(document).ready(() => {
  new Main();
});
