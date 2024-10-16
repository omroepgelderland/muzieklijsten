// Libraries js
import 'bootstrap';
import DataTable, * as datatables from 'datatables.net-dt';

// Project js
import * as server from '@muzieklijsten/server';
import * as functies from '@muzieklijsten/functies';

// css
import '/src/scss/muzieklijst.scss';

// Afbeeldingen
// import '/assets/afbeeldingen/fbshare_top100.jpg';

/**
 * Een door de bezoeker geselecteerd nummer.
 * Maakt een regel in de DOM bij de geselecteerde nummers en inputs in het formulier zodat de nummer-ids bij het stemmen
 * in de formuliervelden staan.
 */
class Nummer {

  /** @type {HTMLTableRowElement} */
  e_tr;

  /**
   * 
   * @param {number} nummer_id 
   * @param {string} titel 
   * @param {string} artiest 
   */
  constructor(nummer_id, titel, artiest) {
    this.e_tr = document.createElement('tr');

    const e_td_titel = document.createElement('td');
    e_td_titel.appendChild(document.createTextNode(titel));
    this.e_tr.appendChild(e_td_titel);

    const e_td_artiest = document.createElement('td');
    e_td_artiest.appendChild(document.createTextNode(artiest));
    this.e_tr.appendChild(e_td_artiest);

    const e_td_toelichting = document.createElement('td');
    e_td_toelichting.classList.add('remark');
    this.e_tr.appendChild(e_td_toelichting);

    const e_toelichting = document.createElement('input');
    e_toelichting.type = 'text';
    e_toelichting.classList.add('form-control');
    e_toelichting.maxLength = 1024;
    e_toelichting.name = `nummers[${nummer_id}][toelichting]`;
    e_td_toelichting.appendChild(e_toelichting);

    const e_hidden = document.createElement('input');
    e_hidden.type = 'hidden';
    e_hidden.name = `nummers[${nummer_id}][id]`;
    e_hidden.value = nummer_id;
    this.e_tr.appendChild(e_hidden);

    document.querySelector('#keuzes tbody').appendChild(this.e_tr);
  }

  /**
   * Verwijdert het nummer uit de DOM.
   */
  destroy() {
    this.e_tr.remove();
  }

}

/**
 * Een invoerveld met info over de stemmer.
 */
class Invoerveld {

  /** @type {HTMLDivElement} */
  e_form_group;

  constructor({
    id,
    label,
    leeg_feedback,
    max,
    maxlength,
    min,
    minlength,
    placeholder,
    type,
    verplicht
  }) {
    const id_str = `veld-${id}`;
    const naam = `velden[${id}]`;

    this.e_form_group = document.createElement('div');
    this.e_form_group.classList.add('form-group', 'row');

    const e_label = document.createElement('label');
    this.e_form_group.appendChild(e_label);
    e_label.classList.add('control-label', 'col-sm-2');
    e_label.setAttribute('for', id_str);
    e_label.appendChild(document.createTextNode(label));

    const e_col = document.createElement('div');
    this.e_form_group.appendChild(e_col);
    e_col.classList.add('col-sm-10');

    let e_input;
    if ( type === 'textarea' ) {
      e_input = document.createElement('textarea');
      e_input.rows = 5;
    } else {
      e_input = document.createElement('input');
      if ( type === 'postcode' ) {
        e_input.type = 'text';
        e_input.classList.add('postcode');
      } else {
        e_input.type = type;
      }
      if ( Number.isInteger(max) ) {
        e_input.max = max;
      }
      if ( Number.isInteger(min) ) {
        e_input.min = min;
      }
    }
    e_col.appendChild(e_input);
    e_input.classList.add('form-control');
    e_input.id = id_str;
    e_input.name = naam;
    e_input.setAttribute('data-leeg-feedback', leeg_feedback);
    if ( Number.isInteger(maxlength) ) {
      e_input.maxLength = maxlength;
    }
    if ( Number.isInteger(minlength) ) {
      e_input.minLength = minlength;
    }
    if ( typeof placeholder === 'string' ) {
      e_input.placeholder = placeholder;
    }
    e_input.required = verplicht;

    document.getElementById('formulier-velden').appendChild(this.e_form_group);
  }

}

class StemView {

