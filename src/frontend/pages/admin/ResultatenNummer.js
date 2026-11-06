// Libraries js
import * as gld from "gld-ts-lib/functions";

// Project js
import * as functies from "@muzieklijsten/functies";
import * as api from "@muzieklijsten/api";
import { TypedEvent } from "gld-ts-lib";
import { ResultatenStem } from "./ResultatenStem";

// HTML
import html_template from "./resultaten-nummer.html";

/**
 * Een nummer in de lijst met resultaten met uitklapbare lijst met stemmen op dat nummer.
 */
class ResultatenNummer {
  /** @type {ResultatenModal} @public */
  resultaten_modal;
  /** @type {HTMLTableSectionElement} */
  e_container;
  /** @type {{[key: number]: ResultatenStem}} */
  resultaten_stemmen;
  /** @type {HTMLTableRowElement} */
  e_tr_uitklap;
  /** @type {HTMLTableRowElement} */
  e_tr_gegevens;
  /** @type {?HTMLTableCellElement} */
  e_aantal_stemmen;
  /** @type {number} */
  nummer_id;
  /** @type {string} */
  titel;
  /** @type {string} */
  artiest;
  /** @type {number | null} */
  duur;
  /** @type {TypedEvent<void>} */
  on_stem_verwijderd;
  /** @type {TypedEvent<number>} */
  on_verwijderd;
  /** @type {boolean} */
  is_zichtbaar;
  /** @type {Promise<string[]>} */
  labels_promise;
  stemmen;
  /** @type {{ip: string, is_behandeld: boolean, stemmer_id: number, timestamp: string, toelichting: string, velden: {type: string, waarde: string}[]}} */
  stemmen_gefilterd;

  /**
   *
   * @param {ResultatenModal} resultaten_modal
   * @param {HTMLTableSectionElement} e_container
   * @param {Promise<string[]>} labels_promise
   * @param {{ip: string, is_behandeld: boolean, stemmer_id: number, timestamp: string, toelichting: string, velden: {type: string, waarde: string}[]}[]} stemmen
   * @param {{artiest: string, duur: number | null, id: number, is_vrijekeuze: boolean, titel: string}} nummer
   */
  constructor(resultaten_modal, e_container, labels_promise, stemmen, nummer) {
    this.resultaten_stemmen = {};
    this.on_stem_verwijderd = new TypedEvent();
    this.on_verwijderd = new TypedEvent();
    this.resultaten_modal = resultaten_modal;
    this.e_container = e_container;
    this.is_zichtbaar = true;
    this.labels_promise = labels_promise;
    this.stemmen = this.stemmen_gefilterd = stemmen;
    this.stemmen_filter = null;

    this.e_tr_uitklap = resultaten_nummer_template
      .querySelector("tr.nummer")
      .cloneNode(true);
    this.e_aantal_stemmen = this.e_tr_uitklap
      .getElementsByClassName("aantal-stemmen")
      .item(0);

    // this.e_tr_uitklap.getElementsByClassName('verwijder-nummer').item(0).addEventListener('click', this.verwijderen.bind(this));

    this.e_container.appendChild(this.e_tr_uitklap);
    this.set_nummer(nummer);
    if (this.stemmen.length > 0) {
      this.e_tr_uitklap.classList.add("heeft-stemmen");
    }
    this.update_aantal_stemmen();
    this.update_behandeld();
  }

  /**
   * Plaats metadata van het nummer.
   * @param {{artiest: string, duur: number | null, id: number, is_vrijekeuze: boolean, titel: string}} param0
   */
  set_nummer({ id, titel, artiest, duur, is_vrijekeuze }) {
    this.nummer_id = id;
    this.titel = titel;
    this.artiest = artiest;
    this.duur = duur;

    this.e_tr_uitklap.id = `nummer-${this.nummer_id}`;
    this.e_tr_uitklap.setAttribute("data-nummer-id", this.nummer_id);

    for (const e_nummer_titel of this.e_tr_uitklap.getElementsByClassName(
      "nummer-titel",
    )) {
      e_nummer_titel.appendChild(document.createTextNode(this.titel));
    }
    for (const e_nummer_artiest of this.e_tr_uitklap.getElementsByClassName(
      "nummer-artiest",
    )) {
      e_nummer_artiest.appendChild(document.createTextNode(this.artiest));
    }
    for (const e_nummer_duur of this.e_tr_uitklap.getElementsByClassName(
      "nummer-duur",
    )) {
      e_nummer_duur.appendChild(
        document.createTextNode(functies.format_duur(this.duur)),
      );
    }
    if (is_vrijekeuze) {
      this.e_tr_uitklap.classList.add("vrijekeuze");
    }
  }

