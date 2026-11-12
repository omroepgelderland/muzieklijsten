// Libraries js
import "bootstrap";
import DataTable from "datatables.net-bs5";
import "datatables.net-select-bs5";
import * as gld from "gld-ts-lib/functions";
import { TypedEvent } from "gld-ts-lib";
import type { Context, Api } from "datatables.net-bs5";

// Project js
import * as api from "@muzieklijsten/api";
import * as functies from "@muzieklijsten/functies";
import type { FrontendVeld, LijstFrontendData } from "@muzieklijsten/api/types";
import type { DataTableRow } from "@muzieklijsten/types";

// css
import "./index.scss";

// HTML
import html_keuze_rij from "./keuze-rij.html";

// Afbeeldingen
// import '/assets/afbeeldingen/fbshare_top100.jpg';

/**
 * Een door de bezoeker geselecteerd nummer.
 * Maakt een regel in de DOM bij de geselecteerde nummers en inputs in het formulier zodat de nummer-ids bij het stemmen
 * in de formuliervelden staan.
 */
class Nummer {
  private readonly e_tr: HTMLTableRowElement;

  constructor(nummer_id: number, titel: string, artiest: string) {
    this.e_tr = keuze_rij_template.cloneNode(true) as HTMLTableRowElement;

    const e_td_titel = gld.querySelectorTagName(
      "td",
      ".keuze-titel",
      this.e_tr,
    );
    e_td_titel.textContent = titel;

    const e_td_artiest = gld.querySelectorTagName(
      "td",
      ".keuze-artiest",
      this.e_tr,
    );
    e_td_artiest.textContent = artiest;

    const e_toelichting = gld.querySelectorTagName(
      "input",
      ".keuze-toelichting",
      this.e_tr,
    );
    e_toelichting.name = `nummers[${nummer_id}][toelichting]`;

    const e_hidden = gld.querySelectorTagName("input", ".keuze-id", this.e_tr);
    e_hidden.name = `nummers[${nummer_id}][id]`;
    e_hidden.value = String(nummer_id);

    const keuzes_tbody =
      gld.querySelector<HTMLTableSectionElement>("#keuzes tbody");
    keuzes_tbody.appendChild(this.e_tr);
  }

  /**
   * Verwijdert het nummer uit de DOM.
   */
  public destroy() {
    this.e_tr.remove();
  }
}

/**
 * Een invoerveld met info over de stemmer.
 */
class Invoerveld {
  private readonly e_form_group: HTMLDivElement;

  constructor({
    id,
    label,
    leeg_feedback,
    max,
    maxlength,
    min,
    minlength,
    placeholder,
    type,
    verplicht,
  }: FrontendVeld) {
    const id_str = `veld-${id}`;
    const naam = `velden[${id}]`;

    this.e_form_group = document.createElement("div");
    this.e_form_group.classList.add("mb-3", "row");

    const e_label = document.createElement("label");
    this.e_form_group.appendChild(e_label);
    e_label.classList.add("col-form-label", "col-md-3", "col-lg-2");
    e_label.setAttribute("for", id_str);
    e_label.appendChild(document.createTextNode(label));

    const e_col = document.createElement("div");
    this.e_form_group.appendChild(e_col);
    e_col.classList.add("col-md-9", "col-lg-10");

    let e_input: HTMLInputElement | HTMLTextAreaElement;
    if (type === "textarea") {
      e_input = document.createElement("textarea");
      e_input.rows = 5;
    } else {
      e_input = document.createElement("input");
      if (type === "postcode") {
        e_input.type = "text";
        e_input.classList.add("postcode");
      } else {
        e_input.type = type;
      }
      if (Number.isInteger(max)) {
        e_input.max = String(max);
      }
      if (Number.isInteger(min)) {
        e_input.min = String(min);
      }
    }
    e_col.appendChild(e_input);
    e_input.classList.add("form-control");
    e_input.id = id_str;
    e_input.name = naam;
    e_input.setAttribute("data-leeg-feedback", leeg_feedback);
    if (maxlength != null) {
      e_input.maxLength = maxlength;
    }
    if (minlength != null) {
      e_input.minLength = minlength;
    }
    if (typeof placeholder === "string") {
      e_input.placeholder = placeholder;
    }
    e_input.required = verplicht;
    if (verplicht) {
      e_label.classList.add("required");
    }

    gld.getElementById("formulier-velden").appendChild(this.e_form_group);
  }
}

