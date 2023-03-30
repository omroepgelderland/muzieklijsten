// Libraries css
import 'bootstrap';
import 'datatables.net-dt';

// Project js
import './favicons.js';
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

class Main {

  /** @type {number} */
  lijst_id;
  /** @type {string} */
  lijst_naam;
  geselecteerde_nummers;
  tabel;

  constructor() {

    this.lijst_id = $('body').data('lijst-id');
    this.lijst_naam = $('body').data('lijst-naam');

    this.geselecteerde_nummers = $('body').data('rows-selected');
    this.tabel = $('#beschikbare-nummers').DataTable({
      'processing': true,
      'serverSide': true,
      'ajax': functies.vul_datatables,
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

    // Vult de tabel met geselecteerde nummers.
    this.vul_lijst_geselecteerde_nummers();
    $('#beschikbare-nummers_length select').addClass('form-control');
    $('#beschikbare-nummers_filter input').addClass('form-control');

    $('#lijstselect').on('change', this.set_lijst.bind(this));

    // Bestaande lijst wijzigen
    $('#beheer').on('submit', 'form#beheer-lijst', this.wijzig_lijst.bind(this));

    // Nieuwe lijst maken
    $('#nieuw').on('submit', 'form#beheer-lijst', this.maak_lijst.bind(this));

    // Lijst verwijderen
    $('#beheer').on('click', '#verwijder-lijst', this.verwijder_lijst.bind(this));

    // Verplicht enablen/disablen na check/uncheck tonen
    $('#beheer').on('change', 'form#beheer-lijst input[type="checkbox"]', this.check_verplicht.bind(this));

    // Checks overzetten naar hidden inputs
    $('#beheer').on('change', 'form#beheer-lijst input.check-met-hidden', this.check_met_hidden_handler.bind(this));

    // Resultaten dialoogvenster openen
    document.getElementById('resultaten').addEventListener('click', this.resultaten_knop_handler.bind(this));
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
   * Voert een page reload uit.
   */
  set_lijst(e) {
    let params = new URLSearchParams(location.search);
    params.set('lijst', e.target.value);
    location.search = params;
  }

  /**
   * Lijst metadata opslaan onder knop beheer.
   */
  wijzig_lijst(e) {
    e.preventDefault();
    let fd = new FormData(document.getElementById('beheer-lijst'));
    functies.lijst_opslaan(fd).then(() => {
      $('#beheer').modal('hide');
    }, (msg) => {
      alert(msg);
    });
  }

  /**
   * Nieuwe lijst aanmaken.
   */
  maak_lijst(e) {
    e.preventDefault();
    let fd = new FormData(document.getElementById('beheer-lijst'));
    functies.lijst_maken(fd).then((lijst_id) => {
      this.lijst_id = lijst_id;
      $('#nieuw').modal('hide');
      let params = new URLSearchParams(location.search);
      params.set('lijst', this.lijst_id);
      location.search = params;
    }, (msg) => {
      alert(msg);
    });
  }

  verwijder_lijst(e) {
    const vraag = 'Deze lijst verwijderen?\nOok alle stemmen op nummers uit deze lijst worden verwijderd.';
    if ( confirm(vraag) ) {
      functies.verwijder_lijst(this.lijst_id).then(() => {
        $('#beheer').modal('hide');
        let params = new URLSearchParams(location.search);
        params.set('lijst', '');
        location.search = params;
      }, (msg) => {
        alert(msg);
      });
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
    return functies.get_selected_html(this.lijst_id).then((data) => {
      $('#result').html(data);
    }, (msg) => {
      alert(msg);
    });
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
        const hidden_id = verplicht_elem.getAttribute('data-hidden-id');
        const hidden_elem = document.getElementById(hidden_id);
        hidden_elem.value = false;
      }
    }
  }

  /**
   * 
   * @param {Event} e 
   */
  check_met_hidden_handler(e) {
    const hidden_id = e.target.getAttribute('data-hidden-id');
    const hidden_elem = document.getElementById(hidden_id);
    hidden_elem.value = e.target.checked;
  }

  /**
   * 
   * @param {Event} e 
   */
  resultaten_knop_handler(e) {
    new ResultatenModal(this.lijst_id, this.lijst_naam);
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
      e_lijst_naam.appendChild(document.createTextNode(lijst_naam));
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
    this.$modal = $(this.e_modal).modal({
        'backdrop': true,
        'focus': true,
        'keyboard': true
      });
    this.$modal.on('hidden.bs.modal', e => {
      this.e_modal.parentNode.removeChild(this.e_modal);
    });
    
    this.$modal.show();

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

    this.e_tr_uitklap.getElementsByClassName('verwijder-nummer').item(0).addEventListener('click', this.verwijderen.bind(this));

    this.e_container.appendChild(this.e_tr_uitklap);
    this.e_container.appendChild(this.e_tr_gegevens);
  }

  set_nummer({id, titel, artiest}) {
    this.nummer_id = id;
    this.titel = titel;
    this.artiest = artiest;

    for ( const e_nummer_titel of this.e_tr_uitklap.getElementsByClassName('nummer-titel') ) {
      e_nummer_titel.appendChild(document.createTextNode(this.titel));
    }
    for ( const e_nummer_artiest of this.e_tr_uitklap.getElementsByClassName('nummer-artiest') ) {
      e_nummer_artiest.appendChild(document.createTextNode(this.artiest));
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

  async verwijderen(e) {
    e.stopPropagation();
    if ( confirm('Dit nummer, inclusief alle reacties hierop, verwijderen uit de stemlijst?') ) {
      const aantal_stemmen = this.get_aantal_stemmen();
      // await functies.verwijder_nummer(this.lijst_id, this.nummer_id);
      this.e_tr_gegevens.parentElement.removeChild(this.e_tr_gegevens);
      this.e_tr_uitklap.parentNode.removeChild(this.e_tr_uitklap);
      functies.trigger(this.eventer, 'verwijderd', aantal_stemmen);
    }
  }

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

$(document).ready(() => {
  new Main();
});
