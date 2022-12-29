// Libraries js
import 'bootstrap';
import 'datatables.net-dt';
import 'eonasdan-bootstrap-datetimepicker';

// Project js
import './favicons.js';
import * as functies from './functies.js';

// Libraries css
import 'bootstrap/dist/css/bootstrap.min.css';
import 'font-awesome/css/font-awesome.min.css';
import 'eonasdan-bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css';

// Project css
import '../scss/algemeen.scss';
import '../scss/resultaten.scss';

function verminder_totaal_aantal_stemmen(aantal) {
  let $elem = $('#totaal-aantal-stemmen');
  $elem.text($elem.text() - aantal);
}

/**
 * Haalt het totaal aantal stemmers van de server.
 */
function set_totaal_aantal_stemmers(lijst_id) {
  functies.get_totaal_aantal_stemmers(lijst_id).then((totaal_aantal_stemmers) => {
    $('#totaal-aantal-stemmers').text(totaal_aantal_stemmers);
  }, (msg) => {
    alert(msg);
  });
}

/**
 * Filtert de resultatenlijst aan de hand van de ingevoerde filtertekst.
 * @param {Event} e 
 */
function resultaatfilter(e) {
  let filter = e.target.value.toLowerCase();
  let rijen = document.querySelectorAll('#resultaten tbody tr');
  let cel_tekst;
  let match;
  for ( const rij of rijen ) {
    if ( filter.length < 3 ) {
      rij.classList.remove('verborgen');
      continue;
    }
    match = false;
    for ( const cel of rij.children ) {
      if ( cel.nodeName.toLowerCase() !== 'td' ) {
        continue;
      }
      cel_tekst = '';
      for ( const node of cel.childNodes ) {
        if ( node.nodeType === Node.TEXT_NODE ) {
          cel_tekst += node.data.toLowerCase();
        }
      }
      if ( cel_tekst.includes(filter) ) {
        match = true;
        break;
      }
    }
    if ( match ) {
      rij.classList.remove('verborgen');
    } else {
      rij.classList.add('verborgen');
    }
  }
}

$(document).ready(() => {
  var lijst_id = $('body').data('lijst-id');

  $('tr').on('shown.bs.collapse', (e) => {
    $(e.target).prev('tr').find('.fa-plus-square')
      .removeClass('fa-plus-square')
      .addClass('fa-minus-square');
    $('#iframeHelper', window.parent.document)
      .height($('#iframeHelper', window.parent.document).contents().find('html').height());
  }).on('hidden.bs.collapse', (e) => {
    $(e.target).prev('tr').find('.fa-minus-square').removeClass('fa-minus-square').addClass('fa-plus-square');
    $('#iframeHelper', window.parent.document).height($('#iframeHelper', window.parent.document).contents().find('html').height());
  });

  $('#iframeHelper', window.parent.document).height($('#iframeHelper', window.parent.document).contents().find('html').height());

  // Stemmer op behandeld of niet behandeld zetten.
  $('tr.stemmer input:checkbox').on('change', (e) => {
    let $tr = $(e.target).closest('tr.stemmer');
    let nummer_id = $tr.data('nummer-id');
    let stemmer_id = $tr.data('stemmer-id');
    if ($(e.target).prop('checked')) {
      functies.stem_set_behandeld(nummer_id, lijst_id, stemmer_id, true).then(() => {
        $tr.addClass('success');
      }, (msg) => {
        $(e.target).prop('checked', false);
        alert(msg);
      });
    } else {
      functies.stem_set_behandeld(nummer_id, lijst_id, stemmer_id, false).then(() => {
        $tr.removeClass('success');
      }, (msg) => {
        $(e.target).prop('checked', true);
        alert(msg);
      });
    }
  });

  // Verwijder één stem.
  $('.stem-verwijderen').on('click', (e) => {
    let $stemmer_rij = $(e.target).closest('.stemmer');
    let nummer_id = $stemmer_rij.data('nummer-id');
    let stemmer_id = $stemmer_rij.data('stemmer-id');
    functies.verwijder_stem(nummer_id, lijst_id, stemmer_id).then(() => {
      $stemmer_rij.remove();
      let $nummer_aantal_stemmen = $(`tr[data-nummer-id="${nummer_id}"]`).find('td.aantal-stemmen');
      $nummer_aantal_stemmen.text($nummer_aantal_stemmen.text() - 1);
      verminder_totaal_aantal_stemmen(1);
      set_totaal_aantal_stemmers(lijst_id);
    }, (msg) => {
      alert(msg);
    });
  });

  // Haal een nummer uit de stemlijst
  $('.verwijder-nummer').on('click', (event) => {
    if (confirm('Dit nummer, inclusief alle reacties hierop, verwijderen uit de stemlijst?')) {
      let $tr = $(event.target).closest('tr.nummer');
      let nummer_id = $(event.target).data('nummer-id');
      let aantal_stemmen = $tr.find('td.aantal-stemmen').text();
      functies.verwijder_nummer(lijst_id, nummer_id).then(() => {
        $tr.remove();
        verminder_totaal_aantal_stemmen(aantal_stemmen);
        set_totaal_aantal_stemmers(lijst_id);
      }, (msg) => {
        alert(msg);
      });
    }
    event.stopPropagation();
  });

  // Filterveld
  document.getElementById('resultaatfilter').addEventListener('input', resultaatfilter);

  $('#datetimepicker1').datetimepicker({
    'locale': 'nl',
    'calendarWeeks': true,
    'format': 'DD-MM-YYYY',
    'maxDate': Date.now()
  });
  $('#datetimepicker2').datetimepicker({
    'locale': 'nl',
    'calendarWeeks': true,
    'format': 'DD-MM-YYYY',
    'maxDate': Date.now()
  });
  $('#datetimepicker1').on('dp.change', (e) => {
    $('#datetimepicker2').data('DateTimePicker').minDate(e.date);
  });
  $('#datetimepicker2').on('dp.change', (e) => {
    $('#datetimepicker1').data('DateTimePicker').maxDate(e.date);
  });
});
