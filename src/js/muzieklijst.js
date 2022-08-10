// Libraries js
import 'bootstrap';
import 'datatables.net-dt';
import 'eonasdan-bootstrap-datetimepicker';

// Project js
import './favicons.js';
import * as functies from './functies.js';

// Libraries css
import 'bootstrap/dist/css/bootstrap.min.css';

// Project css
import '../scss/algemeen.scss';
import '../scss/muzieklijst.scss';

// Afbeeldingen
import '../../assets/afbeeldingen/fbshare_top100.jpg';

class StemView {

  lijst_id;
  minkeuzes;
  maxkeuzes;
  artiest_eenmalig;
  veld_woonplaats;
  veld_adres;
  veld_telefoonnummer;
  veld_email;
  veld_uitzenddatum;
  heeft_recaptcha;
  geselecteerde_nummers;
  geselecteerde_artiesten;
  datatable;

  constructor() {
    this.lijst_id = $('input[name="lijst"]').val();
    this.minkeuzes = $('body').data('minkeuzes');
    this.maxkeuzes = $('body').data('maxkeuzes');
    this.artiest_eenmalig = $('body').data('artiest-eenmalig');
    this.veld_woonplaats = $('body').hasClass('veld-woonplaats');
    this.veld_adres = $('body').hasClass('veld-adres');
    this.veld_telefoonnummer = $('body').hasClass('veld-telefoonnummer');
    this.veld_email = $('body').hasClass('veld-email');
    this.veld_uitzenddatum = $('body').hasClass('veld-uitzenddatum');
    this.heeft_recaptcha = $('body').hasClass('heeft-recaptcha');

    this.geselecteerde_nummers = [];
    this.geselecteerde_artiesten = [];
    this.datatable = $('#nummers').DataTable({
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

    // Handle click on checkbox
    $('#nummers tbody').on('click', 'input[type="checkbox"]', this.checkbox_handler.bind(this));

    // Handle click on table cells with checkboxes
    $('#nummers').on('click', 'tbody td, thead th:first-child', (e) => {
      $(e.target).parent().find('input[type="checkbox"]').trigger('click');
    });

    // Handle form submission event 
    $('#stemformulier').on('submit', this.stem.bind(this));

    $('#submit').on('click', () => {
      return this.validate();
    });

    if (this.veld_uitzenddatum) {
      $('#datetimepicker').datetimepicker({
        locale: 'nl',
        format: 'DD-MM-YYYY'
      });
    }
  }

  validate() {
    if (this.minkeuzes !== undefined && this.geselecteerde_nummers.length < this.minkeuzes) {
      alert(`U moet mimimaal ${this.minkeuzes} nummers selecteren.`);
      return false;
    }
    if (form.naam.value === '') {
      alert('Vul het ontbrekende veld in a.u.b.');
      form.naam.focus();
      return false;
    }
    if (this.veld_woonplaats && form.woonplaats.value === '') {
      alert('Vul het ontbrekende veld in a.u.b.');
      form.woonplaats.focus();
      return false;
    }
    if (this.veld_adres && form.adres.value === '') {
      alert('Vul het ontbrekende veld in a.u.b.');
      form.adres.focus();
      return false;
    }
    if (this.veld_telefoonnummer && form.telefoonnummer.value === '') {
      alert('Vul het ontbrekende veld in a.u.b.');
      form.telefoonnummer.focus();
      return false;
    }
    if (this.veld_email && form.veld_email.value === '') {
      alert('Vul het ontbrekende veld in a.u.b.');
      form.veld_email.focus();
      return false;
    }
    if (this.veld_uitzenddatum && form.veld_uitzenddatum.value === '') {
      alert('Vul het ontbrekende veld in a.u.b.');
      form.veld_uitzenddatum.focus();
      return false;
    }
    var geldig = true;
    $('input[required]').each((index, element) => {
      if ($(element).val() === '') {
        var leeg_feedback = $(element).data('leeg-feedback');
        alert(leeg_feedback);
        return geldig = false;
      }
    });
    if (!geldig) {
      return false;
    }
    if (this.heeft_recaptcha && document.getElementById('g-recaptcha-response').value === '') {
      alert('Plaats een vinkje a.u.b.');
      return false;
    }
    return true;
  }
  
  update_geselecteerde_nummers_info() {
    functies.toon_geselecteerde_nummers(this.geselecteerde_nummers).then((data) => {
      $('#result').html(data);
    }, (msg) => {
      alert(msg);
    });
  }
  
  stem(e) {
    e.preventDefault();
    let fd = new FormData(document.getElementById('stemformulier'));
    for (const nummer_id of this.geselecteerde_nummers) {
      fd.append('nummers[]', nummer_id);
    }
    functies.stem(fd).then((data) => {
      let wrapper_hoogte = $('#nummers_wrapper').outerHeight();
      $('#nummers_wrapper').hide();
      $('#table_placeholder').height(wrapper_hoogte);
      $('#contactform').hide();  
      $('#result').html(data);
    }, (msg) => {
      alert(msg);
    });
  }

  // row_callback(row, data, displayNum, displayIndex, dataIndex) {
  //   // Get row ID
  //   var nummer_id = data[0];
  //   // If row ID is in the list of selected row IDs
  //   if ($.inArray(nummer_id, this.geselecteerde_nummers) !== -1) {
  //     $(row).find('input[type="checkbox"]').prop('checked', true);
  //     $(row).addClass('selected');
  //   }
  // }

  checkbox_handler(e) {
    var $row = $(e.target).closest('tr');
    // Get row data
    var data = this.datatable.row($row).data();
    // Get row ID
    var nummer_id = data[0];
    // Determine whether row ID is in the list of selected row IDs 
    var index = $.inArray(nummer_id, this.geselecteerde_nummers);
    // If checkbox is checked and row ID is not in list of selected row IDs
    if (e.target.checked && index === -1) {
      this.geselecteerde_nummers.push(nummer_id);
      // Otherwise, if checkbox is not checked and row ID is in list of selected row IDs
    } else if (!e.target.checked && index !== -1) {
      this.geselecteerde_nummers.splice(index, 1);
    }
    if (this.artiest_eenmalig) {
      // Get row ID
      var artiest = data[2];
      // Determine whether row ID is in the list of selected row IDs 
      var index2 = $.inArray(artiest, this.geselecteerde_artiesten);
      // If checkbox is checked and row ID is not in list of selected row IDs
      if (e.target.checked) {
        this.geselecteerde_artiesten.push(artiest);
        // Otherwise, if checkbox is not checked and row ID is in list of selected row IDs
      } else if (!e.target.checked) {
        this.geselecteerde_artiesten.splice(index2, 1);
      }
      var unique = (a) => {
        var counts = [];
        for (var i = 0; i <= a.length; i++) {
          if (counts[a[i]] === undefined) {
            counts[a[i]] = 1;
          } else {
            return true;
          }
        }
        return false;
      }
      if (unique(this.geselecteerde_artiesten) == true) {
        $(e.target).prop('checked', false);
        this.geselecteerde_artiesten.splice(index, 1);
        this.geselecteerde_nummers.splice(index, 1);
        alert('Deze artiest is al gekozen');
      }
    }
    if (this.geselecteerde_nummers.length > this.maxkeuzes) {
      $(e.target).prop('checked', false);
      this.geselecteerde_nummers.splice(index, 1);
      alert(`U kunt maximaal ${this.maxkeuzes} nummers selecteren.`);
    }
    if (e.target.checked) {
      $row.addClass('selected');
    } else {
      $row.removeClass('selected');
    }
    this.update_geselecteerde_nummers_info();
    e.stopPropagation();
  }
  
}

$(document).ready(() => {
  let v = new StemView();
});