class StemView {
  private readonly geselecteerde_nummers;
  private readonly geselecteerde_artiesten;
  private readonly datatable;
  private readonly e_body: HTMLBodyElement;
  private readonly e_keuzeformulier: HTMLFormElement;
  private readonly e_stemmerformulier: HTMLFormElement;
  private readonly random_seed: number;
  public readonly on_error;

  constructor(
    private readonly lijst_id: number,
    private readonly data: LijstFrontendData,
  ) {
    this.e_body = gld.querySelectorTagName("body");
    this.e_keuzeformulier =
      gld.getElementById<HTMLFormElement>("keuzeformulier");
    this.e_stemmerformulier =
      gld.getElementById<HTMLFormElement>("stemmerformulier");
    this.geselecteerde_nummers = new Map<number, Nummer>();
    this.geselecteerde_artiesten = new Set<string>();
    this.random_seed = Math.floor(Math.random() * 2 ** 16);
    this.on_error = new TypedEvent<unknown>();

    const e_datatable = gld.getElementById<HTMLTableElement>("nummers");
    this.datatable = new DataTable<DataTableRow>(e_datatable, {
      processing: true,
      serverSide: true,
      createdRow: (row) => {
        row.id = `nummer-${row.id}`;
      },
      ajax: (data, callback, _settings) => {
        this.datatables_ajax_handler(data, callback, _settings).catch(
          this.on_error.emit.bind(this.on_error),
        );
      },
      lengthChange: false,
      pageLength: 50,
      columns: [
        {
          data: "titel",
          name: "titel",
        },
        {
          data: "artiest",
          name: "artiest",
        },
        {
          data: "jaar",
          name: "jaar",
          type: "num",
        },
      ],
      order: [
        { name: "titel", dir: "asc" },
        { name: "artiest", dir: "asc" },
      ],
      ordering: !this.data.random_volgorde,
      language: {
        lengthMenu: "_MENU_ nummers per pagina",
        zeroRecords: "Geen nummers gevonden",
        info: "Pagina _PAGE_ van _PAGES_",
        infoEmpty: "Geen nummers gevonden",
        infoFiltered: "(gefilterd van _MAX_ totaal)",
        search: "",
        paginate: {
          first: "Eerste",
          last: "Laatste",
          next: "Volgende",
          previous: "Vorige",
        },
        select: {
          rows: "%d nummers geselecteerd",
        },
      },
      select: {
        style: "multi",
        selectable: (raw_data) => {
          return this.mag_nummer_geselecteerd_worden(
            functies.validate_datatable_row(raw_data),
          );
        },
      },
      layout: {
        topStart: {
          search: {
            placeholder: "Zoeken…",
          },
        },
        topEnd: null,
      },
    });
    this.datatable.on("select", this.select_handler.bind(this));
    this.datatable.on("deselect", this.deselect_handler.bind(this));
    this.datatable.on("draw", this.sync_datatable_selectie.bind(this));

    // Titel en overige metadata
    const titel = `${this.data.organisatie} – ${this.data.lijst_naam}`;
    const titleElem = gld.querySelectorTagName("title");
    titleElem.innerText = titel;
    for (const e_organisatie of document.querySelectorAll(".organisatie")) {
      if (e_organisatie instanceof HTMLElement) {
        e_organisatie.textContent = this.data.organisatie;
      }
    }
    for (const e_privacy_url of document.querySelectorAll("a.privacy-url")) {
      e_privacy_url.setAttribute("href", this.data.privacy_url);
    }

    // Toon recaptcha
    const e_captcha = gld.querySelector(".g-recaptcha");
    if (
      this.data.heeft_gebruik_recaptcha &&
      this.data.recaptcha_sitekey != null
    ) {
      e_captcha.setAttribute("data-sitekey", this.data.recaptcha_sitekey);
      const e_script = document.createElement("script");
      e_script.src = "https://www.google.com/recaptcha/api.js?hl=nl";
      if (e_captcha.parentElement) {
        e_captcha.parentElement.appendChild(e_script);
      }
      this.e_body.classList.add("heeft-recaptcha");
    } else {
      e_captcha.parentElement?.remove();
    }

    // Toon huidige status van de lijst (actief, gesloten, nog niet actief)
    const nu = new Date();
    const starttijd =
      this.data.starttijd === null ? null : new Date(this.data.starttijd);
    const eindtijd =
      this.data.eindtijd === null ? null : new Date(this.data.eindtijd);
    if (
      (starttijd == null || nu >= starttijd) &&
      (eindtijd == null || nu <= eindtijd)
    ) {
      this.e_body.classList.add("is-actief");
    } else if (eindtijd != null && nu > eindtijd) {
      this.e_body.classList.add("is-gesloten");
    } else {
      const e_stemmen_vanaf = gld.getElementById("stemmen-vanaf-tekst");
      if (starttijd != null) {
        e_stemmen_vanaf.textContent =
          functies.get_stemlijst_actief_vanaf_tekst(starttijd);
      }
      this.e_body.classList.add("is-nog-niet-actief");
    }

    // Toon keuzeformulier velden en vrije keuzes
    const lijstfield = this.e_keuzeformulier.elements.namedItem("lijst");
    if (lijstfield instanceof HTMLInputElement) {
      lijstfield.value = String(this.lijst_id);
    }
    for (const velddata of this.data.velden) {
      new Invoerveld(velddata);
    }
    for (let i = 0; i < this.data.vrijekeuzes; i++) {
      this.vrijkeuze_toevoegen(i, this.data.vrijekeuzes);
    }

    this.e_body.classList.add("geladen");

    // Event handlers

    this.e_stemmerformulier.addEventListener(
      "focusout",
      this.trim_handler.bind(this),
    );

    // Alle kliks
    document.addEventListener("click", this.click_handler.bind(this));

    // Insturen keuzeformulier. Gebeurt in principe niet want er is geen knop.
    this.e_keuzeformulier.addEventListener("submit", (...args) => {
      this.submit_handler(...args).catch(
        this.on_error.emit.bind(this.on_error),
      );
    });

    // Insturen
    this.e_stemmerformulier.addEventListener("submit", (...args) => {
      this.submit_handler(...args).catch(
        this.on_error.emit.bind(this.on_error),
      );
    });
  }

