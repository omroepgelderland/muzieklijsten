// Libraries js
import 'bootstrap';
import 'datatables.net-dt';

// Project js
import './favicons.js';
import * as functies from './functies.js';

// Libraries css
import 'bootstrap/dist/css/bootstrap.min.css';

// Project css
import '../scss/algemeen.scss';
import '../scss/muzieklijst.scss';

// Afbeeldingen
// import '../../assets/afbeeldingen/fbshare_top100.jpg';

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

    let e_td_titel = document.createElement('td');
    e_td_titel.appendChild(document.createTextNode(titel));
    this.e_tr.appendChild(e_td_titel);

    let e_td_artiest = document.createElement('td');
    e_td_artiest.appendChild(document.createTextNode(artiest));
    this.e_tr.appendChild(e_td_artiest);

    let e_td_toelichting = document.createElement('td');
    e_td_toelichting.classList.add('remark');
    this.e_tr.appendChild(e_td_toelichting);

    let e_toelichting = document.createElement('input');
    e_toelichting.type = 'text';
    e_toelichting.classList.add('form-control');
    e_toelichting.maxLength = 1024;
    e_toelichting.name = `nummers[${nummer_id}][toelichting]`;
    e_td_toelichting.appendChild(e_toelichting);

    let e_hidden = document.createElement('input');
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
    this.e_tr.parentNode.removeChild(this.e_tr);
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
  /** @type {Array} */
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
  /** @type {Promise} */
  serverdata_promise;
  /** @type {HTMLDivElement} */
  e_errormsg;
  
  constructor() {
    this.lijst_id = (new URLSearchParams(window.location.search)).get('lijst');
    this.e_body = document.getElementsByTagName('body').item(0);
    this.e_keuzeformulier = document.getElementById('keuzeformulier');
    this.e_stemmerformulier = document.getElementById('stemmerformulier');
    this.geselecteerde_nummers = {};
    this.geselecteerde_artiesten = [];
    this.e_errormsg = document.getElementById('errormsg');

    let serverdata_geladen = this.vul_serverdata();
    serverdata_geladen.then(() => {
      this.e_body.classList.add('geladen');
    });

    const e_datatable = document.getElementById('nummers');
    // datatables heeft jQuery nodig.
    this.datatable = $(e_datatable).DataTable({
      'processing': true,
      'serverSide': true,
      'ajax': (data, callback, settings) => {
        data.lijst = this.lijst_id;
        functies.vul_datatables(data, callback, settings);
      },
      'bLengthChange': false,
      'iDisplayLength': 50,
      'columnDefs': [{
        'targets': 0,
        'searchable': false,
        'orderable': false,
        'className': 'dt-body-center',
        'render': (data, type, full, meta) => {
          return '<input type="checkbox">';
        }
      }],
      'order': [1, 'asc'],
      // 'rowCallback': this.row_callback.bind(this),
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
    this.e_datatable_body = e_datatable.getElementsByTagName('tbody').item(0);

    serverdata_geladen.then(() => {
      for ( const elem of this.e_stemmerformulier.elements ) {
        this.add_trim_handler(elem);
      }
  
      // Klik op een checkbox of tabelrij met een nummer.
      this.e_datatable_body.addEventListener('click', this.tabel_klik_handler.bind(this));

      // Insturen keuzeformulier. Gebeurt in principe niet want er is geen knop.
      this.e_keuzeformulier.addEventListener('submit', this.submit_handler.bind(this));

      // Insturen
      this.e_stemmerformulier.addEventListener('submit', this.submit_handler.bind(this));
    });
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
    if ( serverdata.is_max_stemmen_per_ip_bereikt ) {
      this.e_body.classList.add('max-ip-bereikt');
    }
    this.e_keuzeformulier.elements.lijst.value = this.lijst_id;
    document.getElementById('formulier-velden').innerHTML = serverdata.formulier_velden;
    for ( const e_captcha of document.querySelectorAll('.g-recaptcha') ) {
      e_captcha.setAttribute('data-sitekey', serverdata.recaptcha_sitekey);
      let e_script = document.createElement('script');
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
   * @returns {Promise<object>}
   */
  get_serverdata() {
    return this.serverdata_promise ??= functies.post('get_stemlijst_frontend_data', {
      'lijst': this.lijst_id
    });
  }

  /**
   * Verwerkt het stemmen.
   * @param {Event} e 
   */
  submit_handler(e) {
    e.preventDefault();
    e.stopPropagation();
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

    let fd = new FormData(this.e_stemmerformulier);
    fd.append('lijst', this.lijst_id);
    functies.stem(fd).then((data) => {
      let hoogte = document.getElementById('stemsegment').offsetHeight - 100;
      document.getElementById('stemsegment-placeholder').style.height = `${hoogte}px`;
      document.getElementById('result').innerHTML = data;
      this.e_body.classList.add('gestemd');
    }, (msg) => {
      alert(msg);
    });
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
    let data = this.datatable.row($(e_rij)).data();
    // Get row ID
    let nummer_id = data[0];
    let titel = data[1];
    let artiest = data[2];

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
      let nummer = this.geselecteerde_nummers[nummer_id];
      nummer.destroy();
      delete this.geselecteerde_nummers[nummer_id];
      let artiest_index = this.geselecteerde_artiesten.indexOf(artiest);
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
