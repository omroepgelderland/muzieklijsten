// Libraries js
import DataTable from "datatables.net-bs5";
import * as gld from "gld-ts-lib/functions";
import type {
  AjaxData as DTAjaxData,
  Api,
  ApiRowMethods,
  Context,
} from "datatables.net-bs5";
import type { DataTableRow } from "@muzieklijsten/types";

// Project js
import * as api from "@muzieklijsten/api";
import * as functies from "@muzieklijsten/functies";
import type { AjaxMap } from "@muzieklijsten/api/types";

import { BeheerModal } from "./BeheerModal";
import { PowergoldImportModal } from "./PowergoldImportModal";
import { ResultatenModal } from "./ResultatenModal";

interface LijstData {
  id: number;
  naam: string;
}

type LijstMetadata = AjaxMap["get_lijst_metadata"]["response"];

class Admin {
  private lijst_id: number | null;
  private lijst_data_promise?: Promise<LijstMetadata>;
  private readonly e_body: HTMLBodyElement;
  private readonly e_lijst_select: HTMLSelectElement;
  private readonly e_geselecteerd_lijst: HTMLTableSectionElement;
  private readonly e_aantal_geselecteerde_nummers: HTMLSpanElement;
  private readonly e_beschikbare_nummers: HTMLTableElement;
  private readonly geselecteerde_nummers;
  private readonly tabel;
  private readonly config_promise: Promise<AjaxMap["get_config"]["response"]>;

  constructor() {
    this.e_body = gld.querySelectorTagName("body");
    this.e_lijst_select = gld.querySelectorTagName("select", "#lijstselect");
    this.e_geselecteerd_lijst = gld.querySelectorTagName(
      "tbody",
      "#geselecteerd-lijst",
    );
    this.e_aantal_geselecteerde_nummers = gld.querySelectorTagName(
      "span",
      "#aantal-geselecteerde-nummers",
    );
    this.e_beschikbare_nummers = gld.querySelectorTagName(
      "table",
      "#beschikbare-nummers",
    );
    this.lijst_id = null;
    this.geselecteerde_nummers = new Set<number>();
    this.config_promise = functies.set_config_classes();

    this.vul_metadata().catch(this.error_handler.bind(this));

    this.tabel = new DataTable<DataTableRow>(this.e_beschikbare_nummers, {
      processing: true,
      serverSide: true,
      ajax: (data, callback, _settings) => {
        this.datatables_ajax_handler(data, callback, _settings).catch(
          this.error_handler.bind(this),
        );
      },
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
      createdRow: this.toon_geselecteerd.bind(this),
      language: {
        lengthMenu: "_MENU_ nummers per pagina",
        zeroRecords: "Geen nummers gevonden",
        info: "Pagina _PAGE_ van _PAGES_",
        infoEmpty: "Geen nummers gevonden",
        infoFiltered: "(gefilterd van _MAX_ totaal)",
        search: "Zoeken:",
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
      },
    });
    this.tabel.on("select", this.select_handler.bind(this));
    this.tabel.on("deselect", this.deselect_handler.bind(this));

    this.e_lijst_select.addEventListener(
      "change",
      this.lijst_select_handler.bind(this),
    );

    // Bestaande lijst wijzigen
    gld
      .querySelectorTagName("a", "#beheerknop")
      .addEventListener("click", this.beheer_knop_handler.bind(this));

    // Nieuwe lijst maken
    gld
      .querySelectorTagName("a", "#nieuwknop")
      .addEventListener("click", this.nieuw_knop_handler.bind(this));

    // Resultaten dialoogvenster openen
    gld
      .querySelectorTagName("a", "#resultatenknop")
      .addEventListener("click", (event) => {
        this.resultaten_knop_handler(event).catch(
          this.error_handler.bind(this),
        );
      });

    gld
      .querySelectorTagName("a", "#powergold-importknop")
      .addEventListener("click", (event) => {
        event.preventDefault();
        const p = new PowergoldImportModal();
        const reset_lijst = () => {
          // Reset huidige lijst en beschikbare nummers
          if (this.lijst_id != null) {
            this.set_lijst(this.lijst_id);
          }
        };
        p.on_import.once(reset_lijst.bind(this));
        p.on_nummers_verwijderd.once(reset_lijst.bind(this));
      });
  }

  private async datatables_ajax_handler(
    data: DTAjaxData,
    callback: (data: unknown) => void,
    _settings: Context,
  ): Promise<void> {
    if (this.lijst_id == null) {
      return;
    }
    const request_data = {
      ...data,
      random_volgorde: false,
      is_vrijekeuze: false,
    };
    const respons = await api.post("vul_datatables", request_data);
    callback(respons);
  }

  private error_handler(e: unknown) {
    if (e instanceof Error) {
      console.error(e.message);
    } else {
      console.error(e);
    }
  }