  private async datatables_ajax_handler(
    data: object,
    callback: (data: unknown) => void,
    _settings: Context,
  ): Promise<void> {
    const request_data = {
      ...data,
      lijst: this.lijst_id,
      is_vrijekeuze: false,
      random_seed: this.random_seed,
    };
    const respons = await api.post("vul_datatables", request_data);
    if (respons.recordsTotal === 0) {
      this.verberg_lijst();
    }
    callback(respons);
  }

  private click_handler(event: Event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    const e_keuze_annuleren = target.closest(".keuze-annuleren");
    if (e_keuze_annuleren instanceof HTMLElement) {
      this.keuze_annuleren(e_keuze_annuleren);
    }
  }

  /**
   * Verwerkt het stemmen.
   */
  private async submit_handler(event: Event) {
    event.preventDefault();
    event.stopPropagation();

    const aantal_geselecteerd_lijst = this.geselecteerde_nummers.size;
    if (aantal_geselecteerd_lijst < this.data.minkeuzes) {
      alert(
        `U moet mimimaal ${this.data.minkeuzes} nummers uit de keuzelijst selecteren.`,
      );
      return;
    }
    if (
      aantal_geselecteerd_lijst === 0 &&
      this.get_aantal_ingevulde_vrijekeuzes() === 0
    ) {
      alert("Kies ten minste één nummer.");
      return;
    }
    if (
      this.data.heeft_gebruik_recaptcha &&
      this.get_recaptcha_response() === ""
    ) {
      alert("Plaats een vinkje a.u.b.");
      return;
    }

    for (const veld of this.e_stemmerformulier.elements) {
      if (!(
        veld instanceof HTMLInputElement || veld instanceof HTMLTextAreaElement
      )) {
        continue;
      }
      if (veld.required && veld.value === "") {
        alert(veld.getAttribute("data-leeg-feedback"));
        veld.focus();
        return;
      }
      if (
        veld instanceof HTMLInputElement &&
        veld.hasAttribute("min") &&
        parseInt(veld.min) > 0 &&
        veld.valueAsNumber < parseInt(veld.min)
      ) {
        alert(
          `De waarde van ${veld.name} is te laag. Het minimum is ${veld.min}`,
        );
        return;
      }
      if (
        veld instanceof HTMLInputElement &&
        veld.hasAttribute("max") &&
        parseInt(veld.max) > 0 &&
        veld.valueAsNumber > parseInt(veld.max)
      ) {
        alert(
          `De waarde van ${veld.name} is te hoog. Het maximum is ${veld.max}`,
        );
        return;
      }
      if (veld.minLength > 0 && veld.value.length < veld.minLength) {
        alert(
          `De invoer van ${veld.name} is te kort. De minimumlengte is ${veld.minLength}`,
        );
        return;
      }
      if (veld.maxLength > 0 && veld.value.length > veld.maxLength) {
        alert(
          `De invoer van ${veld.name} is te lang. De maximumlengte is ${veld.maxLength}`,
        );
        return;
      }
    }

    const fd = new FormData(this.e_stemmerformulier);
    fd.append("lijst", String(this.lijst_id));
    try {
      const data = await api.post("stem", fd);
      const stemsegment = gld.getElementById("stemsegment");
      const hoogte = (stemsegment.offsetHeight ?? 100) - 100;
      const placeholder = gld.getElementById("stemsegment-placeholder");
      placeholder.style.height = `${hoogte}px`;
      const result = gld.getElementById("result");
      if (typeof data === "string") {
        result.innerHTML = data;
      }
      this.e_body.classList.add("gestemd");
    } catch (msg) {
      alert(msg);
    }
  }

