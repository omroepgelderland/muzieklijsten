export function get_instant_promise(...args) {
    return (new $.Deferred()).resolve(...args).promise();
  }
  
  export function get_instant_reject_promise(...args) {
    return (new $.Deferred()).reject(...args).promise();
  }
  
  function ajax_done(data) {
    if (data.error === false) {
      // Request geslaagd.
      return data.data;
    } else {
      // Request geslaagd, maar server geeft foutmelding.
      return get_instant_reject_promise(data.errordata);
    }
  }
  
  function ajax_fail(jqXHR, textStatus, errorThrown) {
    // Request mislukt.
    return get_instant_reject_promise(errorThrown);
  }
  
  export function post(functie, data) {
    if ( data instanceof FormData ) {
      data.append('functie', functie);
      return $.ajax({
        'url': 'ajax.php',
        'data': data,
        'processData': false,
        'contentType': false,
        'type': 'POST'
      }).then(ajax_done, ajax_fail);
    } else {
      data.functie = functie;
      return $.post('ajax.php', data).then(ajax_done, ajax_fail);
    }
  }
  
  export function verwijder_lijst(lijst_id) {
    return post('verwijder_lijst', {
      'lijst': lijst_id
    });
  }
  
  export function lijst_opslaan(fd) {
    return post('lijst_opslaan', fd);
  }

  export function lijst_maken(fd) {
    return post('lijst_maken', fd);
  }
  
  export function losse_nummers_toevoegen(nummers, lijst_ids) {
    return post('losse_nummers_toevoegen', {
      'nummers': nummers,
      'lijsten': lijst_ids
    });
  }
  
  export function get_lijsten() {
    return post('get_lijsten', {});
  }
  
  export function stem_set_behandeld(nummer_id, lijst_id, stemmer_id, waarde) {
    return post('stem_set_behandeld', {
      'nummer': nummer_id,
      'lijst': lijst_id,
      'stemmer': stemmer_id,
      'waarde': waarde
    });
  }
  
  export function verwijder_stem(nummer_id, lijst_id, stemmer_id) {
    return post('verwijder_stem', {
      'nummer': nummer_id,
      'lijst': lijst_id,
      'stemmer': stemmer_id
    });
  }
  
  export function verwijder_nummer(lijst_id, nummer_id) {
    return post('verwijder_nummer', {
      'lijst': lijst_id,
      'nummer': nummer_id
    });
  }
  
  export function get_totaal_aantal_stemmers(lijst_id, van, tot) {
    return post('get_totaal_aantal_stemmers', {
      'lijst': lijst_id,
      'van': van,
      'tot': tot
    });
  }
  
  export function toon_geselecteerde_nummers(nummer_ids) {
    return post('toon_geselecteerde_nummers', {
      'nummers': nummer_ids
    });
  }
  
  export function stem(data) {
    return post('stem', data);
  }
  
  export function get_selected_html(lijst_id) {
    return post('get_selected_html', {
      'lijst': lijst_id
    });
  }
  
  export function vul_datatables(data, callback, settings) {
    post('vul_datatables', data).then((data) => {
      callback(data);
    });
  }
  
  export function login() {
    return post('login', {});
  }
