// Libraries js
import { Modal } from "bootstrap";
import * as gld from "gld-ts-lib/functions";

// Project js
import * as functies from "@muzieklijsten/functies";
import * as api from "@muzieklijsten/api";
import { ResultatenNummer } from "./ResultatenNummer";

// css
import "./resultaten-modal.scss";

// HTML
import html_template from "./resultaten-modal.html";

class ResultatenModal {
  /** @type {number} */
  lijst_id;
  /** @type {HTMLDivElement} */
  e_modal;
  /** @type {Modal} */
  modal;
  /** @type {HTMLSpanElement} */
  e_totaal_aantal_stemmen;
  /** @type {HTMLSpanElement} */
  e_totaal_aantal_stemmers;
  /** @type {HTMLFormElement} */
  e_filters_form;
  /** @type {HTMLButtonElement} */
  e_exportknop;
  /** @type {HTMLTableSectionElement} */
  e_resultaten_tabel;
  /** @type {{[key: number]: ResultatenNummer}} */
  resultaten_nummers;
  /** @type {number} */
  totaal_aantal_stemmen;

  /**
   *
   * @param {number} lijst_id
   * @param {string} lijst_naam
   */
  constructor(lijst_id, lijst_naam) {
    this.lijst_id = lijst_id;
    this.resultaten_nummers = {};
    this.totaal_aantal_stemmen = 0;

    this.e_modal = modal_template.cloneNode(true);
    this.e_totaal_aantal_stemmen = this.e_modal
      .getElementsByClassName("totaal-aantal-stemmen")
      .item(0);
    this.e_totaal_aantal_stemmers = this.e_modal
      .getElementsByClassName("totaal-aantal-stemmers")
      .item(0);
    this.e_filters_form = this.e_modal.querySelector("form.filters");
    this.e_filters_form.addEventListener("submit", this.filter.bind(this));
    this.e_filters_form.addEventListener("reset", this.filter_reset.bind(this));
    this.e_exportknop = this.e_modal.querySelector("#export-resultaten");
    this.e_resultaten_tabel = this.e_modal.querySelector(".resultaten-tabel");
    this.e_resultaten_tabel.id = functies.get_random_string(12);

    this.set_totaal_aantal_stemmers();
    this.maak_resultaten_tabel();

    for (const e_lijst_naam of this.e_modal.querySelectorAll(".lijst-naam")) {
      e_lijst_naam.textContent = lijst_naam;
    }

    document.getElementsByTagName("body").item(0).appendChild(this.e_modal);
    this.modal = new Modal(this.e_modal, {
      backdrop: true,
      focus: true,
      keyboard: true,
    });
    this.e_modal.addEventListener("hidden.bs.modal", () => {
      this.e_modal.remove();
    });

    this.e_modal.addEventListener("click", this.click_handler.bind(this));
    this.e_modal.addEventListener("change", this.change_handler.bind(this));

    this.modal.show();
  }

  async maak_resultaten_tabel() {
    const labels_promise = api.post("get_resultaten_labels", {
      lijst: this.lijst_id,
    });
    const nummers_stemmen = await api.post("get_resultaten", {
      lijst: this.lijst_id,
    });
    this.totaal_aantal_stemmen = 0;
    for (const nummer_stemmen of nummers_stemmen) {
      const resultaten_nummer = new ResultatenNummer(
        this,
        this.e_resultaten_tabel,
        labels_promise,
        nummer_stemmen.stemmen,
        nummer_stemmen.nummer,
      );
      resultaten_nummer.on_stem_verwijderd.on(
        this.stem_verwijderd_handler.bind(this),
      );
      resultaten_nummer.on_verwijderd.on(
        this.nummer_verwijderd_handler.bind(this),
      );
      this.resultaten_nummers[nummer_stemmen.nummer.id] = resultaten_nummer;
      this.add_totaal_aantal_stemmen(nummer_stemmen.stemmen.length);
    }
  }

  /**
   *
   * @param {number} aantal
   */
  add_totaal_aantal_stemmen(aantal) {
    this.totaal_aantal_stemmen += aantal;
    this.e_totaal_aantal_stemmen.innerText = this.totaal_aantal_stemmen;
  }

  async set_totaal_aantal_stemmers() {
    // const van = this.get_van();
    // const tot = this.get_tot();
    const request = { lijst: this.lijst_id };
    // if ( van != null ) {
    //   request.van = van.toISOString();
    // }
    // if ( tot != null ) {
    //   request.tot = tot.toISOString();
    // }
    this.e_totaal_aantal_stemmers.innerText = await api.post(
      "get_totaal_aantal_stemmers",
      request,
    );
  }

  stem_verwijderd_handler() {
    this.add_totaal_aantal_stemmen(-1);
    this.set_totaal_aantal_stemmers();
  }

  /**
   *
   * @param {number} aantal_stemmen
   */
  nummer_verwijderd_handler(aantal_stemmen) {
    this.add_totaal_aantal_stemmen(-aantal_stemmen);
    this.set_totaal_aantal_stemmers();
  }

  /**
   *
   * @param {Event} event
   */
  async filter(event) {
    event.preventDefault();
    let nummers_tekst =
      this.e_filters_form.elements["filter-nummers"].value.toLowerCase();
    let stemmers_tekst =
      this.e_filters_form.elements["filter-stemmers"].value.toLowerCase();
    if (nummers_tekst.length < 3) {
      nummers_tekst = "";
      this.e_filters_form.elements["filter-nummers"].value = "";
    }
    if (stemmers_tekst.length < 3) {
      stemmers_tekst = "";
      this.e_filters_form.elements["filter-stemmers"].value = "";
    }
    for (const [nummer_id, resultaten_nummer] of Object.entries(
      this.resultaten_nummers,
    )) {
      resultaten_nummer.filter(
        nummers_tekst,
        stemmers_tekst,
        this.get_van(),
        this.get_tot(),
      );
    }
    // await this.set_totaal_aantal_stemmers();
  }