  private get_recaptcha_response(): string {
    const elem = document.getElementById("g-recaptcha-response");
    if (!(elem instanceof HTMLInputElement)) {
      return "";
    }
    return elem.value;
  }

  /**
   * Knipt spaties en andere lege tekens weg aan het begin en einde van tekstvelden.
   */
  private trim_handler(event: Event) {
    const types = [
      "email",
      "month",
      "number",
      "tel",
      "text",
      "time",
      "url",
      "week",
    ];
    const target = event.target;
    if (
      (target instanceof HTMLInputElement && types.includes(target.type)) ||
      target instanceof HTMLTextAreaElement
    ) {
      target.value = target.value.trim();
    }
  }

  /**
   * Geeft aan of het nummer geselecteerd mag worden.
   */
  private mag_nummer_geselecteerd_worden({ artiest }: DataTableRow): boolean {
    if (this.geselecteerde_nummers.size >= this.data.maxkeuzes) {
      alert(`U kunt maximaal ${this.data.maxkeuzes} nummers selecteren.`);
      return false;
    }
    if (
      this.data.is_artiest_eenmalig &&
      this.geselecteerde_artiesten.has(artiest)
    ) {
      alert("Deze artiest is al gekozen");
      return false;
    }
    return true;
  }

  private select_handler(
    event: Event,
    dt: Api<DataTableRow>,
    type: string,
    indexes: number[],
  ) {
    const rows = this.datatable.rows(indexes).data().toArray();
    for (const row of rows) {
      const { DT_RowId, titel, artiest } = functies.validate_datatable_row(row);
      this.nummer_selecteren(DT_RowId, titel, artiest);
    }
  }

