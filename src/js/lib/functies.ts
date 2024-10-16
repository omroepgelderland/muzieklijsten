import * as server from '@muzieklijsten/server';

export async function vul_datatables(data: any, callback: any, settings: any) {
  const respons = await server.post('vul_datatables', data);
  callback(respons);
}

/**
 * Maakt DOM-elementen van een door html-loader geïmporteerd template.
 * @returns De root-elementen van het template.
 */
export function get_html_template(geimporteerd_template: string): HTMLCollection {
  const template = document.createElement('template');
  template.innerHTML = geimporteerd_template.trim();
  return template.content.children;
}

export function get_html_template_enkel<T extends Element>(geimporteerd_template: string): T {
  const template = get_html_template(geimporteerd_template);
  if ( template.length !== 1 ) {
      throw new Error('Template-html moet exact één root-element hebben.');
  }
  return template.item(0) as T;
}

/**
 * Plaatst (non breaking) spaties in een Nederlands internationaal telefoonnummer
 * voor de leesbaarheid.
 * @param telefoonnummer - Origineel telefoonnummer
 */
export function format_telefoonnummer( telefoonnummer: string ): string {
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

export function get_random_string(lengte: number) {
  let respons = '';
  while ( respons.length < lengte ) {
    respons += Math.floor(Math.random()*Number.MAX_SAFE_INTEGER).toString(36);
  }
  return respons.substring(0, lengte);
}