  private set_lijst(lijst_id: number): void {
    this.lijst_id = lijst_id;

    functies.set_lijst_query_param(this.lijst_id);

    this.e_body.classList.add("lijst-geselecteerd");
    for (const bewerkknop of document.querySelectorAll(".bewerk-knop")) {
      bewerkknop.removeAttribute("title");
    }

    this.lijst_data_promise = this.vul_lijst_metadata();

    this.tabel.draw();
  }

  private async vul_metadata(): Promise<void> {
    const query_param_lijst_id = functies.get_query_param_id("lijst");
    const data = await api.post("get_metadata", {});
    for (const e_organisatie of document.querySelectorAll<HTMLElement>(
      ".organisatie",
    )) {
      e_organisatie.textContent = data.organisatie;
    }
    for (const lijst of data.lijsten) {
      const option = new Option(lijst.naam, String(lijst.id));
      if (query_param_lijst_id === lijst.id) {
        option.selected = true;
        this.set_lijst(lijst.id);
      }
      this.e_lijst_select.add(option);
    }
    const e_totaal_aantal_nummers = gld.querySelectorTagName(
      "span",
      "#totaal-aantal-nummers",
    );
    e_totaal_aantal_nummers.textContent = String(data.totaal_aantal_nummers);
    for (const e_nimbus_url of document.querySelectorAll<HTMLAnchorElement>(
      ".nimbus-url",
    )) {
      e_nimbus_url.href = data.nimbus_url;
    }
  }

  private async vul_lijst_metadata(): Promise<LijstMetadata> {
    const data = await api.post("get_lijst_metadata", {
      lijst: this.lijst_id ?? 0,
    });
    for (const e_naam of document.querySelectorAll<HTMLElement>(
      ".lijst-naam",
    )) {
      e_naam.textContent = data.naam;
    }
    gld.querySelectorTagName("title").textContent =
      `Muzieklijsten beheer – ${data.naam}`;
    for (const e_iframe_url of document.querySelectorAll<HTMLInputElement>(
      ".iframe-url",
    )) {
      e_iframe_url.value = data.iframe_url;
    }
    for (const e_iframe_code of document.querySelectorAll<HTMLInputElement>(
      ".iframe-code",
    )) {
      e_iframe_code.value = `<iframe src="${data.iframe_url}" frameborder="0" height="3000" style="width: 100%; height: 3000px; border: none;">`;
    }
    this.geselecteerde_nummers.clear();
    for (const id of data.nummer_ids) {
      this.geselecteerde_nummers.add(id);
    }
    // Vult de tabel met geselecteerde nummers.
    await this.vul_lijst_geselecteerde_nummers();
    const e_form_controls = document.querySelectorAll(
      "#beschikbare-nummers-length select, #beschikbare-nummers_filter input",
    );
    for (const elem of e_form_controls) {
      elem.classList.add("form-control");
    }
    return data;
  }

  /**
   * Datatables event handler voor wanneer er een rij is geselecteerd.
   * Wordt aangeroepen nadat de rij al visueel geselecteerd is.
   */
  private select_handler(
    event: Event,
    dt: Api<DataTableRow>,
    type: string,
    indexes: number[],
  ): void {
    for (const index of indexes) {
      const row = this.tabel.row(index);
      this.nummer_selecteren(row).catch(this.error_handler.bind(this));
    }
  }

  /**
   * Datatables event handler voor wanneer er een rij is gedeselecteerd.
   * Wordt aangeroepen nadat de rij al visueel gedeselecteerd is.
   */
  private deselect_handler(
    event: Event,
    dt: Api<DataTableRow>,
    type: string,
    indexes: number[],
  ): void {
    for (const index of indexes) {
      const row = this.tabel.row(index);
      this.nummer_deselecteren(row).catch(this.error_handler.bind(this));
    }
  }

  private async nummer_selecteren(
    row: ApiRowMethods<DataTableRow>,
  ): Promise<void> {
    const { DT_RowId: id_waarde } = row.data();
    const nummer_id = Number.parseInt(String(id_waarde), 10);

    if (this.geselecteerde_nummers.has(nummer_id)) {
      // Nummer is al geselecteerd.
      // Functie is aangeroepen bij initialisatie van de tabel of als annulering van een deselect.
      return;
    }

    try {
      await this.nummer_toevoegen(nummer_id);
      this.geselecteerde_nummers.add(nummer_id);
    } catch (msg) {
      row.deselect();
      alert(String(msg));
    }
    await this.vul_lijst_geselecteerde_nummers();
  }

  /**
   * Deseleceert een nummer en verwijdert de koppeling van de server.
   */
  private async nummer_deselecteren(
    row: ApiRowMethods<DataTableRow>,
  ): Promise<void> {
    const { DT_RowId: id_waarde } = row.data();
    const nummer_id = Number.parseInt(String(id_waarde), 10);

    if (!this.geselecteerde_nummers.has(nummer_id)) {
      // Nummer is niet geselecteerd.
      // Functie is aangeroepen als annulering van een select.
      return;
    }
    try {
      await this.nummer_verwijderen(nummer_id);
      this.geselecteerde_nummers.delete(nummer_id);
    } catch (msg) {
      row.select();
      alert(String(msg));
    }
    await this.vul_lijst_geselecteerde_nummers();
  }

