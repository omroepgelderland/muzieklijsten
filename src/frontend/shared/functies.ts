import * as gld from "gld-ts-lib/functions";

import * as api from "@muzieklijsten/api";

import type { Config } from "@muzieklijsten/api/types";
import type { DataTableRow } from "@muzieklijsten/types";

/**
 * Maakt DOM-elementen van een door html-loader geïmporteerd template.
 *
 * @returns De root-elementen van het template.
 */
export function get_html_template(geimporteerd_template: string): Element[] {
  const template = document.createElement("template");
  template.innerHTML = geimporteerd_template.trim();
  return Array.from(template.content.children);
}

export function get_html_template_enkel<T extends Element>(
  geimporteerd_template: string,
): T {
  const template = get_html_template(geimporteerd_template);
  if (template.length !== 1) {
    throw new Error("Template-html moet exact één root-element hebben.");
  }
  return template[0] as T;
}

/**
 * Plaatst (non breaking) spaties in een Nederlands internationaal telefoonnummer
 * voor de leesbaarheid.
 *
 * @param telefoonnummer - Origineel telefoonnummer
 */
export function format_telefoonnummer(telefoonnummer: string): string {
  const patronen = [
    // Viercijferige netnummers
    /^(\+31)((?:11|16|17|18|22|25|29|31|32|34|41|44|47|47|48|49|51|52|54|56|57|59|67|80|90)[0-9])([0-9]{2})([0-9]{2})([0-9]{2})$/,
    // Eencijferige netnummers
    /^(\+31)(6)([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})$/,
    // Tweecijferige netnummers
    /^(\+31)([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/,
  ];
  for (const patroon of patronen) {
    const m = telefoonnummer.match(patroon);
    if (m !== null && m.length > 0) {
      m.shift();
      return m.join(" ");
    }
  }
  return telefoonnummer;
}

export function get_random_string(lengte: number) {
  let respons = "";
  while (respons.length < lengte) {
    respons += Math.floor(Math.random() * Number.MAX_SAFE_INTEGER).toString(36);
  }
  return respons.substring(0, lengte);
}

/**
 * Formatteert een duur in seconden naar een string in het formaat "[U.]M.S".
 */
export function format_duur(duur: number | null): string {
  if (duur === null) {
    return "";
  }
  const uren = Math.floor(duur / 3600);
  const minuten = Math.floor((duur % 3600) / 60);
  const seconden = duur % 60;
  return uren === 0
    ? `${String(minuten)}.${String(seconden).padStart(2, "0")}`
    : `${String(uren)}.${String(minuten).padStart(2, "0")}.${String(seconden).padStart(2, "0")}`;
}

/**
 * Haalt de configuratie van de server op en zet CSS-klassen op de body
 * afhankelijk van de configuratie.
 *
 * @returns De opgehaalde configuratie.
 */
export async function set_config_classes(): Promise<Config> {
  const config = await api.post("get_config", {});
  if (config.heeft_openai_key) {
    document.body.classList.add("config-heeft-openai-key");
  }
  if (config.heeft_recaptcha_key) {
    document.body.classList.add("config-heeft-recaptcha-key");
  }
  return config;
}

/**
 * Geeft een leesbare tekst die aangeeft hoe lang het duurt tot de stemmen geopend zijn.
 */
export function get_stemlijst_actief_vanaf_tekst(dt: Date): string {
  const nu = new Date();
  const verschil = dt.getTime() - nu.getTime();

  const format_datum = (datum: Date): string => {
    if (datum.getFullYear() === nu.getFullYear()) {
      const formatter = new Intl.DateTimeFormat("nl-NL", {
        weekday: "long",
        day: "numeric",
        month: "long",
      });
      return `vanaf ${formatter.format(datum)}`;
    }
    const formatter = new Intl.DateTimeFormat("nl-NL", {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    });
    return `vanaf ${formatter.format(datum)}`;
  };

  if (verschil <= 0) {
    return format_datum(dt);
  }

  if (verschil < 60_000) {
    return `over ${Math.max(1, Math.ceil(verschil / 1000))} seconden`;
  }

  if (verschil < 3_600_000) {
    return `over ${Math.max(1, Math.ceil(verschil / 60_000))} minuten`;
  }

  if (verschil < 14_400_000) {
    return `over ${Math.max(1, Math.ceil(verschil / 3_600_000))} uren`;
  }

  const start_vandaag = new Date(nu);
  start_vandaag.setHours(0, 0, 0, 0);

  const start_morgen = new Date(start_vandaag);
  start_morgen.setDate(start_morgen.getDate() + 1);

  const is_vandaag =
    dt.getFullYear() === nu.getFullYear() &&
    dt.getMonth() === nu.getMonth() &&
    dt.getDate() === nu.getDate();

  const is_morgen =
    dt.getFullYear() === start_morgen.getFullYear() &&
    dt.getMonth() === start_morgen.getMonth() &&
    dt.getDate() === start_morgen.getDate();

  if (is_morgen) {
    return "vanaf morgen";
  }

  if (is_vandaag) {
    return `vanaf ${String(dt.getHours())}.${String(dt.getMinutes()).padStart(2, "0")} uur`;
  }

  return format_datum(dt);
}

/**
 * Zet een Date om naar een string in het formaat dat gebruikt wordt door datetime-local inputs.
 *
 * @param dt - De datum die omgezet moet worden.
 * @returns De datum als string in het formaat YYYY-MM-DDTHH:MM.
 */
export function date_to_datetime_local(dt: Date | string): string {
  if (typeof dt === "string") {
    dt = new Date(dt);
    if (isNaN(dt.getTime())) {
      throw new Error("Ongeldige datum");
    }
  }
  const pad = (n: number) => String(n).padStart(2, "0");

  return (
    `${dt.getFullYear()}-` +
    `${pad(dt.getMonth() + 1)}-` +
    `${pad(dt.getDate())}T` +
    `${pad(dt.getHours())}:` +
    `${pad(dt.getMinutes())}`
  );
}

/**
 * Zet de waarde van een datetime-local input om naar een Date object.
 *
 * @param value - De waarde van een datetime-local input.
 * @returns De datum als Date object, of null als de waarde leeg is.
 */
export function get_datetime_local_value(value?: string): Date | null {
  if (value == null || value.trim() === "") {
    return null;
  }
  const dt = new Date(value);
  if (isNaN(dt.getTime())) {
    throw new Error("Ongeldige datum");
  }
  return dt;
}

/**
 * Haalt de waarde van een query parameter op. Dit moet een positief geheel getal zijn.
 */
export function get_query_param_id(key: string): number | null {
  const params = new URLSearchParams(document.location.search);
  const str = params.get(key);
  if (str == null) {
    return null;
  }
  const id = Number.parseInt(str);
  if (Number.isNaN(id) || id <= 0) {
    return null;
  }
  return id;
}

/**
 * Stelt de query parameter "lijst" in. Werkt ook alle navigatielinks bij zodat andere pagina’s met dezelfde lijst
 * worden geopend.
 */
export function set_lijst_query_param(lijst_id: number | null): void {
  const url = new URL(window.location.href);
  if (lijst_id !== null) {
    url.searchParams.set("lijst", String(lijst_id));
  } else {
    url.searchParams.delete("lijst");
  }
  window.history.replaceState({}, "", url);

  for (const nav_link of gld.querySelectorAllTagName("a", ".nav-link")) {
    const nav_url = new URL(nav_link.href);
    if (lijst_id !== null) {
      nav_url.searchParams.set("lijst", String(lijst_id));
    } else {
      nav_url.searchParams.delete("lijst");
    }
    nav_link.href = nav_url.toString();
  }
}

export function validate_datatable_row(data: unknown): DataTableRow {
  if (!Array.isArray(data) || data.length !== 4) {
    throw new Error("Invalid data table row");
  }
  return [
    String(data[0]),
    String(data[1]),
    String(data[2]),
    data[3] == null ? null : String(data[3]),
  ];
}
