// Libraries js
import * as gld from "gld-ts-lib/functions";

// Project js
import * as functies from "@muzieklijsten/functies";
import * as api from "@muzieklijsten/api";
import { TypedEvent } from "gld-ts-lib";

// HTML
import html_template from "./resultaten-stem.html";
import type { ResultatenNummer } from "./ResultatenNummer";
import type { Stem } from "@muzieklijsten/api/types";

/**
 * Stem per nummer
 */
class ResultatenStem {
  private readonly e_tr: HTMLTableRowElement;
  private readonly e_behandeld_input: HTMLInputElement;
  private readonly metadata_voor_filter: string[];
  public on_change_is_behandeld: TypedEvent<boolean>;
  public on_verwijderd: TypedEvent<void>;

  constructor(
    private readonly resultaten_nummer: ResultatenNummer,
    e_container: HTMLElement,
    private readonly stem: Stem,
  ) {
    this.on_change_is_behandeld = new TypedEvent();
    this.on_verwijderd = new TypedEvent();
    this.resultaten_nummer = resultaten_nummer;
    this.metadata_voor_filter = [];
    const timestamp = new Date(stem.timestamp);
    this.e_tr = gld
      .querySelectorTagName("tr", undefined, resultaten_stem_template)
      .cloneNode(true) as HTMLTableRowElement;
    this.e_tr.setAttribute("data-stemmer-id", String(this.stem.stemmer_id));
    this.e_behandeld_input = gld.querySelectorTagName(
      "input",
      undefined,
      this.e_tr,
    );
    this.e_behandeld_input.checked = this.stem.is_behandeld;
    this.update_behandeld();
    for (const e_timestamp of this.e_tr.getElementsByClassName(
      "stemmer-timestamp",
    )) {
      e_timestamp.appendChild(
        document.createTextNode(
          timestamp.toLocaleString("nl-NL", {
            dateStyle: "short",
            timeStyle: "short",
          }),
        ),
      );
    }
    for (const e_toelichting of this.e_tr.getElementsByClassName(
      "stem-toelichting",
    )) {
      e_toelichting.appendChild(document.createTextNode(this.stem.toelichting));
    }
    this.maak_velden();
    const e_cell0 = this.e_tr.firstChild;
    if (!(e_cell0 instanceof HTMLElement)) {
      throw new Error("First cell of the row is not an HTMLElement");
    }
    e_cell0.setAttribute("title", this.stem.ip);

    e_container.appendChild(this.e_tr);
  }

  private maak_velden() {
    const e_insert_before = this.e_tr.firstChild;
    for (const item of this.stem.velden) {
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

  public async behandeld_handler() {
    try {
      await api.post("stem_set_behandeld", {
        nummer: this.resultaten_nummer.nummer.id,
        lijst: this.resultaten_nummer.resultaten_modal.lijst_id,
        stemmer: this.stem.stemmer_id,
        waarde: this.e_behandeld_input.checked,
      });
      this.stem.is_behandeld = this.e_behandeld_input.checked;
      this.on_change_is_behandeld.emit(this.stem.is_behandeld);
      this.update_behandeld();
    } catch {
      this.e_behandeld_input.checked = !this.e_behandeld_input.checked;
    }
  }

  private update_behandeld() {
    if (this.stem.is_behandeld) {
      this.e_tr.classList.add("table-success");
    } else {
      this.e_tr.classList.remove("table-success");
    }
  }

  public async verwijderen() {
    await api.post("verwijder_stem", {
      nummer: this.resultaten_nummer.nummer.id,
      lijst: this.resultaten_nummer.resultaten_modal.lijst_id,
      stemmer: this.stem.stemmer_id,
    });
    this.e_tr.remove();
    this.on_verwijderd.emit();
  }
}

const resultaten_stem_template: HTMLTableElement =
  functies.get_html_template_enkel(html_template);

export { ResultatenStem };
