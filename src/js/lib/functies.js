/**
 * Voert een post-request uit aan de server.
 * @param {string} functie 
 * @param {object|FormData} data 
 * @returns {Promise<any>}
 */
export function post(functie, data = {}) {
  return new Promise((resolve, reject) => {
      let xhr = new XMLHttpRequest();
      // xhr.withCredentials = withCredentials;
      // xhr.withCredentials = false;
      xhr.open('POST', 'ajax.php', true);
      xhr.onload = post_verwerk_respons.bind(undefined, xhr, resolve, reject);
      xhr.onerror = post_verwerk_respons.bind(undefined, xhr, resolve, reject);

      if ( data instanceof FormData ) {
          data.append('functie', functie);
      } else {
          xhr.setRequestHeader('Content-Type', 'application/json');
          data.functie = functie;
          data = JSON.stringify(data);
      }
      xhr.send(data);
  });
}

/**
* Verwerkt een serverrespons.
* @param {XMLHttpRequest} xhr 
* @param {function} resolve Uit te voeren functie bij een succesvolle uitvoering.
* @param {function} reject Uit te voeren functie bij een mislukt request.
* @param {ProgressEvent} event 
*/
function post_verwerk_respons(xhr, resolve, reject, event) {
  try {
      let data = JSON.parse(xhr.response);
      if ( data.error !== false ) {
          reject(data.errordata);
      } else {
          resolve(data.data);
      }
  } catch ( error ) {
      reject(xhr.responseText);
  }
}

export function verwijder_lijst(lijst_id) {
  return post('verwijder_lijst', {
    lijst: lijst_id
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
    nummers: nummers,
    lijsten: lijst_ids
  });
}

export function get_lijsten() {
  return post('get_lijsten', {});
}

export function stem_set_behandeld(nummer_id, lijst_id, stemmer_id, waarde) {
  return post('stem_set_behandeld', {
    nummer: nummer_id,
    lijst: lijst_id,
    stemmer: stemmer_id,
    waarde: waarde
  });
}

export function verwijder_stem(nummer_id, lijst_id, stemmer_id) {
  return post('verwijder_stem', {
    nummer: nummer_id,
    lijst: lijst_id,
    stemmer: stemmer_id
  });
}

export function verwijder_nummer(lijst_id, nummer_id) {
  return post('verwijder_nummer', {
    lijst: lijst_id,
    nummer: nummer_id
  });
}

export function get_totaal_aantal_stemmers(lijst_id, van, tot) {
  return post('get_totaal_aantal_stemmers', {
    lijst: lijst_id,
    van: van,
    tot: tot
  });
}

export function toon_geselecteerde_nummers(nummer_ids) {
  return post('toon_geselecteerde_nummers', {
    nummers: nummer_ids
  });
}

export function stem(data) {
  return post('stem', data);
}

export function get_geselecteerde_nummers(lijst_id) {
  return post('get_geselecteerde_nummers', {
    lijst: lijst_id
  });
}

export async function vul_datatables(data, callback, settings) {
  const respons = await post('vul_datatables', data);
  callback(respons);
}

export function login() {
  return post('login', {});
}

/**
 * Maakt DOM-elementen van een door html-loader geïmporteerd template.
 * @param {string} geimporteerd_template 
 * @returns {HTMLCollection}
 */
export function get_html_template( geimporteerd_template ) {
  let template = document.createElement('template');
  template.innerHTML = geimporteerd_template.trim();
  return template.content.children;
}

/**
 * Plaatst (non breaking) spaties in een Nederlands internationaal telefoonnummer
 * voor de leesbaarheid.
 * @param {string} telefoonnummer Origineel telefoonnummer
 * @returns {string}
 */
export function format_telefoonnummer( telefoonnummer ) {
  const patronen = [
    // Viercijferige netnummers
    /^(\+31)((?:11|16|17|18|22|25|29|31|32|34|41|44|47|47|48|49|51|52|54|56|57|59|67|80|90)[0-9])([0-9]{2})([0-9]{2})([0-9]{2})$/,
    // Eencijferige netnummers
    /^(\+31)(6)([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})$/,
    // Tweecijferige netnummers
    /^(\+31)([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/
  ];
  for ( const patroon of patronen ) {
        let m = telefoonnummer.match(patroon);
    if ( m !== null && m.length > 0 ) {
      m.shift();
            return m.join(' ');
    }
  }
  return telefoonnummer;
}

export function get_random_string(lengte) {
  let respons = '';
  while ( respons.length < lengte ) {
    respons += Math.floor(Math.random()*Number.MAX_SAFE_INTEGER).toString(36);
  }
  return respons.substring(0, lengte);
}
