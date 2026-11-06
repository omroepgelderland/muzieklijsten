// Libraries js
import * as gld from "gld-ts-lib/functions";

// Project js
import * as functies from "@muzieklijsten/functies";
// import * as api from "@muzieklijsten/api";
import { TypedEvent } from "gld-ts-lib";
import type { ResultatenModal } from "./ResultatenModal";
import { ResultatenStem } from "./ResultatenStem";

// HTML
import html_template from "./resultaten-nummer.html";
import type { Nummer, Stem } from "@muzieklijsten/api/types";

/**
 * Een nummer in de lijst met resultaten met uitklapbare lijst met stemmen op dat nummer.
 */
class ResultatenNummer {
  public readonly resultaten_modal: ResultatenModal;
  private readonly e_container: HTMLTableSectionElement;
  private readonly resultaten_stemmen: Map<number, ResultatenStem>;
  private readonly e_tr_uitklap: HTMLTableRowElement;
  private e_tr_gegevens: HTMLTableRowElement | null = null;
  private readonly e_aantal_stemmen: HTMLTableCellElement;
  public nummer: Nummer;
  public readonly on_stem_verwijderd: TypedEvent<void>;
  public readonly on_verwijderd: TypedEvent<number>;
  private is_zichtbaar: boolean;
  private readonly labels_promise: Promise<string[]>;
  private readonly stemmen: Stem[];
  private stemmen_gefilterd: Stem[];

  constructor(
    resultaten_modal: ResultatenModal,
    e_container: HTMLTableSectionElement,
    labels_promise: Promise<string[]>,
    stemmen: Stem[],
    nummer: Nummer,
  ) {
    this.resultaten_stemmen = new Map();
    this.on_stem_verwijderd = new TypedEvent();
    this.on_verwijderd = new TypedEvent();
    this.resultaten_modal = resultaten_modal;
    this.e_container = e_container;
    this.is_zichtbaar = true;
    this.labels_promise = labels_promise;
    this.stemmen = stemmen;
    this.stemmen_gefilterd = [...stemmen];

    this.e_tr_uitklap = gld
      .querySelectorTagName("tr", ".nummer", resultaten_nummer_template)
      .cloneNode(true) as HTMLTableRowElement;
    this.e_aantal_stemmen = gld.querySelector<HTMLTableCellElement>(
      ".aantal-stemmen",
      this.e_tr_uitklap,
    );

    // this.e_tr_uitklap.getElementsByClassName('verwijder-nummer').item(0).addEventListener('click', this.verwijderen.bind(this));

    this.e_container.appendChild(this.e_tr_uitklap);
    this.nummer = nummer;
    this.set_nummer(nummer);
    if (this.stemmen.length > 0) {
      this.e_tr_uitklap.classList.add("heeft-stemmen");
    }
    this.update_aantal_stemmen();
    this.update_behandeld();
  }

  private set_nummer(nummer: Nummer) {
    this.nummer = nummer;

    this.e_tr_uitklap.id = `nummer-${this.nummer.id}`;
    this.e_tr_uitklap.setAttribute("data-nummer-id", String(this.nummer.id));

    for (const e_nummer_titel of this.e_tr_uitklap.getElementsByClassName(
      "nummer-titel",
    )) {
      e_nummer_titel.appendChild(document.createTextNode(this.nummer.titel));
    }
    for (const e_nummer_artiest of this.e_tr_uitklap.getElementsByClassName(
      "nummer-artiest",
    )) {
      e_nummer_artiest.appendChild(
        document.createTextNode(this.nummer.artiest),
      );
    }
    for (const e_nummer_duur of this.e_tr_uitklap.getElementsByClassName(
      "nummer-duur",
    )) {
      e_nummer_duur.appendChild(
        document.createTextNode(functies.format_duur(this.nummer.duur)),
      );
    }
    if (this.nummer.is_vrijekeuze) {
      this.e_tr_uitklap.classList.add("vrijekeuze");
    }
  }