  private deselect_handler(
    e: Event,
    dt: Api<DataTableRow>,
    type: string,
    indexes: number[],
  ) {
    const rows = this.datatable.rows(indexes).data().toArray();
    for (const row of rows) {
      const { DT_RowId, artiest } = functies.validate_datatable_row(row);
      this.nummer_deselecteren(DT_RowId, artiest);
    }
  }

  /**
   * Voegt een veld met titel en artiest voor een optionele vrije keuze.
   *
   * @param nummer - Volgnummer van de vrije keuze.
   * @param totaal - Totaal Totaal aantal vrije keuzes.
   */
  private vrijkeuze_toevoegen(nummer: number, totaal: number): void {
    const label = totaal === 1 ? "Vrije keuze" : `Vrije keuze ${nummer + 1}`;

    const e_artiest_col = this.vrijkeuze_toevoegen_veld(
      "Artiest",
      nummer,
      3,
      128,
    );
    const e_titel_col = this.vrijkeuze_toevoegen_veld("Titel", nummer, 3, 128);
    const e_toelichting_col = this.vrijkeuze_toevoegen_veld(
      "Toelichting",
      nummer,
      4,
      512,
    );

    const e_form_group = document.createElement("div");
    e_form_group.classList.add("mb-3", "row");

    const e_label = document.createElement("label");
    e_form_group.appendChild(e_label);
    e_label.classList.add("col-form-label", "col-md-2");
    const input = e_artiest_col.querySelector("input");
    if (input) {
      e_label.setAttribute("for", input.id);
    }
    e_label.appendChild(document.createTextNode(label));

    e_form_group.appendChild(e_artiest_col);
    e_form_group.appendChild(e_titel_col);
    e_form_group.appendChild(e_toelichting_col);

    gld.getElementById("formulier-velden").appendChild(e_form_group);
  }

  /**
   * Titel- of artiestveld maken.
   *
   * @param nummer - Volgnummer van de vrije keuze.
   * @param breedte - Breedte van de kolom.
   * @returns Kolomelement.
   */
  private vrijkeuze_toevoegen_veld(
    veld: "Titel" | "Artiest" | "Toelichting",
    nummer: number,
    breedte: number,
    max_length: number,
  ): HTMLDivElement {
    const veld_intern = veld.toLowerCase();
    const id_str = `vrijekeuze-${veld_intern}-${nummer}`;
    const naam = `vrijekeuzes[${nummer}][${veld_intern}]`;

    const e_col = document.createElement("div");
    e_col.classList.add(`col-md-${breedte}`);

    const e_input = document.createElement("input");
    e_input.type = "text";
    e_col.appendChild(e_input);
    e_input.classList.add("form-control");
    e_input.id = id_str;
    e_input.name = naam;
    e_input.maxLength = max_length;
    e_input.placeholder = veld;
    return e_col;
  }

  /**
   * Geeft het aantal vrije keuzes waar de bezoeker zowel een titel als artiest heeft ingevuld.
   */
  private get_aantal_ingevulde_vrijekeuzes(): number {
    let aantal = 0;
    for (let i = 0; ; i++) {
      const e_artiest = document.getElementById(`vrijekeuze-artiest-${i}`);
      const e_titel = document.getElementById(`vrijekeuze-titel-${i}`);
      if (
        !(e_artiest instanceof HTMLInputElement) ||
        !(e_titel instanceof HTMLInputElement)
      ) {
        break;
      }
      if (e_artiest.value.trim() !== "" && e_titel.value.trim() !== "") {
        aantal++;
      }
    }
    return aantal;
  }

  /**
   * Verbergt de lijst als er geen nummers in staan.
   */
  private verberg_lijst() {
    this.e_keuzeformulier.hidden = true;
  }

