// Libraries js
import "bootstrap";
import { Modal } from "bootstrap";
import "datatables.net-select-bs5";
import * as gld from "gld-ts-lib/functions";

// Project js
import * as functies from "@muzieklijsten/functies";
import * as api from "@muzieklijsten/api";
import { TypedEvent } from "gld-ts-lib";

// css
import "./beheer-modal.scss";

// HTML
import html_beheer_modal from "./beheer-modal.html";

class BeheerModal {
  /** @type {?number} */
  lijst_id;
  /** @type {HTMLDivElement} */
  e_modal;
  /** @type {Modal} */
  modal;
  /** @type {HTMLFormElement} */
  e_form;
  /** @type {HTMLDivElement} */
  e_velden_zichtbaar_kolom;
  /** @type {HTMLDivElement} */
  e_velden_verplicht_kolom;
  /** @type {TypedEvent<{id: number, naam: string}>} */
  on_lijst_gemaakt;
  /** @type {TypedEvent<{id: number, naam: string}>} */
  on_lijst_veranderd;
  /** @type {TypedEvent<{id: number}>} */
  on_lijst_verwijderd;

  /**
   *
   * @param {?number} lijst_id
   * @param {Promise<{heeft_openai_key: boolean; heeft_recaptcha_key: boolean}>} config_promise
   */
  constructor(lijst_id, config_promise) {
    this.on_lijst_gemaakt = new TypedEvent();
    this.on_lijst_veranderd = new TypedEvent();
    this.on_lijst_verwijderd = new TypedEvent();
    this.lijst_id = lijst_id;
    this.e_modal = beheer_modal_template.cloneNode(true);
    this.e_form = this.e_modal.getElementsByTagName("form").item(0);
    this.e_velden_zichtbaar_kolom = this.e_modal
      .getElementsByClassName("velden-zichtbaar-kolom")
      .item(0);
    this.e_velden_verplicht_kolom = this.e_modal
      .getElementsByClassName("velden-verplicht-kolom")
      .item(0);

    config_promise.then((config) => {
      if (!config.heeft_recaptcha_key) {
        this.e_form.elements.recaptcha.disabled = true;
      }
    });

    if (this.is_nieuw()) {
      this.e_modal.classList.add("is-nieuw");
      this.maak_lege_veld_checks();
    } else {
      this.vul_data();
    }
    document.getElementsByTagName("body").item(0).appendChild(this.e_modal);

    // Lijst opslaan
    this.e_form.addEventListener("submit", this.opslaan.bind(this));

    // Lijst verwijderen
    document
      .getElementById("verwijder-lijst")
      .addEventListener("click", this.verwijder_lijst.bind(this));

    this.modal = new Modal(this.e_modal, {
      backdrop: true,
      focus: true,
      keyboard: true,
    });
    this.e_modal.addEventListener("hidden.bs.modal", this.destroy.bind(this));

    this.modal.show();
  }

  /**
   *
   * @returns {boolean}
   */
  is_nieuw() {
    return this.lijst_id === undefined;
  }

  async vul_data() {
    this.e_form.elements.lijst.value = this.lijst_id;
    const data = await api.post("get_beheer_lijstdata", {
      lijst: this.lijst_id,
    });

    for (const e_lijst_naam of this.e_modal.querySelectorAll(".lijst-naam")) {
      e_lijst_naam.textContent = data.naam;
    }
    this.e_form.elements.naam.value = data.naam;
    this.e_form.elements.starttijd.value =
      data.starttijd == null
        ? ""
        : functies.date_to_datetime_local(data.starttijd);
    this.e_form.elements.eindtijd.value =
      data.eindtijd == null
        ? ""
        : functies.date_to_datetime_local(data.eindtijd);
    this.e_form.elements.minkeuzes.value = data.minkeuzes;
    this.e_form.elements.maxkeuzes.value = data.maxkeuzes;
    this.e_form.elements.vrijekeuzes.value = data.vrijekeuzes;
    this.e_form.elements["stemmen-per-ip"].value = data.stemmen_per_ip;
    this.e_form.elements["artiest-eenmalig"].checked = data.artiest_eenmalig;
    this.e_form.elements["mail-stemmers"].checked = data.mail_stemmers;
    this.e_form.elements["random-volgorde"].checked = data.random_volgorde;
    this.e_form.elements.recaptcha.checked = data.recaptcha;
    this.e_form.elements.email.value = data.email;
    this.e_form.elements["bedankt-tekst"].value = data.bedankt_tekst;

    for (const veld of data.velden) {
      this.maak_veld_checks(veld);
    }
  }

  async maak_lege_veld_checks() {
    const velden = await api.post("get_alle_velden", {});
    for (const veld of velden) {
      this.maak_veld_checks(veld);
    }
  }