  /**
   * Maak de kolomtitels van de invulvelden van de stemmen (naam, e-mail e.d.).
   */
  async maak_velden_labels() {
    if (this.e_tr_gegevens == null) {
      throw new Error("Nummer is niet uitgeklapt");
    }
    const e_container = this.e_tr_gegevens.querySelector("thead tr");
    const e_insert_before = e_container.firstChild;
    const labels = await this.labels_promise;
    for (const label of labels) {
      const e_label = document.createElement("th");
      e_label.appendChild(document.createTextNode(label));
      e_container.insertBefore(e_label, e_insert_before);
    }
  }

  /**
   * Maakt views voor de stemmen. Alleen aanroepen bij het uitklappen.
   */
  maak_stemmen() {
    if (this.e_tr_gegevens == null) {
      throw new Error("Nummer is niet uitgeklapt");
    }
    const e_tbody = this.e_tr_gegevens.getElementsByTagName("tbody").item(0);
    for (const stem of this.stemmen_gefilterd) {
      const resultaten_stem = new ResultatenStem(this, e_tbody, stem);
      resultaten_stem.on_change_is_behandeld.on(
        this.behandeld_change_handler.bind(this, stem.stemmer_id),
      );
      resultaten_stem.on_verwijderd.on(
        this.stem_verwijderd_handler.bind(this, stem.stemmer_id),
      );
      this.resultaten_stemmen[stem.stemmer_id] = resultaten_stem;
    }
  }

  /**
   * Zet een stem op behandeld of niet na een klik op het vinkhokje.
   * @param {number} stemmer_id
   * @param {boolean} is_behanded
   */
  behandeld_change_handler(stemmer_id, is_behandeld) {
    const i = this.stemmen.findIndex((stem) => stem.stemmer_id === stemmer_id);
    this.stemmen[i].is_behandeld = is_behandeld;
    this.update_behandeld();
  }

  /**
   * Maakt de regel groen als alle stemmen behandeld zijn (ook de stemmen buiten het filter)
   */
  update_behandeld() {
    if (this.stemmen.every((stem) => stem.is_behandeld)) {
      this.e_tr_uitklap.classList.add("table-success");
    } else {
      this.e_tr_uitklap.classList.remove("table-success");
    }
  }

  /**
   * Verwijdert een stem en eventueel de hele regel als er geen stemmen meer zijn.
   * @param {number} stemmer_id
   */
  stem_verwijderd_handler(stemmer_id) {
    const i = this.stemmen.findIndex((stem) => stem.stemmer_id === stemmer_id);
    if (i === -1) {
      throw new Error("stem niet gevonden");
    }
    this.stemmen.splice(i, 1);
    delete this.resultaten_stemmen[stemmer_id];
    this.on_stem_verwijderd.emit();
    this.update_aantal_stemmen();
    if (this.stemmen.length === 0) {
      // Nummer heeft geen stemmen meer. Element verwijderen.
      this.inklappen();
      this.e_tr_uitklap.remove();
    }
  }

  /**
   * Update het veld met het aantal stemmen op dit nummer.
   */
  update_aantal_stemmen() {
    this.e_aantal_stemmen.innerText = this.stemmen.length;
  }