  private keuze_annuleren(e_button: HTMLElement) {
    const e_keuzerij = e_button.closest("tr");
    if (!e_keuzerij) {
      return;
    }
    const inputElement = e_keuzerij.querySelector(
      "input.keuze-id",
    ) as HTMLInputElement;
    if (!inputElement) {
      return;
    }
    const nummer_id = Number.parseInt(inputElement.value);
    // Direct verwijderen, ook als de row niet op de huidige pagina staat.
    this.nummer_deselecteren(nummer_id, "");
    this.sync_datatable_selectie();
  }

  /**
   * Synchroniseer de DataTable visuele selectie met geselecteerde_nummers.
   * Wordt aangeroepen bij elke redraw van de DataTable (bijv. bij paginawissel).
   */
  private sync_datatable_selectie() {
    const tbody = document.querySelector("#nummers tbody");
    if (tbody == null) {
      return;
    }

    // Zet events tijdelijk uit om recursie te voorkomen.
    this.datatable.off("select deselect");

    // Verwijder alle visuele selecties.
    tbody.querySelectorAll("tr.selected").forEach((tr: Element) => {
      this.datatable.row(tr).deselect();
    });

    // Herselecteer rijen die in geselecteerde_nummers staan.
    for (const nummer_id of this.geselecteerde_nummers.keys()) {
      const tr = tbody.querySelector(`#nummer-${nummer_id}`);
      if (tr) {
        this.datatable.row(tr).select();
      }
    }

    // Zet events weer aan.
    this.datatable.on("select", this.select_handler.bind(this));
    this.datatable.on("deselect", this.deselect_handler.bind(this));
  }

  private nummer_selecteren(nummer_id: number, titel: string, artiest: string) {
    if (this.geselecteerde_nummers.has(nummer_id)) {
      return;
    }
    this.geselecteerde_nummers.set(
      nummer_id,
      new Nummer(nummer_id, titel, artiest),
    );
    this.geselecteerde_artiesten.add(artiest);

    if (this.geselecteerde_nummers.size > 0) {
      this.e_body.classList.add("heeft-nummers-geselecteerd");
    }
  }

  private nummer_deselecteren(nummer_id: number, artiest: string) {
    const nummer = this.geselecteerde_nummers.get(nummer_id);
    if (nummer == null) {
      return;
    }
    nummer.destroy();
    this.geselecteerde_nummers.delete(nummer_id);

    this.geselecteerde_artiesten.delete(artiest);

    if (this.geselecteerde_nummers.size == 0) {
      this.e_body.classList.remove("heeft-nummers-geselecteerd");
    }
  }
}

class Main {
  private readonly e_body: HTMLBodyElement;
  private readonly e_errormsg: HTMLDivElement;

  constructor() {
    this.e_body = gld.querySelectorTagName("body");
    this.e_errormsg = gld.getElementById<HTMLDivElement>("errormsg");
  }

  public async init(): Promise<void> {
    const lijst_id = functies.get_query_param_id("lijst");
    if (lijst_id == null) {
      throw new Error("Geen lijst ID opgegeven.");
    }
    const data = await api.post("get_stemlijst_frontend_data", {
      lijst: lijst_id,
    });
    const stemview = new StemView(lijst_id, data);
    stemview.on_error.on(this.error_handler.bind(this));
  }

  /**
   * Plaatst een foutmelding op de pagina.
   */
  public error_handler(e: unknown) {
    if (e instanceof Error) {
      this.e_errormsg.innerText = e.message;
    } else {
      this.e_errormsg.innerText = String(e);
    }
    this.e_body.classList.add("error");
  }
}

const keuze_rij_template: HTMLTableElement =
  functies.get_html_template_enkel(html_keuze_rij);

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    const m = new Main();
    m.init().catch(m.error_handler.bind(m));
  });
} else {
  const m = new Main();
  m.init().catch(m.error_handler.bind(m));
}