  /** @type {number} */
  lijst_id;
  /** @type {number} */
  minkeuzes;
  /** @type {number} */
  maxkeuzes;
  /** @type {boolean} */
  is_artiest_eenmalig;
  /** @type {boolean} */
  heeft_recaptcha;
  /** @type {{[key: number]: Nummer}} */
  geselecteerde_nummers;
  /** @type {Array} */
  geselecteerde_artiesten;
  /** @type {HTMLTableElement} */
  e_datatable;
  /** @type {HTMLTableSectionElement} */
  e_datatable_body;
  /** @type {_Api} */
  datatable;
  /** @type {HTMLBodyElement} */
  e_body;
  /** @type {HTMLFormElement} */
  e_keuzeformulier;
  /** @type {HTMLFormElement} */
  e_stemmerformulier;
  serverdata_promise;
  /** @type {HTMLDivElement} */
  e_errormsg;
  random_seed;
  
  constructor() {
    this.lijst_id = (new URLSearchParams(window.location.search)).get('lijst');
    this.e_body = document.getElementsByTagName('body').item(0);
    this.e_keuzeformulier = document.getElementById('keuzeformulier');
    this.e_stemmerformulier = document.getElementById('stemmerformulier');
    this.geselecteerde_nummers = {};
    this.geselecteerde_artiesten = [];
    this.e_errormsg = document.getElementById('errormsg');
    this.random_seed = Math.floor(Math.random() * 2**16);

    this.init();
  }

  async init() {
    await this.vul_serverdata();

    this.e_body.classList.add('geladen');

    const e_datatable = document.getElementById('nummers');

    this.datatable = new DataTable(e_datatable, {
      processing: true,
      serverSide: true,
      ajax: (data, callback, settings) => {
        data.lijst = this.lijst_id;
        data.is_vrijekeuze = false;
        data.random_seed = this.random_seed;
        functies.vul_datatables(data, callback, settings);
      },
      bLengthChange: false,
      iDisplayLength: 50,
      columnDefs: [
        {
          targets: 0,
          searchable: false,
          orderable: false,
          className: 'dt-body-center',
          render: (data, type, full, meta) => {
            return '<input type="checkbox">';
          }
        }
      ],
      order: [
        [2, 'asc'],
        [1, 'asc']
      ],
      ordering: !(await this.is_random_volgorde()),
      // rowCallback: this.row_callback.bind(this),
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
    });
    this.e_datatable_body = e_datatable.getElementsByTagName('tbody').item(0);

    for ( const elem of this.e_stemmerformulier.elements ) {
      this.add_trim_handler(elem);
    }

    // Klik op een checkbox of tabelrij met een nummer.
    this.e_datatable_body.addEventListener('click', this.tabel_klik_handler.bind(this));

    // Insturen keuzeformulier. Gebeurt in principe niet want er is geen knop.
    this.e_keuzeformulier.addEventListener('submit', this.submit_handler.bind(this));

    // Insturen
    this.e_stemmerformulier.addEventListener('submit', this.submit_handler.bind(this));
  }

  /**
   * Vult serverdata in in het object en de DOM.
   */
  async vul_serverdata() {
    let serverdata;
    try {
      serverdata = await this.get_serverdata();
    } catch (msg) {
      this.error(msg);
      return;
    }
    this.minkeuzes = serverdata.minkeuzes;
    this.maxkeuzes = serverdata.maxkeuzes;
    this.is_artiest_eenmalig = serverdata.is_artiest_eenmalig;
    const titel = `${serverdata.organisatie} – ${serverdata.lijst_naam}`
    document.getElementsByTagName('title').item(0).innerText = titel;
    if ( serverdata.heeft_gebruik_recaptcha ) {
      this.e_body.classList.add('heeft-recaptcha');
    }
    if ( serverdata.is_actief ) {
      this.e_body.classList.add('is-actief');
    }
    this.e_keuzeformulier.elements.lijst.value = this.lijst_id;
    for ( const velddata of serverdata.velden ) {
      new Invoerveld(velddata);
    }
    for ( let i = 0; i < serverdata.vrijekeuzes; i++ ) {
      this.vrijkeuze_toevoegen(i, serverdata.vrijekeuzes);
    }
    for ( const e_captcha of document.querySelectorAll('.g-recaptcha') ) {
      e_captcha.setAttribute('data-sitekey', serverdata.recaptcha_sitekey);
      const e_script = document.createElement('script');
      e_script.src = 'https://www.google.com/recaptcha/api.js?hl=nl';
      e_captcha.parentElement.appendChild(e_script);
    }
    for ( const e_organisatie of document.querySelectorAll('.organisatie') ) {
      e_organisatie.innerText = serverdata.organisatie;
    }
    for ( const e_privacy_url of document.querySelectorAll('a.privacy-url') ) {
      e_privacy_url.setAttribute('href', serverdata.privacy_url);
    }
  }