  // async verwijderen(e) {
  //   e.stopPropagation();
  //   if ( confirm('Dit nummer, inclusief alle stemmen hierop, verwijderen uit de stemlijst?') ) {
  //     const aantal_stemmen = this.get_aantal_stemmen();
  //     await api.post('verwijder_nummer', {
  //       lijst: this.lijst_id,
  //       nummer: this.nummer_id
  //     });
  //     this.e_tr_gegevens.remove();
  //     this.e_tr_uitklap.remove();
  //     this.on_verwijderd.emit(aantal_stemmen);
  //   }
  // }

  /**
   * Stelt een filter in. De regel wordt ingeklapt.
   *
   * @param {string} nummers_tekst
   * @param {string} stemmers_tekst
   * @param {Date|null} van
   * @param {Date|null} tot
   */
  filter(nummers_tekst, stemmers_tekst, van, tot) {
    this.inklappen();
    // Filter het nummer.
    this.is_zichtbaar =
      nummers_tekst == "" ||
      this.titel.toLowerCase().includes(nummers_tekst) ||
      this.artiest.toLowerCase().includes(nummers_tekst);

    if (this.is_zichtbaar) {
      // Filter de stemmen.
      this.filter_stemmen({
        tekst: stemmers_tekst,
        van: van,
        tot: tot,
      });
      this.is_zichtbaar &= this.stemmen_gefilterd.length > 0;
    }

    if (this.is_zichtbaar) {
      this.e_tr_uitklap.hidden = false;
    } else {
      this.e_tr_uitklap.hidden = true;
    }
  }

  /**
   * Past een filter toe. De gefilterde stemmen worden in stemmen_gefilterd opgeslagen.
   *
   * @param {{tekst: string, van: ?Date, tot: ?Date}} filter
   */
  filter_stemmen(filter) {
    if (filter.tekst === "" && filter.van == null && filter.tot == null) {
      // Geen filter
      this.stemmen_gefilterd = this.stemmen;
      return;
    }

    this.stemmen_gefilterd = [];
    for (const stem of this.stemmen) {
      const metadata_voor_filter =
        stem.velden
          .map((veld) => veld.waarde)
          .join(" ")
          .toLowerCase() + stem.toelichting;
      if (
        filter.tekst !== "" &&
        filter.tekst !== null &&
        !metadata_voor_filter.includes(filter.tekst)
      ) {
        continue;
      }
      if (filter.van != null && new Date(stem.timestamp) < filter.van) {
        continue;
      }
      if (filter.tot != null && new Date(stem.timestamp) > filter.tot) {
        continue;
      }
      this.stemmen_gefilterd.push(stem);
    }
  }

  is_uitgeklapt() {
    return this.e_tr_gegevens != null;
  }

  /**
   * Verwijdert de tabel met stemmers op dit nummer.
   */
  inklappen() {
    if (this.e_tr_gegevens != null) {
      this.e_tr_gegevens.remove();
      this.e_tr_gegevens = undefined;
      this.e_tr_uitklap.classList.add("collapsed");
    }
  }

  /**
   * Genereert de HTML van de tabel met stemmers op dit nummer en plaatst dit op de pagina.
   */
  uitklappen() {
    if (this.e_tr_gegevens == null) {
      this.e_tr_uitklap.classList.remove("collapsed");
      this.e_tr_gegevens = resultaten_nummer_template
        .querySelector("tr.stemmers")
        .cloneNode(true);
      this.e_tr_uitklap.after(this.e_tr_gegevens);
      this.e_tr_gegevens.id = `nummer-stemmers-${this.nummer_id}`;
      this.e_tr_gegevens.setAttribute("data-nummer-id", this.nummer_id);
      this.maak_velden_labels();
      this.maak_stemmen();
    }
  }

  /**
   * Toont de tabel met stemmen als die nu niet zichtbaar is en andersom.
   */
  toggle() {
    if (this.is_uitgeklapt()) {
      this.inklappen();
    } else {
      this.uitklappen();
    }
  }

  /**
   * @param {number} stemmer_id
   * @returns {ResultatenStem}
   */
  get_stem(stemmer_id) {
    return this.resultaten_stemmen[stemmer_id];
  }
}

/** @type {HTMLTableElement} */
const resultaten_nummer_template =
  functies.get_html_template_enkel(html_template);

export { ResultatenNummer };