  /**
   * Maak de kolomtitels van de invulvelden van de stemmen (naam, e-mail e.d.).
   */
  private async maak_velden_labels() {
    if (this.e_tr_gegevens == null) {
      throw new Error("Nummer is niet uitgeklapt");
    }
    const thead = gld.querySelectorTagName(
      "thead",
      undefined,
      this.e_tr_gegevens,
    );
    const e_container = gld.querySelectorTagName("tr", undefined, thead);
    const e_insert_before = gld.assert_notnil(e_container?.firstChild);
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
  private maak_stemmen() {
    if (this.e_tr_gegevens == null) {
      throw new Error("Nummer is niet uitgeklapt");
    }
    const e_tbody = gld.querySelectorTagName(
      "tbody",
      undefined,
      this.e_tr_gegevens,
    );
    for (const stem of this.stemmen_gefilterd) {
      const resultaten_stem = new ResultatenStem(this, e_tbody, stem);
      resultaten_stem.on_change_is_behandeld.on(
        this.behandeld_change_handler.bind(this, stem.stemmer_id),
      );
      resultaten_stem.on_verwijderd.on(
        this.stem_verwijderd_handler.bind(this, stem.stemmer_id),
      );
      this.resultaten_stemmen.set(stem.stemmer_id, resultaten_stem);
    }
  }

  /**
   * Zet een stem op behandeld of niet na een klik op het vinkhokje.
   */
  private behandeld_change_handler(stemmer_id: number, is_behandeld: boolean) {
    const i = this.stemmen.findIndex((stem) => stem.stemmer_id === stemmer_id);
    this.stemmen[i].is_behandeld = is_behandeld;
    this.update_behandeld();
  }

  /**
   * Maakt de regel groen als alle stemmen behandeld zijn (ook de stemmen buiten het filter)
   */
  private update_behandeld() {
    if (this.stemmen.every((stem) => stem.is_behandeld)) {
      this.e_tr_uitklap.classList.add("table-success");
    } else {
      this.e_tr_uitklap.classList.remove("table-success");
    }
  }

  /**
   * Verwijdert een stem en eventueel de hele regel als er geen stemmen meer zijn.
   */
  private stem_verwijderd_handler(stemmer_id: number) {
    const i = this.stemmen.findIndex((stem) => stem.stemmer_id === stemmer_id);
    if (i === -1) {
      throw new Error("stem niet gevonden");
    }
    this.stemmen.splice(i, 1);
    this.resultaten_stemmen.delete(stemmer_id);
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
  private update_aantal_stemmen() {
    this.e_aantal_stemmen.innerText = String(this.stemmen.length);
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
   */
  public filter(
    nummers_tekst: string,
    stemmers_tekst: string,
    van: Date | null,
    tot: Date | null,
  ) {
    this.inklappen();
    // Filter het nummer.
    this.is_zichtbaar =
      nummers_tekst == "" ||
      this.nummer.titel.toLowerCase().includes(nummers_tekst) ||
      this.nummer.artiest.toLowerCase().includes(nummers_tekst);

    if (this.is_zichtbaar) {
      // Filter de stemmen.
      this.filter_stemmen({
        tekst: stemmers_tekst,
        van: van,
        tot: tot,
      });
      this.is_zichtbaar =
        this.is_zichtbaar && this.stemmen_gefilterd.length > 0;
    }

    if (this.is_zichtbaar) {
      this.e_tr_uitklap.hidden = false;
    } else {
      this.e_tr_uitklap.hidden = true;
    }
  }

  /**
   * Past een filter toe. De gefilterde stemmen worden in stemmen_gefilterd opgeslagen.
   */
  private filter_stemmen(filter: {
    tekst: string;
    van: Date | null;
    tot: Date | null;
  }) {
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

  private is_uitgeklapt() {
    return this.e_tr_gegevens != null;
  }

  /**
   * Verwijdert de tabel met stemmers op dit nummer.
   */
  private inklappen() {
    if (this.e_tr_gegevens != null) {
      this.e_tr_gegevens.remove();
      this.e_tr_gegevens = null;
      this.e_tr_uitklap.classList.add("collapsed");
    }
  }

  /**
   * Genereert de HTML van de tabel met stemmers op dit nummer en plaatst dit op de pagina.
   */
  private async uitklappen() {
    if (this.e_tr_gegevens == null) {
      this.e_tr_uitklap.classList.remove("collapsed");
      this.e_tr_gegevens = gld
        .querySelectorTagName("tr", ".stemmers", resultaten_nummer_template)
        .cloneNode(true) as HTMLTableRowElement;
      this.e_tr_uitklap.after(this.e_tr_gegevens);
      this.e_tr_gegevens.id = `nummer-stemmers-${this.nummer.id}`;
      this.e_tr_gegevens.setAttribute("data-nummer-id", String(this.nummer.id));
      await this.maak_velden_labels();
      this.maak_stemmen();
    }
  }

  /**
   * Toont de tabel met stemmen als die nu niet zichtbaar is en andersom.
   */
  public async toggle() {
    if (this.is_uitgeklapt()) {
      this.inklappen();
    } else {
      await this.uitklappen();
    }
  }

  public get_stem(stemmer_id: number): ResultatenStem | undefined {
    return this.resultaten_stemmen.get(stemmer_id);
  }
}

const resultaten_nummer_template: HTMLTableElement =
  functies.get_html_template_enkel(html_template);

export { ResultatenNummer };