  /**
   * Haalt gegevens over de stemlijst van de server.
   */
  get_serverdata() {
    return this.serverdata_promise ??= server.post('get_stemlijst_frontend_data', {
      lijst: this.lijst_id
    });
  }

  /**
   * @returns {Promise<boolean>}
   */
  async is_random_volgorde() {
    return (await this.get_serverdata()).random_volgorde;
  }

  /**
   * Verwerkt het stemmen.
   * @param {Event} event 
   */
  async submit_handler(event) {
    event.preventDefault();
    event.stopPropagation();
    if ( Object.keys(this.geselecteerde_nummers).length < this.minkeuzes ) {
      alert(`U moet mimimaal ${this.minkeuzes} nummers selecteren.`);
      return;
    }
    if ( this.heeft_recaptcha && document.getElementById('g-recaptcha-response').value === '' ) {
      alert('Plaats een vinkje a.u.b.');
      return;
    }

    for ( const veld of this.e_stemmerformulier.elements ) {
      if ( veld.required && veld.value === '' ) {
        alert(veld.getAttribute('data-leeg-feedback'));
        veld.focus();
        return;
      }
      if ( veld.min > 0 && veld.valueAsNumber < veld.min ) {
        alert(`De waarde van ${veld.name} is te laag. Het minimum is ${veld.min}`);
        return;
      }
      if ( veld.max > 0 && veld.valueAsNumber > veld.max ) {
        alert(`De waarde van ${veld.name} is te hoog. Het maximum is ${veld.max}`);
        return;
      }
      if ( veld.minLength > 0 && veld.value.length < veld.minLength ) {
        alert(`De invoer van ${veld.name} is te kort. De minimumlengte is ${veld.minLenght}`);
        return;
      }
      if ( veld.maxLength > 0  && veld.value.length > veld.maxLength ) {
        alert(`De invoer van ${veld.name} is te lang. De maximumlengte is ${veld.maxLength}`);
        return;
      }
    }

    const fd = new FormData(this.e_stemmerformulier);
    fd.append('lijst', this.lijst_id);
    try {
      const data = await server.post('stem', fd);
      const hoogte = document.getElementById('stemsegment').offsetHeight - 100;
      document.getElementById('stemsegment-placeholder').style.height = `${hoogte}px`;
      document.getElementById('result').innerHTML = data;
      this.e_body.classList.add('gestemd');
    } catch (msg) {
      alert(msg);
    }
  }

  /**
   * Knipt spaties en andere lege tekens weg aan het begin en einde van tekstvelden.
   * @param {HTMLElement} e 
   */
  add_trim_handler(elem) {
    const types = [
      'email',
      'month',
      'number',
      'tel',
      'text',
      'time',
      'url',
      'week'
    ];
    if ( elem instanceof HTMLInputElement && types.includes(elem.type) || elem instanceof HTMLTextAreaElement ) {
      elem.addEventListener('blur', e => {
        e.target.value = e.target.value.trim();
      });
    }
  }
  