  /**
   * Gebruiker kiest een lijst in de dropdown.
   */
  private lijst_select_handler(event: Event): void {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement)) {
      return;
    }
    const lijst_id = Number.parseInt(target.value, 10);
    if (!Number.isNaN(lijst_id) && lijst_id > 0) {
      this.set_lijst(lijst_id);
    }
  }

  /**
   * Selecteert de nummers in de tabel bij initialisatie.
   * Callback bij het renderen van elke rij.
   */
  private toon_geselecteerd(
    _tr: HTMLTableRowElement,
    raw_data: unknown,
    dataIndex: number,
    _cells: HTMLTableCellElement[],
  ): void {
    const { DT_RowId: nummer_id } = functies.validate_datatable_row(raw_data);
    if (this.geselecteerde_nummers.has(nummer_id)) {
      const row_data = this.tabel.row(dataIndex);
      row_data.select();
    }
  }

  /**
   * Voegt een nummer toe aan de lijst.
   */
  private nummer_toevoegen(nummer_id: number): Promise<void> {
    return api.post("lijst_nummer_toevoegen", {
      lijst: this.lijst_id ?? 0,
      nummer: nummer_id,
    });
  }

  /**
   * Verwijdert een nummer uit de lijst.
   */
  private nummer_verwijderen(nummer_id: number): Promise<void> {
    return api.post("lijst_nummer_verwijderen", {
      lijst: this.lijst_id ?? 0,
      nummer: nummer_id,
    });
  }

  /**
   * Vult de rechterkolom met alle geselecteerde nummers.
   * De hele inhoud van het element wordt vervangen.
   */
  private async vul_lijst_geselecteerde_nummers(): Promise<void> {
    const nummers = await api.post("get_geselecteerde_nummers", {
      lijst: this.lijst_id ?? 0,
    });
    while (this.e_geselecteerd_lijst.lastChild != null) {
      this.e_geselecteerd_lijst.removeChild(
        this.e_geselecteerd_lijst.lastChild,
      );
    }
    this.e_aantal_geselecteerde_nummers.textContent = String(nummers.length);
    for (const nummer of nummers) {
      const e_tr = document.createElement("tr");
      this.e_geselecteerd_lijst.appendChild(e_tr);

      const e_titel = document.createElement("td");
      e_titel.textContent = nummer.titel;
      e_tr.appendChild(e_titel);

      const e_artiest = document.createElement("td");
      e_artiest.textContent = nummer.artiest;
      e_tr.appendChild(e_artiest);

      const e_jaar = document.createElement("td");
      e_jaar.textContent = nummer.jaar === null ? "" : String(nummer.jaar);
      e_tr.appendChild(e_jaar);
    }
  }

  private async resultaten_knop_handler(event: Event): Promise<void> {
    event.preventDefault();
    if (this.lijst_id == null || this.lijst_data_promise == null) {
      return;
    }
    new ResultatenModal(this.lijst_id, (await this.lijst_data_promise).naam);
  }

  private nieuw_knop_handler(event: Event): void {
    event.preventDefault();
    const modal = new BeheerModal(null, this.config_promise);
    modal.on_lijst_gemaakt.on((data) => {
      this.lijst_toegevoegd(data);
    });
  }

  private beheer_knop_handler(event: Event): void {
    event.preventDefault();
    const modal = new BeheerModal(this.lijst_id, this.config_promise);
    modal.on_lijst_veranderd.on(this.lijst_veranderd.bind(this));
    modal.on_lijst_verwijderd.on(() => {
      this.lijst_verwijderd().catch(this.error_handler.bind(this));
    });
  }

  private lijst_toegevoegd(data: LijstData): void {
    this.e_lijst_select.add(
      new Option(data.naam, String(data.id), false, true),
    );
    this.set_lijst(data.id);
  }

  private lijst_veranderd(data: LijstData): void {
    const e_option = this.e_lijst_select.item(
      this.e_lijst_select.selectedIndex,
    );
    if (e_option == null) {
      return;
    }
    e_option.text = data.naam;
  }

  private async lijst_verwijderd(): Promise<void> {
    this.e_lijst_select.remove(this.e_lijst_select.selectedIndex);
    await this.unset_lijst();
  }

  private async unset_lijst(): Promise<void> {
    this.lijst_id = null;

    this.e_lijst_select.value = "";

    functies.set_lijst_query_param(null);

    this.e_body.classList.remove("lijst-geselecteerd");

    gld.querySelectorTagName("title").textContent = "Muzieklijsten beheer";
    this.geselecteerde_nummers.clear();
    await this.vul_lijst_geselecteerde_nummers();
  }
}

export { Admin };
