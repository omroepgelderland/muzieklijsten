// Libraries css
import 'bootstrap';
import 'datatables.net-dt';

// Project js
import './favicons.js';
import * as functies from './functies.js';

// Libraries css
import 'bootstrap/dist/css/bootstrap.min.css';
import 'datatables.net-dt/css/jquery.dataTables.min.css';

// Project css
import '../scss/algemeen.scss';
import '../scss/admin.scss';

class Main {

  lijst_id;
  geselecteerde_nummers;
  tabel;

  constructor() {

    this.lijst_id = $('body').data('lijst-id');

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
}

$(document).ready(() => {
  new Main();
});
