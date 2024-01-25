// Libraries css
import 'bootstrap';
import 'datatables.net-dt';

// Project js
import * as functies from './functies.js';

// Libraries css
import 'bootstrap/dist/css/bootstrap.min.css';
import 'font-awesome/css/font-awesome.min.css';
import 'datatables.net-dt/css/jquery.dataTables.min.css';

// Project css
import '../scss/algemeen.scss';
import '../scss/admin.scss';

// HTML
import html_resultaten_modal from '../html/admin-resultaten-modal.html';
import html_resultaten_nummer from '../html/admin-resultaten-nummer.html';
import html_resultaten_stem from '../html/admin-resultaten-stem.html';
import html_beheer_modal from '../html/admin-beheer-modal.html';
import { data } from 'jquery';

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

    this.tabel = $('#beschikbare-nummers').DataTable({
      'processing': true,
      'serverSide': true,
      'ajax': (data, callback, settings) => {
        data.is_vrijekeuze = false;
        functies.vul_datatables(data, callback, settings);
      },
      'columnDefs': [{
        'targets': 0,
        'searchable': false,
        'orderable': false,
        'className': 'dt-body-center',
        'render': (nummer_id, type, [nummer_id2, titel, artiest, jaar], meta) => {
          let input = document.createElement('input');
          input.setAttribute('type', 'checkbox');
          return input.outerHTML;
        }
      }],
      'order': [1, 'asc'],
      'rowCallback': this.toon_geselecteerd.bind(this),
      'language': {
        'lengthMenu': '_MENU_ nummers per pagina',
        'zeroRecords': 'Geen nummers gevonden',
        'info': 'Pagina _PAGE_ van _PAGES_',
        'infoEmpty': 'Geen nummers gevonden',
        'infoFiltered': '(gefilterd van _MAX_ totaal)',
        'search': 'Zoeken:',
        'paginate': {
          'first': 'Eerste',
          'last': 'Laatste',
          'next': 'Volgende',
          'previous': 'Vorige'
        },
      }

    });

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
    const data = await functies.post('get_metadata');
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
    const data = await functies.post('get_lijst_metadata', {'lijst': this.lijst_id});
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

  checkbox_handler(e) {
    e.preventDefault();

    let $row = $(e.target).closest('tr');
    let $checkbox = $row.find('input[type="checkbox"]').addBack('input[type="checkbox"]');
    let nummer_id, titel, artiest, jaar;
    [nummer_id, titel, artiest, jaar] = this.tabel.row($row).data();

    // Determine whether row ID is in the list of selected row IDs 
    let index = $.inArray(nummer_id, this.geselecteerde_nummers);

    if ( index === -1 ) {
      return this.nummer_toevoegen(nummer_id).then(() => {
        this.geselecteerde_nummers.push(nummer_id);
        $checkbox.prop('checked', true);
        $row.addClass('selected');
        return this.vul_lijst_geselecteerde_nummers();
      }, (msg) => {
        $checkbox.prop('checked', false);
        alert(msg);
      });
    } else {
      return this.nummer_verwijderen(nummer_id).then(() => {
        this.geselecteerde_nummers.splice(index, 1);
        $checkbox.prop('checked', false);
        $row.removeClass('selected');
        return this.vul_lijst_geselecteerde_nummers();
      }, (msg) => {
        $checkbox.prop('checked', true);
        alert(msg);
      });
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
   */
  nummer_toevoegen(nummer_id) {
    return functies.post('lijst_nummer_toevoegen', {
      'lijst': this.lijst_id,
      'nummer': nummer_id
    });
  }

  /**
   * Verwijdert een nummer uit de lijst.
   */
  nummer_verwijderen(nummer_id) {
    return functies.post('lijst_nummer_verwijderen', {
      'lijst': this.lijst_id,
      'nummer': nummer_id
    });
  }

  /**
   * Vult de rechterkolom met alle geselecteerde nummers.
   * De hele inhoud van het element wordt vervangen.
   */
  vul_lijst_geselecteerde_nummers() {
    let nummers_promise = functies.get_geselecteerde_nummers(this.lijst_id);
    while ( this.e_geselecteerd_lijst.firstChild ) {
      this.e_geselecteerd_lijst.removeChild(this.e_geselecteerd_lijst.firstChild);
    }
    return nummers_promise.then((nummers) => {
      this.e_aantal_geselecteerde_nummers.innerText = nummers.length;
      for ( const nummer of nummers ) {
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
    });
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
    modal.eventer.addEventListener('lijst_gemaakt', this.lijst_veranderd.bind(this, true));
  }

  beheer_knop_handler(e) {
    e.preventDefault();
    let modal = new BeheerModal(this.lijst_id);
    modal.eventer.addEventListener('lijst_veranderd', this.lijst_veranderd.bind(this, false));
    modal.eventer.addEventListener('lijst_verwijderd', this.lijst_verwijderd.bind(this));
  }

  /**
   * 
   * @param {boolean} is_nieuw 
   * @param {Event} event 
   */
  lijst_veranderd(is_nieuw, event) {
    if ( is_nieuw ) {
      this.e_lijst_select.add(new Option(event.detail.naam, event.detail.id, false, true));
      this.set_lijst(event.detail.id);
    } else {
      const e_option = this.e_lijst_select.item(this.e_lijst_select.selectedIndex);
      e_option.text = event.detail.naam;
    }
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
  /** @type {Array.<ResultatenNummer>} */
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
    this.resultaten_nummers = [];
    this.totaal_aantal_stemmen = 0;

    this.e_modal = functies.get_html_template(html_resultaten_modal).item(0);
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
    //   'backdrop': true,
    //   'focus': true,
    //   'keyboard': true
    // });
    // this.e_modal.addEventListener('hidden.bs.modal', e => {
    //   this.e_modal.parentNode.removeChild(this.e_modal);
    // });
    this.$modal = $(this.e_modal);
    this.$modal.modal({
        'backdrop': true,
        'focus': true,
        'keyboard': true
      });
    this.$modal.on('hidden.bs.modal', e => {
      this.e_modal.parentNode.removeChild(this.e_modal);
    });
    
    this.$modal.modal('show');
  }

  async maak_resultaten_tabel() {
    let labels_promise = functies.post('get_resultaten_labels', {
      'lijst': this.lijst_id
    });
    let nummers_stemmen = await functies.post('get_resultaten', {
      'lijst': this.lijst_id
    });
    for ( const nummer_stemmen of nummers_stemmen ) {
      let resultaten_nummer = new ResultatenNummer(this, this.e_resultaten_tabel);
      resultaten_nummer.eventer.addEventListener('stem_verwijderd', this.stem_verwijderd_handler.bind(this));
      resultaten_nummer.eventer.addEventListener('verwijderd', this.nummer_verwijderd_handler.bind(this));
      resultaten_nummer.maak_velden_labels(labels_promise);
      resultaten_nummer.set_nummer(nummer_stemmen.nummer);
      resultaten_nummer.maak_stemmen(nummer_stemmen.stemmen);
      this.resultaten_nummers.push(resultaten_nummer);
      this.add_totaal_aantal_stemmen(nummer_stemmen.stemmen.length);
    }
  }

  add_totaal_aantal_stemmen(aantal) {
    this.totaal_aantal_stemmen += aantal;
    this.e_totaal_aantal_stemmen.innerText = this.totaal_aantal_stemmen;
  }

  async set_totaal_aantal_stemmers() {
    this.e_totaal_aantal_stemmers.innerText = await functies.get_totaal_aantal_stemmers(this.lijst_id);
  }

  /**
   * 
   * @param {Event} e 
   */
  stem_verwijderd_handler(e) {
    this.add_totaal_aantal_stemmen(-1);
    this.set_totaal_aantal_stemmers();
  }

  /**
   * 
   * @param {Event} e 
   */
  nummer_verwijderd_handler(e) {
    const aantal_stemmen = e.detail;
    this.add_totaal_aantal_stemmen(-aantal_stemmen);
    this.set_totaal_aantal_stemmers();
  }

  filter(e) {
    e.preventDefault();
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
    let van = new Date(this.e_filters_form.elements.van.value);
    let tot = new Date(this.e_filters_form.elements.tot.value);
    if ( isNaN(van) ) {
      van = null;
    } else {
      van.setHours(0);
      van.setMinutes(0);
      van.setSeconds(0);
      van.setMilliseconds(0);
    }
    if ( isNaN(tot) ) {
      tot = null;
    } else {
      tot.setDate(tot.getDate() + 1);
      tot.setHours(0);
      tot.setMinutes(0);
      tot.setSeconds(0);
      tot.setMilliseconds(0);
    }
    for ( const resultaten_nummer of this.resultaten_nummers ) {
      resultaten_nummer.filter(nummers_tekst, stemmers_tekst, van, tot);
    }
  }

  filter_reset(e) {
    for ( const element of this.e_filters_form.elements ) {
      if ( element.type === 'text' || element.type === 'date' ) {
        element.value = '';
      }
    }
    this.filter(e);
  }
  
}

class ResultatenNummer {

  /** @type {ResultatenModal} */
  resultaten_modal;
  /** @type {HTMLTableSectionElement} */
  e_container;
  /** @type {HTMLUnknownElement} */
  eventer;
  /** @type {Array.<ResultatenStem>} */
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

  /**
   * 
   * @param {ResultatenModal} resultaten_modal 
   * @param {HTMLTableSectionElement} e_container 
   */
  constructor(resultaten_modal, e_container) {
    this.resultaten_modal = resultaten_modal;
    this.e_container = e_container;
    this.eventer = document.createElement(null);
    this.resultaten_stemmen = [];
    const template_elems = functies.get_html_template(html_resultaten_nummer);
    this.e_tr_uitklap = template_elems.item(0);
    this.e_tr_gegevens = template_elems.item(1);

    const collapse_id = functies.get_random_string(12);
    this.e_tr_uitklap.setAttribute('data-target', `#${collapse_id}`);
    // this.e_tr_uitklap.setAttribute('data-parent', `#${this.e_container.id}`);
    // this.e_tr_gegevens.setAttribute('data-parent', `#${this.e_container.id}`);
    this.e_tr_gegevens.id = collapse_id;
    // this.e_tr_gegevens.setAttribute('aria-labelledby', `uitklaprij-${this.nummer_id}`);

    this.e_aantal_stemmen = this.e_tr_uitklap.getElementsByClassName('aantal-stemmen').item(0);

    // this.e_tr_uitklap.getElementsByClassName('verwijder-nummer').item(0).addEventListener('click', this.verwijderen.bind(this));

    this.e_container.appendChild(this.e_tr_uitklap);
    this.e_container.appendChild(this.e_tr_gegevens);
  }

  set_nummer({id, titel, artiest, is_vrijekeuze}) {
    this.nummer_id = id;
    this.titel = titel;
    this.artiest = artiest;

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
      resultaten_stem.eventer.addEventListener('change_is_behandeld', this.update_behandeld.bind(this));
      resultaten_stem.eventer.addEventListener('verwijderd', this.stem_verwijderd_handler.bind(this, resultaten_stem));
      this.resultaten_stemmen.push(resultaten_stem);
    }
    this.update_aantal_stemmen();
    this.update_behandeld();
  }

  update_behandeld(e) {
    let is_behandeld;
    for ( const resultaten_stem of this.resultaten_stemmen ) {
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
   * @param {Event} e 
   */
  stem_verwijderd_handler(resultaten_stem, e) {
    for ( const [i, a_resultaten_stem] of this.resultaten_stemmen.entries() ) {
      if ( resultaten_stem.stemmer_id === a_resultaten_stem.stemmer_id ) {
        this.resultaten_stemmen.splice(i, 1);
        functies.trigger(this.eventer, 'stem_verwijderd');
      }
    }
    this.update_aantal_stemmen();
    if ( this.resultaten_stemmen.length === 0 ) {
      // Nummer heeft geen stemmen meer.
      this.e_tr_gegevens.parentElement.removeChild(this.e_tr_gegevens);
      this.e_tr_uitklap.parentNode.removeChild(this.e_tr_uitklap);
    }
  }

  get_aantal_stemmen() {
    return this.resultaten_stemmen.length;
  }

  update_aantal_stemmen() {
    this.e_aantal_stemmen.innerText = this.get_aantal_stemmen();
  }

  // async verwijderen(e) {
  //   e.stopPropagation();
  //   if ( confirm('Dit nummer, inclusief alle stemmen hierop, verwijderen uit de stemlijst?') ) {
  //     const aantal_stemmen = this.get_aantal_stemmen();
  //     // await functies.verwijder_nummer(this.lijst_id, this.nummer_id);
  //     this.e_tr_gegevens.parentElement.removeChild(this.e_tr_gegevens);
  //     this.e_tr_uitklap.parentNode.removeChild(this.e_tr_uitklap);
  //     functies.trigger(this.eventer, 'verwijderd', aantal_stemmen);
  //   }
  // }

  filter(nummers_tekst, stemmers_tekst, van, tot) {
    // Filter het nummer.
    let nummer_zichtbaar =
      nummers_tekst == ''
      || this.titel.toLowerCase().includes(nummers_tekst)
      || this.artiest.toLowerCase().includes(nummers_tekst);

    if ( nummer_zichtbaar ) {
      // Filter de stemmen.
      let stem_zichtbaar = false; // Of er tenminste één stem zichtbaar is.
      for ( const resultaten_stem of this.resultaten_stemmen ) {
        stem_zichtbaar |= resultaten_stem.filter(stemmers_tekst, van, tot);
      }
      nummer_zichtbaar &= stem_zichtbaar;
    }

    if ( nummer_zichtbaar ) {
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

}

/**
 * Stem per nummer
 */
class ResultatenStem {

  /** @type {ResultatenNummer} */
  resultaten_nummer;
  /** @type {HTMLUnknownElement} */
  eventer;
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

  constructor(resultaten_nummer, e_container, {stemmer_id, ip, is_behandeld, toelichting, timestamp, velden}) {
    this.resultaten_nummer = resultaten_nummer;
    this.eventer = document.createElement(null);
    this.stemmer_id = stemmer_id;
    this.metadata_voor_filter = [];
    this.timestamp = new Date(timestamp);
    this.is_behandeld = is_behandeld;
    this.e_tr = functies.get_html_template(html_resultaten_stem).item(0);
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

    this.e_behandeld_input.addEventListener('change', this.behandeld_handler.bind(this));
    this.e_tr.getElementsByClassName('stem-verwijderen').item(0).addEventListener('click', this.verwijderen.bind(this));

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

  async behandeld_handler(e) {
    try {
      await functies.stem_set_behandeld(
        this.resultaten_nummer.nummer_id,
        this.resultaten_nummer.resultaten_modal.lijst_id,
        this.stemmer_id,
        this.e_behandeld_input.checked
      );
      this.is_behandeld = this.e_behandeld_input.checked;
      functies.trigger(this.eventer, 'change_is_behandeld', this.is_behandeld);
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

  async verwijderen(e) {
    await functies.verwijder_stem(
      this.resultaten_nummer.nummer_id,
      this.resultaten_nummer.resultaten_modal.lijst_id,
      this.stemmer_id
    );
    this.e_tr.parentNode.removeChild(this.e_tr);
    functies.trigger(this.eventer, 'verwijderd');
  }

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
    const filter_ok =
      tekst_filter_ok
      && (
        van === null
        || this.timestamp >= van
      )
      && (
        tot === null
        || this.timestamp <= tot
      )
    if ( filter_ok ) {
      this.e_tr.classList.remove('verborgen');
    } else {
      this.e_tr.classList.add('verborgen');
    }
    return filter_ok;
  }

}

class BeheerModal {

  /** @type {?number} */
  lijst_id;
  /** @type {HTMLUnknownElement} */
  eventer;
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

  constructor(lijst_id) {
    this.lijst_id = lijst_id;
    this.eventer = document.createElement(null);

    this.e_modal = functies.get_html_template(html_beheer_modal).item(0);
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
    //   'backdrop': true,
    //   'focus': true,
    //   'keyboard': true
    // });
    // this.e_modal.addEventListener('hidden.bs.modal', e => {
    //   this.e_modal.parentNode.removeChild(this.e_modal);
    // });
    this.$modal = $(this.e_modal);
    this.$modal.modal({
        'backdrop': true,
        'focus': true,
        'keyboard': true
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
    const data = await functies.post('get_beheer_lijstdata', {'lijst': this.lijst_id});

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
    this.e_form.elements.recaptcha.checked = data.recaptcha;
    this.e_form.elements.email.value = data.email;
    this.e_form.elements['bedankt-tekst'].value = data.bedankt_tekst;

    for ( const veld of data.velden ) {
      this.maak_veld_checks(veld);
    }
  }

  async maak_lege_veld_checks() {
    const velden = await functies.post('get_alle_velden');
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
        const lijst_id = await functies.lijst_maken(fd);
        functies.trigger(this.eventer, 'lijst_gemaakt', {
          'id': lijst_id,
          'naam': fd.get('naam')
        });
        this.$modal.modal('hide');
      } catch (msg) {
        alert(msg);
      }
    } else {
      try {
        await functies.lijst_opslaan(fd);
        functies.trigger(this.eventer, 'lijst_veranderd', {
          'id': this.lijst_id,
          'naam': fd.get('naam')
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
        await functies.verwijder_lijst(this.lijst_id)
        functies.trigger(this.eventer, 'lijst_verwijderd', {
          'id': this.lijst_id
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
    this.e_modal.parentNode.removeChild(this.e_modal);
  }

}

$(document).ready(() => {
  new Main();
});