  maak_veld_checks(veld) {
    const zichtbaar_id = functies.get_random_string(16);
    const verplicht_id = functies.get_random_string(16);

    const e_zichtbaar_container = document.createElement("div");
    const e_zichtbaar_label = document.createElement("label");
    const e_zichtbaar_check = document.createElement("input");
    const e_zichtbaar_label_tekst = document.createTextNode(veld.label);
    const e_verplicht_container = document.createElement("div");
    const e_verplicht_label = document.createElement("label");
    const e_verplicht_check = document.createElement("input");
    const e_verplicht_label_tekst = document.createTextNode("Verplicht");

    this.e_velden_zichtbaar_kolom.appendChild(e_zichtbaar_container);
    e_zichtbaar_container.appendChild(e_zichtbaar_check);
    e_zichtbaar_container.appendChild(e_zichtbaar_label);
    e_zichtbaar_label.appendChild(e_zichtbaar_label_tekst);
    this.e_velden_verplicht_kolom.appendChild(e_verplicht_container);
    e_verplicht_container.appendChild(e_verplicht_check);
    e_verplicht_container.appendChild(e_verplicht_label);
    e_verplicht_label.appendChild(e_verplicht_label_tekst);

    e_zichtbaar_container.classList.add("form-check");
    e_verplicht_container.classList.add("form-check");

    e_zichtbaar_check.classList.add("form-check-input");
    e_zichtbaar_check.type = "checkbox";
    e_zichtbaar_check.id = zichtbaar_id;
    e_zichtbaar_check.name = `velden[${veld.id}][tonen]`;
    e_zichtbaar_check.checked = veld.tonen;
    e_zichtbaar_check.setAttribute("data-input-verplicht", verplicht_id);
    // Verplicht enablen/disablen na check/uncheck tonen
    e_zichtbaar_check.addEventListener(
      "change",
      this.check_verplicht.bind(this),
    );

    e_zichtbaar_label.classList.add("form-check-label");
    e_zichtbaar_label.for = zichtbaar_id;

    e_verplicht_check.classList.add("form-check-input");
    e_verplicht_check.type = "checkbox";
    e_verplicht_check.id = verplicht_id;
    e_verplicht_check.name = `velden[${veld.id}][verplicht]`;
    e_verplicht_check.checked = veld.verplicht;
    e_verplicht_check.disabled = !veld.tonen;

    e_verplicht_label.classList.add("form-check-label");
    e_verplicht_label.for = verplicht_id;
  }

  /**
   * Lijst metadata opslaan.
   */
  async opslaan(e) {
    e.preventDefault();

    const minkeuzes = Number.parseInt(
      this.e_form.elements.namedItem("minkeuzes").value,
    );
    const min_vrijekeuzes = Number.parseInt(
      this.e_form.elements.namedItem("vrijekeuzes").value,
    );
    if (minkeuzes + min_vrijekeuzes < 1) {
      alert(
        "Er moet minimaal 1 keuze mogelijk zijn (gewone keuzes + vrije keuzes).",
      );
      return;
    }

    const fd = new FormData(this.e_form);
    fd.set(
      "starttijd",
      functies.get_datetime_local_value(fd.get("starttijd"))?.toISOString() ??
        "",
    );
    fd.set(
      "eindtijd",
      functies.get_datetime_local_value(fd.get("eindtijd"))?.toISOString() ??
        "",
    );

    if (this.is_nieuw()) {
      try {
        const lijst_id = await api.post("lijst_maken", fd);
        this.on_lijst_gemaakt.emit({
          id: lijst_id,
          naam: fd.get("naam"),
        });
        this.modal.hide();
      } catch (msg) {
        alert(msg);
      }
    } else {
      try {
        await api.post("lijst_opslaan", fd);
        this.on_lijst_veranderd.emit({
          id: this.lijst_id,
          naam: fd.get("naam"),
        });
        this.modal.hide();
      } catch (msg) {
        alert(msg);
      }
    }
  }

  async verwijder_lijst(e) {
    const vraag =
      "Deze lijst verwijderen?\nOok alle stemmen op nummers uit deze lijst worden verwijderd.";
    if (confirm(vraag)) {
      try {
        await api.post("verwijder_lijst", { lijst: this.lijst_id });
        this.on_lijst_verwijderd.emit({
          id: this.lijst_id,
        });
        this.modal.hide();
      } catch (msg) {
        alert(msg);
      }
    }
  }

  /**
   * Handler voor wanneer er een vinkje verandert in de invoervelden.
   * Wanneer een vinkje wordt uitgezet wordt het bijbehorende 'verplicht'-vinkje
   * uitgezet en disabled.
   * @param {Event} e
   */
  check_verplicht(e) {
    const verplicht_id = e.target.getAttribute("data-input-verplicht");
    const verplicht_elem = document.getElementById(verplicht_id);
    if (verplicht_elem instanceof HTMLInputElement) {
      if (e.target.checked) {
        verplicht_elem.disabled = false;
      } else {
        verplicht_elem.disabled = true;
        verplicht_elem.checked = false;
      }
    }
  }

  destroy() {
    this.e_modal.remove();
  }
}

/** @type {HTMLElement} */
const beheer_modal_template =
  functies.get_html_template_enkel(html_beheer_modal);

export { BeheerModal };