  /**
   * Regelt het selecteren of deselecteren van een nummer in de lijst.
   * @param {Event} e 
   * @returns 
   */
  tabel_klik_handler(e) {
    const e_rij = e.target.closest('tr');
    const e_checkbox = e_rij.querySelector('input[type="checkbox"]');
    const checkbox_klik = e.target instanceof HTMLInputElement;
    // Bij het klikken op de checkbox zelf is deze al geselecteerd,
    // bij het klikken op een andere plek in de tabel niet.
    const selecteren = checkbox_klik && e_checkbox.checked || !checkbox_klik && !e_checkbox.checked;

    // Get row data
    let [nummer_id, titel, artiest] = this.datatable.row(e_rij).data();
    nummer_id = Number.parseInt(nummer_id);

    if ( selecteren ) {
      // Nummer selecteren
      if ( Object.keys(this.geselecteerde_nummers).length >= this.maxkeuzes ) {
        e_checkbox.checked = false;
        alert(`U kunt maximaal ${this.maxkeuzes} nummers selecteren.`);
        return;
      }
      if ( this.is_artiest_eenmalig && this.geselecteerde_artiesten.includes(artiest) ) {
        e_checkbox.checked = false;
        alert('Deze artiest is al gekozen');
        return;
      }
      this.geselecteerde_nummers[nummer_id] = new Nummer(nummer_id, titel, artiest);
      if ( !this.geselecteerde_artiesten.includes(artiest) ) {
        this.geselecteerde_artiesten.push(artiest);
      }
      e_checkbox.checked = true;
      e_rij.classList.add('selected');
    } else {
      // Nummer deselecteren
      const nummer = this.geselecteerde_nummers[nummer_id];
      nummer.destroy();
      delete this.geselecteerde_nummers[nummer_id];
      const artiest_index = this.geselecteerde_artiesten.indexOf(artiest);
      if ( artiest_index !== -1 ) {
        this.geselecteerde_artiesten.splice(artiest_index, 1);
      }
      e_checkbox.checked = false;
      e_rij.classList.remove('selected');
    }
    if ( Object.keys(this.geselecteerde_nummers).length > 0 ) {
      this.e_body.classList.add('heeft-nummers-geselecteerd');
    } else {
      this.e_body.classList.remove('heeft-nummers-geselecteerd');
    }
  }

  /**
   * Voegt een veld met titel en artiest voor een optionele vrije keuze.
   * @param {number} nummer Volgnummer van de vrije keuze.
   * @param {number} totaal Totaal aantal vrije keuzes.
   * @return {void}
   */
  vrijkeuze_toevoegen(nummer, totaal) {
    const label = totaal === 1 ? 'Vrije keuze' : `Vrije keuze ${nummer + 1}`;

    const e_artiest_col = this.vrijkeuze_toevoegen_veld('Artiest', nummer, 3, 128);
    const e_titel_col = this.vrijkeuze_toevoegen_veld('Titel', nummer, 3, 128);
    const e_toelichting_col = this.vrijkeuze_toevoegen_veld('Toelichting', nummer, 4, 512);

    const e_form_group = document.createElement('div');
    e_form_group.classList.add('form-group', 'row');

    const e_label = document.createElement('label');
    e_form_group.appendChild(e_label);
    e_label.classList.add('control-label', 'col-sm-2');
    e_label.setAttribute('for', e_artiest_col.querySelector('input').id);
    e_label.appendChild(document.createTextNode(label));

    e_form_group.appendChild(e_artiest_col);
    e_form_group.appendChild(e_titel_col);
    e_form_group.appendChild(e_toelichting_col);

    document.getElementById('formulier-velden').appendChild(e_form_group);
  }

  /**
   * Titel- of artiestveld maken.
   * @param {'Titel' | 'Artiest' | 'Toelichting'} veld 
   * @param {number} nummer Volgnummer van de vrije keuze.
   * @param {number} breedte Breedte van de kolom.
   * @param {number} max_length
   * @returns {HTMLDivElement} Kolomelement.
   */
  vrijkeuze_toevoegen_veld(veld, nummer, breedte, max_length) {
    const veld_intern = veld.toLowerCase();
    const id_str = `vrijekeuze-${veld_intern}-${nummer}`;
    const naam = `vrijekeuzes[${nummer}][${veld_intern}]`;

    const e_col = document.createElement('div');
    e_col.classList.add(`col-sm-${breedte}`);

    const e_input = document.createElement('input');
    e_input.type = 'text';
    e_col.appendChild(e_input);
    e_input.classList.add('form-control');
    e_input.id = id_str;
    e_input.name = naam;
    e_input.maxLength = max_length;
    e_input.placeholder = veld;
    return e_col;
  }

  /**
   * Plaatst een foutmelding op de pagina.
   * @param {string} msg 
   */
  error(msg) {
    this.e_errormsg.innerText = msg;
    this.e_body.classList.add('error');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new StemView();
});
