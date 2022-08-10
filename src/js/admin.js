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

$(document).ready(() => {
  var lijst_id = $('body').data('lijst-id');

  // Array holding selected row IDs
  var rows_selected = $('body').data('rows-selected');
  var table = $('#beschikbare-nummers').DataTable({
    'processing': true,
    'serverSide': true,
    'ajax': functies.vul_datatables,
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
    'rowCallback': (row, data, dataIndex) => {
      // Get row ID
      var rowId = data[0];

      // If row ID is in the list of selected row IDs
      if ($.inArray(rowId, rows_selected) !== -1) {
        $(row).find('input[type="checkbox"]').prop('checked', true);
        $(row).addClass('selected');
      }
    },
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

  // Handle click on checkbox
  $('#beschikbare-nummers tbody').on('click', 'input[type="checkbox"]', (e) => {
    // Prevent click event from propagating to parent
    e.stopPropagation();

    let $row = $(e.target).closest('tr');

    // Get row data
    let data = table.row($row).data();

    // Get row ID
    let rowId = data[0];

    // Determine whether row ID is in the list of selected row IDs 
    let index = $.inArray(rowId, rows_selected);

    // If checkbox is checked and row ID is not in list of selected row IDs
    if (e.target.checked && index === -1) {
      rows_selected.push(rowId);

      // Otherwise, if checkbox is not checked and row ID is in list of selected row IDs
    } else if (!e.target.checked && index !== -1) {
      rows_selected.splice(index, 1);
    }

    if (e.target.checked) {
      $row.addClass('selected');
    } else {
      $row.removeClass('selected');
    }

    $('form#beheer-nummers').trigger('submit');
  });

  // Handle click on table cells with checkboxes
  $('#beschikbare-nummers').on('click', 'tbody td, thead th:first-child', (e) => {
    $(e.target).parent().find('input[type="checkbox"]').trigger('click');
  });

  $('form#beheer-nummers').on('submit', (e) => {

    // Iterate over all selected checkboxes
    let nummer_ids = [];
    for (const rowId of rows_selected) {
      nummer_ids.push(rowId);
    }

    functies.update_lijst(lijst_id, nummer_ids).then(() => {
      functies.get_selected_html(lijst_id).then((data) => {
        $('#result').html(data);
      }, (msg) => {
        alert(msg);
      });
    }, (msg) => {
      alert(msg);
    });

    // Prevent actual form submission
    e.preventDefault();
  });

  functies.get_selected_html(lijst_id).then((data) => {
    $('#result').html(data);
  }, (msg) => {
    alert(msg);
  });
  $('#beschikbare-nummers_length select').addClass('form-control');
  $('#beschikbare-nummers_filter input').addClass('form-control');

  $('#lijstselect').on('change', (e) => {
    let params = new URLSearchParams(location.search);
    params.set('lijst', e.target.value);
    location.search = params;
  });

  // Bestaande lijst wijzigen
  $('#beheer').on('submit', 'form#beheer-lijst', (e) => {
    e.preventDefault();
    let fd = new FormData(document.getElementById('beheer-lijst'));
    functies.lijst_opslaan(fd).then(() => {
      $('#beheer').modal('hide');
    }, (msg) => {
      alert(msg);
    });
  });

  // Nieuwe lijst maken
  $('#nieuw').on('submit', 'form#beheer-lijst', (e) => {
    e.preventDefault();
    let fd = new FormData(document.getElementById('beheer-lijst'));
    functies.lijst_maken(fd).then((lijst_id) => {
      $('#nieuw').modal('hide');
      let params = new URLSearchParams(location.search);
      params.set('lijst', lijst_id);
      location.search = params;
    }, (msg) => {
      alert(msg);
    });
  });

  // Lijst verwijderen
  $('#beheer').on('click', '#verwijder-lijst', (e) => {
    if (confirm('Deze lijst verwijderen?\nOok alle stemmen op nummers uit deze lijst worden verwijderd.')) {
      functies.verwijder_lijst(lijst_id).then(() => {
        $('#beheer').modal('hide');
        let params = new URLSearchParams(location.search);
        params.set('lijst', '');
        location.search = params;
      }, (msg) => {
        alert(msg);
      });
    }
  });

});