  /**
   *
   * @param {Event} event
   */
  filter_reset(event) {
    for (const element of this.e_filters_form.elements) {
      if (element.type === "text" || element.type === "date") {
        element.value = "";
      }
    }
    this.filter(event);
  }

  /**
   * Handler voor alle kliks in het modal.
   * @param {Event} event
   */
  click_handler(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    if (target.closest("#export-resultaten")) {
      this.exporteer_resultaten_handler();
      return;
    }
    if (target.closest(".stem-verwijderen")) {
      this.stem_verwijderen_handler(target.closest(".stem-verwijderen"));
    }
    // In- en uitklappen van stemmen op een nummer.
    if (target.closest(".nummer")) {
      this.toggle_nummer_stemmen_handler(target.closest(".nummer"));
    }
  }

  /**
   * Handler voor alle change events in het modal.
   * @param {Event} event
   */
  change_handler(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    if (target.closest(".behandeld-check")) {
      this.check_behandeld_handler(target.closest(".behandeld-check"));
    }
  }

  /**
   * In- en uitklappen van de stemmen op een nummer.
   *
   * @private
   * @param {HTMLElement} elem
   */
  toggle_nummer_stemmen_handler(elem) {
    const nummer_id = Number.parseInt(elem.getAttribute("data-nummer-id"));
    const resultaten_nummer = this.resultaten_nummers[nummer_id];
    resultaten_nummer.toggle();
  }

  /**
   * Handler voor een klik op het kruisje om een stem te verwijderen.
   * @param {HTMLInputElement} elem - Knop waarop geklikt is.
   */
  async stem_verwijderen_handler(elem) {
    const stemmer_id = Number.parseInt(
      elem.closest("[data-stemmer-id]").getAttribute("data-stemmer-id"),
    );
    const nummer_id = Number.parseInt(
      elem.closest("[data-nummer-id]").getAttribute("data-nummer-id"),
    );
    /** @type {ResultatenNummer} */
    const resultaten_nummer = this.resultaten_nummers[nummer_id];
    const resultaten_stem = resultaten_nummer.get_stem(stemmer_id);
    await resultaten_stem.verwijderen();
  }

  /**
   * Change event op een vinkhokje is_behandeld.
   * @param {HTMLInputElement} elem - Vinkhokje dat veranderd is.
   */
  async check_behandeld_handler(elem) {
    const stemmer_id = Number.parseInt(
      elem.closest("[data-stemmer-id]").getAttribute("data-stemmer-id"),
    );
    const nummer_id = Number.parseInt(
      elem.closest("[data-nummer-id]").getAttribute("data-nummer-id"),
    );
    /** @type {ResultatenNummer} */
    const resultaten_nummer = this.resultaten_nummers[nummer_id];
    const resultaten_stem = resultaten_nummer.get_stem(stemmer_id);
    await resultaten_stem.behandeld_handler();
  }

  async exporteer_resultaten_handler() {
    if (!(this.e_exportknop instanceof HTMLButtonElement)) {
      return;
    }
    if (this.e_exportknop.disabled) {
      return;
    }

    const icon_ready = this.e_exportknop.querySelector(".export-icon-ready");
    const icon_loading = this.e_exportknop.querySelector(
      ".export-icon-loading",
    );
    if (!(icon_ready instanceof HTMLElement)) {
      return;
    }
    if (!(icon_loading instanceof HTMLElement)) {
      return;
    }

    this.e_exportknop.disabled = true;
    icon_ready.classList.add("d-none");
    icon_loading.classList.remove("d-none");

    try {
      const form_data = new FormData();
      form_data.append("lijst", String(this.lijst_id));
      const response = await fetch("export_resultaten_ods.php", {
        method: "POST",
        body: form_data,
      });
      if (!response.ok) {
        throw new Error(await response.text());
      }

      const blob = await response.blob();
      let filename = "resultaten.ods";
      const disposition = response.headers.get("Content-Disposition");
      if (typeof disposition === "string") {
        const utf8_match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
        const plain_match = disposition.match(/filename="?([^";]+)"?/i);
        const matched = utf8_match?.[1] ?? plain_match?.[1];
        if (matched) {
          filename = decodeURIComponent(matched);
        }
      }

      const blob_url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = blob_url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(blob_url);
    } catch (error) {
      alert(error instanceof Error ? error.message : String(error));
    } finally {
      this.e_exportknop.disabled = false;
      icon_ready.classList.remove("d-none");
      icon_loading.classList.add("d-none");
    }
  }

  /**
   * @returns {Date|null}
   */
  get_van() {
    const van = new Date(this.e_filters_form.elements.van.value);
    if (isNaN(van)) {
      return null;
    } else {
      van.setHours(0);
      van.setMinutes(0);
      van.setSeconds(0);
      van.setMilliseconds(0);
      return van;
    }
  }

  /**
   * @returns {Date|null}
   */
  get_tot() {
    const tot = new Date(this.e_filters_form.elements.tot.value);
    if (isNaN(tot)) {
      return null;
    } else {
      tot.setDate(tot.getDate() + 1);
      tot.setHours(0);
      tot.setMinutes(0);
      tot.setSeconds(0);
      tot.setMilliseconds(0);
      return tot;
    }
  }
}

/** @type {HTMLElement} */
const modal_template = functies.get_html_template_enkel(html_template);

export { ResultatenModal };
