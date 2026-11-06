// Libraries js
import DataTable, * as datatables from "datatables.net-bs5";
import * as gld from "gld-ts-lib/functions";

// Project js
import * as functies from "@muzieklijsten/functies";
import * as api from "@muzieklijsten/api";
import { BeheerModal } from "./BeheerModal";
import { ResultatenModal } from "./ResultatenModal";

class Admin {
  /** @type {?number} */
  lijst_id;
  /** @type {Promise<string>} */
  lijst_naam_promise;
  /** @type {HTMLBodyElement} */
  e_body;
  /** @type {HTMLSelectElement} */
  e_lijst_select;
  /** @type {HTMLTableSectionElement} */
  e_geselecteerd_lijst;
  /** @type {HTMLSpanElement} */
  e_aantal_geselecteerde_nummers;
  /** @type {number[]} */
  geselecteerde_nummers;
  tabel;
  config_promise;

  constructor() {
    this.e_body = document.getElementsByTagName("body").item(0);
    this.e_lijst_select = document.getElementById("lijstselect");
    this.e_geselecteerd_lijst = document.getElementById("geselecteerd-lijst");
    this.e_aantal_geselecteerde_nummers = document.getElementById(
      "aantal-geselecteerde-nummers",
    );
    this.lijst_naam_promise = Promise.resolve("?");
    this.geselecteerde_nummers = [];
    this.config_promise = functies.set_config_classes();

    this.vul_metadata().then(() => {
      const params = new URLSearchParams(document.location.search);
      const lijst_id = params.get("lijst");
      for (const e_option of this.e_lijst_select.options) {
        if (e_option.value == lijst_id) {
          e_option.selected = true;
          this.set_lijst(lijst_id);
          break;
        }
      }
    });

    this.tabel = new DataTable(document.getElementById("beschikbare-nummers"), {
      processing: true,
      serverSide: true,
      ajax: async (data, callback, settings) => {
        data.is_vrijekeuze = 0;
        const respons = await api.post("vul_datatables", data);
        callback(respons);
      },
      columnDefs: [
        {
          targets: 0,
          visible: false,
          searchable: false,
          orderable: false,
        },
      ],
      order: [1, "asc"],
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
    document
      .getElementById("beheerknop")
      .addEventListener("click", this.beheer_knop_handler.bind(this));

    // Nieuwe lijst maken
    document
      .getElementById("nieuwknop")
      .addEventListener("click", this.nieuw_knop_handler.bind(this));

    // Resultaten dialoogvenster openen
    document
      .getElementById("resultatenknop")
      .addEventListener("click", this.resultaten_knop_handler.bind(this));
  }

  /**
   *
   * @param {number} lijst_id
   * @return {Promise<void>}
   */
  async set_lijst(lijst_id) {
    this.lijst_id = lijst_id;

    this.set_lijst_query_param(this.lijst_id);

    this.e_body.classList.add("lijst-geselecteerd");
    for (const bewerkknop of document.getElementsByClassName("bewerk-knop")) {
      bewerkknop.removeAttribute("title");
    }

    const lijst_data_promise = this.vul_lijst_metadata();
    this.lijst_naam_promise = lijst_data_promise.then((lijst_data) => {
      return lijst_data.naam;
    });
    await lijst_data_promise;

    this.tabel.draw();
  }

  async vul_metadata() {
    const data = await api.post("get_metadata", {});
    for (const e_organisatie of document.getElementsByClassName(
      "organisatie",
    )) {
      e_organisatie.innerText = data.organisatie;
    }
    for (const lijst of data.lijsten) {
      this.e_lijst_select.add(new Option(lijst.naam, lijst.id));
    }
    document.getElementById("totaal-aantal-nummers").innerText =
      data.totaal_aantal_nummers;
    for (const e_nimbus_url of document.getElementsByClassName("nimbus-url")) {
      e_nimbus_url.href = data.nimbus_url;
    }
  }

  /**
   *
   * @returns {Promise<unknown>}
   */
  async vul_lijst_metadata() {
    const data = await api.post("get_lijst_metadata", {
      lijst: this.lijst_id,
    });
    for (const e_naam of document.getElementsByClassName("lijst-naam")) {
      e_naam.innerText = data.naam;
    }
    document.getElementsByTagName("title").item(0).innerText =
      `Muzieklijsten beheer – ${data.naam}`;
    for (const e_iframe_url of document.getElementsByClassName("iframe-url")) {
      e_iframe_url.value = data.iframe_url;
    }
    for (const e_iframe_code of document.getElementsByClassName(
      "iframe-code",
    )) {
      e_iframe_code.value = `<iframe src="${data.iframe_url}" frameborder="0" height="3000" style="width: 100%; height: 3000px; border: none;">`;
    }
    this.geselecteerde_nummers = data.nummer_ids;
    // Vult de tabel met geselecteerde nummers.
    this.vul_lijst_geselecteerde_nummers();
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
   *
   * @param {Event} e
   * @param {ApiRow} dt
   * @param {string} type
   * @param {unknown} indexes
   */
  select_handler(e, dt, type, indexes) {
    for (const i of indexes) {
      const row = this.tabel.row(i);
      this.nummer_selecteren(row);
    }
  }

  /**
   * Datatables event handler voor wanneer er een rij is gedeselecteerd.
   * Wordt aangeroepen nadat de rij al visueel gedeselecteerd is.
   *
   * @param {Event} e
   * @param {DataTable} dt
   * @param {string} type
   * @param {unknown} indexes
   */
  deselect_handler(e, dt, type, indexes) {
    for (const i of indexes) {
      const row = this.tabel.row(i);
      this.nummer_deselecteren(row);
    }
  }

  /**
   * Selecteert een nummer en sla dit op op de server.
   *
   * @param {DataTable} row
   */
  async nummer_selecteren(row) {
    const [id_str, titel, artiest] = row.data();
    const nummer_id = Number.parseInt(id_str);

    if (this.geselecteerde_nummers.includes(nummer_id)) {
      // Nummer is al geselecteerd.
      // Functie is aangeroepen bij initialisatie van de tabel of als annulering van een deselect.
      return;
    }

    try {
      await this.nummer_toevoegen(nummer_id);
      this.geselecteerde_nummers.push(nummer_id);
    } catch (msg) {
      row.deselect();
      alert(msg);
    }
    await this.vul_lijst_geselecteerde_nummers();
  }

  /**
   * Deseleceert een nummer en verwijdert de koppeling van de server.
   *
   * @param {DataTable} row
   */
  async nummer_deselecteren(row) {
    const [id_str, titel, artiest] = row.data();
    const nummer_id = Number.parseInt(id_str);

    if (!this.geselecteerde_nummers.includes(nummer_id)) {
      // Nummer is niet geselecteerd.
      // Functie is aangeroepen als annulering van een select.
      return;
    }

    try {
      await this.nummer_verwijderen(nummer_id);
      const index = this.geselecteerde_nummers.indexOf(nummer_id);
      this.geselecteerde_nummers.splice(index, 1);
    } catch (msg) {
      row.select();
      alert(msg);
    }
    await this.vul_lijst_geselecteerde_nummers();
  }

  /**
   * Gebruiker kiest een lijst in de dropdown.
   *
   * @param {Event} e
   */
  lijst_select_handler(e) {
    const target = e.target;
    if (!(target instanceof HTMLSelectElement)) {
      return;
    }
    const lijst_id = Number.parseInt(target.value);
    if (!Number.isNaN(lijst_id) && lijst_id > 0) {
      this.set_lijst(lijst_id).catch(window.alert);
    }
  }

  /**
   * Selecteert de nummers in de tabel bij initialisatie.
   * Callback bij het renderen van elke rij.
   *
   * @param {HTMLTableRowElement} tr
   * @param {Array} row
   * @param {number} dataIndex
   * @param {Node[]} cells
   */
  toon_geselecteerd(tr, [nummer_id, titel, artiest, jaar], dataIndex, cells) {
    nummer_id = Number.parseInt(nummer_id);
    // If row ID is in the list of selected row IDs
    if (this.geselecteerde_nummers.includes(nummer_id)) {
      const row = this.tabel.row(dataIndex);
      row.select();
    }
  }

  /**
   * Voegt een nummer toe aan de lijst.
   * @returns {Promise<void>}
   */
  nummer_toevoegen(nummer_id) {
    return api.post("lijst_nummer_toevoegen", {
      lijst: this.lijst_id,
      nummer: nummer_id,
    });
  }

  /**
   * Verwijdert een nummer uit de lijst.
   * @returns {Promise<void>}
   */
  nummer_verwijderen(nummer_id) {
    return api.post("lijst_nummer_verwijderen", {
      lijst: this.lijst_id,
      nummer: nummer_id,
    });
  }

  /**
   * Vult de rechterkolom met alle geselecteerde nummers.
   * De hele inhoud van het element wordt vervangen.
   */
  async vul_lijst_geselecteerde_nummers() {
    const nummers = await api.post("get_geselecteerde_nummers", {
      lijst: this.lijst_id,
    });
    while (this.e_geselecteerd_lijst.lastChild) {
      this.e_geselecteerd_lijst.removeChild(
        this.e_geselecteerd_lijst.lastChild,
      );
    }
    this.e_aantal_geselecteerde_nummers.innerText = nummers.length;
    for (const nummer of nummers) {
      const e_tr = document.createElement("tr");
      this.e_geselecteerd_lijst.appendChild(e_tr);
      const e_titel = document.createElement("td");
      e_tr.appendChild(e_titel);
      e_titel.appendChild(document.createTextNode(nummer.titel));
      const e_artiest = document.createElement("td");
      e_tr.appendChild(e_artiest);
      e_artiest.appendChild(document.createTextNode(nummer.artiest));
      const e_jaar = document.createElement("td");
      e_tr.appendChild(e_jaar);
      e_jaar.appendChild(document.createTextNode(nummer.jaar ?? ""));
    }
  }

  /**
   *
   * @param {Event} e
   */
  async resultaten_knop_handler(e) {
    e.preventDefault();
    new ResultatenModal(this.lijst_id, await this.lijst_naam_promise);
  }

  nieuw_knop_handler(e) {
    e.preventDefault();
    const modal = new BeheerModal(undefined, this.config_promise);
    modal.on_lijst_gemaakt.on(this.lijst_toegevoegd.bind(this));
  }

  beheer_knop_handler(e) {
    e.preventDefault();
    const modal = new BeheerModal(this.lijst_id, this.config_promise);
    modal.on_lijst_veranderd.on(this.lijst_veranderd.bind(this));
    modal.on_lijst_verwijderd.on(this.lijst_verwijderd.bind(this));
  }

  /**
   *
   * @param {{id: number, naam: string}} data
   */
  lijst_toegevoegd(data) {
    this.e_lijst_select.add(new Option(data.naam, data.id, false, true));
    this.set_lijst(data.id);
  }

  /**
   *
   * @param {{id: number, naam: string}} data
   */
  lijst_veranderd(data) {
    const e_option = this.e_lijst_select.item(
      this.e_lijst_select.selectedIndex,
    );
    e_option.text = data.naam;
  }

  lijst_verwijderd() {
    this.e_lijst_select.remove(this.e_lijst_select.selectedIndex);
    this.unset_lijst();
  }

  unset_lijst() {
    this.lijst_id = undefined;

    this.e_lijst_select.value = "";

    this.set_lijst_query_param(null);

    this.e_body.classList.remove("lijst-geselecteerd");

    document.getElementsByTagName("title").item(0).innerText =
      "Muzieklijsten beheer";
    this.geselecteerde_nummers = [];
    // Vult de tabel met geselecteerde nummers.
    this.vul_lijst_geselecteerde_nummers();
  }

  /**
   *
   * @param {number|null} lijst_id
   */
  set_lijst_query_param(lijst_id) {
    const url = new URL(window.location.href);
    if (lijst_id !== null) {
      url.searchParams.set("lijst", String(lijst_id));
    } else {
      url.searchParams.delete("lijst");
    }
    window.history.replaceState({}, "", url);

    for (const nav_link of gld.querySelectorAllTagName("a", ".nav-link")) {
      const url = new URL(nav_link.href);
      if (lijst_id !== null) {
        url.searchParams.set("lijst", String(lijst_id));
      } else {
        url.searchParams.delete("lijst");
      }
      nav_link.href = url.toString();
    }
  }
}

export { Admin };
