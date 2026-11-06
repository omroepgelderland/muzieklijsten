// Libraries js
import * as gld from "gld-ts-lib/functions";

// Project js
import * as functies from "@muzieklijsten/functies";
import * as api from "@muzieklijsten/api";
import { TypedEvent } from "gld-ts-lib";

// HTML
import html_template from "./resultaten-stem.html";

/**
 * Stem per nummer
 */
class ResultatenStem {
  /** @type {ResultatenNummer} */
  resultaten_nummer;
  /** @type {HTMLTableRowElement} */
  e_tr;
  /** @type {HTMLInputElement} */
  e_behandeld_input;
  /** @type {boolean} */
  is_behandeld;
  /** @type {number} */
  stemmer_id;
  /** @type {string[]} */
  metadata_voor_filter;
  /** @type {Date} */
  timestamp;
  /** @type {TypedEvent<boolean>} */
  on_change_is_behandeld;
  /** @type {TypedEvent<void>} */
  on_verwijderd;
  /** @type {boolean} */
  is_zichtbaar;

  /**
   *
   * @param {ResultatenNummer} resultaten_nummer
   * @param {HTMLElement} e_container
   * @param {any} param3
   */
  constructor(
    resultaten_nummer,
    e_container,
    { stemmer_id, ip, is_behandeld, toelichting, timestamp, velden },
  ) {
    this.on_change_is_behandeld = new TypedEvent();
    this.on_verwijderd = new TypedEvent();
    this.resultaten_nummer = resultaten_nummer;
    this.stemmer_id = stemmer_id;
    this.metadata_voor_filter = [];
    this.timestamp = new Date(timestamp);
    this.is_behandeld = is_behandeld;
    this.is_zichtbaar = true;
    this.e_tr = resultaten_stem_template.querySelector("tr").cloneNode(true);
    this.e_tr.setAttribute("data-stemmer-id", this.stemmer_id);
    this.e_behandeld_input = this.e_tr.getElementsByTagName("input").item(0);
    this.e_behandeld_input.checked = this.is_behandeld;
    this.update_behandeld();
    for (const e_timestamp of this.e_tr.getElementsByClassName(
      "stemmer-timestamp",
    )) {
      e_timestamp.appendChild(
        document.createTextNode(
          this.timestamp.toLocaleString("nl-NL", {
            dateStyle: "short",
            timeStyle: "short",
          }),
        ),
      );
    }
    for (const e_toelichting of this.e_tr.getElementsByClassName(
      "stem-toelichting",
    )) {
      e_toelichting.appendChild(document.createTextNode(toelichting));
    }
    this.maak_velden(velden);
    this.e_tr.firstChild.setAttribute("title", ip);

    e_container.appendChild(this.e_tr);
  }

  maak_velden(velden) {
    const e_insert_before = this.e_tr.firstChild;
    for (const item of velden) {
      this.metadata_voor_filter.push(item.waarde);
      const e_td = document.createElement("td");
      if (item.waarde !== null && item.waarde !== "") {
        if (item.type === "email" || item.type === "tel") {
          const e_a = document.createElement("a");
          let prefix = "";
          if (item.type === "email") {
            prefix = "mailto:";
          }
          if (item.type === "tel") {
            prefix = "tel:";
          }
          e_a.setAttribute("href", `${prefix}${item.waarde}`);
          let tekst;
          if (item.type === "tel") {
            tekst = functies.format_telefoonnummer(item.waarde);
          } else {
            tekst = item.waarde;
          }
          e_a.appendChild(document.createTextNode(tekst));
          e_td.appendChild(e_a);
        } else {
          e_td.appendChild(document.createTextNode(item.waarde));
        }
        if (
          item.type === "email" ||
          item.type === "tel" ||
          item.type === "postcode"
        ) {
          e_td.classList.add("nobreak");
        }
      }
      this.e_tr.insertBefore(e_td, e_insert_before);
    }
  }

  async behandeld_handler() {
    try {
      await api.post("stem_set_behandeld", {
        nummer: this.resultaten_nummer.nummer_id,
        lijst: this.resultaten_nummer.resultaten_modal.lijst_id,
        stemmer: this.stemmer_id,
        waarde: this.e_behandeld_input.checked,
      });
      this.is_behandeld = this.e_behandeld_input.checked;
      this.on_change_is_behandeld.emit(this.is_behandeld);
      this.update_behandeld();
    } catch (e) {
      this.e_behandeld_input.checked = !this.e_behandeld_input.checked;
    }
  }

  update_behandeld() {
    if (this.is_behandeld) {
      this.e_tr.classList.add("table-success");
    } else {
      this.e_tr.classList.remove("table-success");
    }
  }

  async verwijderen() {
    await api.post("verwijder_stem", {
      nummer: this.resultaten_nummer.nummer_id,
      lijst: this.resultaten_nummer.resultaten_modal.lijst_id,
      stemmer: this.stemmer_id,
    });
    this.e_tr.remove();
    this.on_verwijderd.emit();
  }
}

/** @type {HTMLTableElement} */
const resultaten_stem_template =
  functies.get_html_template_enkel(html_template);

export { ResultatenStem };
