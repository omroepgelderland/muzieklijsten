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
  public readonly lijst_id: number;
  private readonly e_modal: HTMLDivElement;
  private readonly modal: Modal;
  private readonly e_totaal_aantal_stemmen: HTMLSpanElement;
  private readonly e_totaal_aantal_stemmers: HTMLSpanElement;
  private readonly e_filter_van: HTMLInputElement;
  private readonly e_filter_tot: HTMLInputElement;
  private readonly e_filters_form: HTMLFormElement;
  private readonly e_exportknop: HTMLButtonElement;
  private readonly e_resultaten_tabel: HTMLTableSectionElement;
  private readonly resultaten_nummers: Record<number, ResultatenNummer>;
  private totaal_aantal_stemmen: number;

  constructor(lijst_id: number, lijst_naam: string) {
    this.lijst_id = lijst_id;
    this.resultaten_nummers = {};
    this.totaal_aantal_stemmen = 0;

    this.e_modal = modal_template.cloneNode(true) as HTMLDivElement;
    this.e_totaal_aantal_stemmen = gld.querySelector(
      ".totaal-aantal-stemmen",
      this.e_modal,
    );
    this.e_totaal_aantal_stemmers = gld.querySelector(
      ".totaal-aantal-stemmers",
      this.e_modal,
    );
    this.e_filters_form = gld.querySelector("form.filters", this.e_modal);
    this.e_filters_form.addEventListener("submit", this.filter.bind(this));
    this.e_filters_form.addEventListener("reset", this.filter_reset.bind(this));
    this.e_filter_van = gld.querySelector<HTMLInputElement>(
      "#filter-van",
      this.e_modal,
    );
    this.e_filter_tot = gld.querySelector<HTMLInputElement>(
      "#filter-tot",
      this.e_modal,
    );
    this.e_exportknop = gld.querySelector("#export-resultaten", this.e_modal);
    this.e_resultaten_tabel = gld.querySelector(
      ".resultaten-tabel",
      this.e_modal,
    );
    this.e_resultaten_tabel.id = functies.get_random_string(12);

    this.set_totaal_aantal_stemmers().catch(this.error_handler.bind(this));
    this.maak_resultaten_tabel().catch(this.error_handler.bind(this));

    for (const e_lijst_naam of this.e_modal.querySelectorAll(".lijst-naam")) {
      e_lijst_naam.textContent = lijst_naam;
    }

    gld.querySelectorTagName("body").appendChild(this.e_modal);
    this.modal = new Modal(this.e_modal, {
      backdrop: true,
      focus: true,
      keyboard: true,
    });
    this.e_modal.addEventListener("hidden.bs.modal", () => {
      this.e_modal.remove();
    });

    this.e_modal.addEventListener("click", (event) => {
      this.click_handler(event).catch(this.error_handler.bind(this));
    });
    this.e_modal.addEventListener("change", (event) => {
      this.change_handler(event).catch(this.error_handler.bind(this));
    });

    this.modal.show();
  }

  private error_handler(e: unknown) {
    if (e instanceof Error) {
      console.error(e.message);
    } else {
      console.error(e);
    }
  }

  private async maak_resultaten_tabel() {
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
      resultaten_nummer.on_stem_verwijderd.on(() => {
        this.stem_verwijderd_handler().catch(this.error_handler.bind(this));
      });
      resultaten_nummer.on_verwijderd.on((aantal_stemmen) => {
        this.nummer_verwijderd_handler(aantal_stemmen).catch(
          this.error_handler.bind(this),
        );
      });
      this.resultaten_nummers[nummer_stemmen.nummer.id] = resultaten_nummer;
      this.add_totaal_aantal_stemmen(nummer_stemmen.stemmen.length);
    }
  }

  private add_totaal_aantal_stemmen(aantal: number) {
    this.totaal_aantal_stemmen += aantal;
    this.e_totaal_aantal_stemmen.innerText = String(this.totaal_aantal_stemmen);
  }

  private async set_totaal_aantal_stemmers() {
    // const van = this.get_van();
    // const tot = this.get_tot();
    const request = { lijst: this.lijst_id };
    // if ( van != null ) {
    //   request.van = van.toISOString();
    // }
    // if ( tot != null ) {
    //   request.tot = tot.toISOString();
    // }
    this.e_totaal_aantal_stemmers.innerText = String(
      await api.post("get_totaal_aantal_stemmers", request),
    );
  }

  private async stem_verwijderd_handler() {
    this.add_totaal_aantal_stemmen(-1);
    await this.set_totaal_aantal_stemmers();
  }

  private async nummer_verwijderd_handler(aantal_stemmen: number) {
    this.add_totaal_aantal_stemmen(-aantal_stemmen);
    await this.set_totaal_aantal_stemmers();
  }

  private filter(event: Event) {
    event.preventDefault();
    let nummers_tekst = this.e_filter_van.value.toLowerCase();
    let stemmers_tekst = this.e_filter_tot.value.toLowerCase();
    if (nummers_tekst.length < 3) {
      nummers_tekst = "";
      this.e_filter_van.value = "";
    }
    if (stemmers_tekst.length < 3) {
      stemmers_tekst = "";
      this.e_filter_tot.value = "";
    }
    for (const [_nummer_id, resultaten_nummer] of Object.entries(
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

  private filter_reset(event: Event) {
    this.e_filter_van.value = "";
    this.e_filter_tot.value = "";
    this.filter(event);
  }

  /**
   * Handler voor alle kliks in het modal.
   */
  private async click_handler(event: Event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    if (target.closest("#export-resultaten")) {
      await this.exporteer_resultaten_handler();
      return;
    }
    const stem_verwijderen = target.closest(".stem-verwijderen");
    if (stem_verwijderen instanceof HTMLInputElement) {
      await this.stem_verwijderen_handler(stem_verwijderen);
    }
    // In- en uitklappen van stemmen op een nummer.

    const nummer = target.closest(".nummer");
    if (nummer instanceof HTMLElement) {
      await this.toggle_nummer_stemmen_handler(nummer);
    }
  }

  /**
   * Handler voor alle change events in het modal.
   */
  private async change_handler(event: Event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const behandeld_check = target.closest(".behandeld-check");
    if (behandeld_check instanceof HTMLInputElement) {
      await this.check_behandeld_handler(behandeld_check);
    }
  }

  /**
   * In- en uitklappen van de stemmen op een nummer.
   */
  private async toggle_nummer_stemmen_handler(elem: HTMLElement) {
    const nummer_id = Number.parseInt(
      elem.getAttribute("data-nummer-id") ?? "",
    );
    const resultaten_nummer = this.resultaten_nummers[nummer_id];
    await resultaten_nummer.toggle();
  }

  /**
   * Handler voor een klik op het kruisje om een stem te verwijderen.
   *
   * @param elem - Knop waarop geklikt is.
   */
  private async stem_verwijderen_handler(elem: HTMLInputElement) {
    const stemmer_id = Number.parseInt(
      elem.closest("[data-stemmer-id]")?.getAttribute("data-stemmer-id") ?? "",
    );
    const nummer_id = Number.parseInt(
      elem.closest("[data-nummer-id]")?.getAttribute("data-nummer-id") ?? "",
    );
    const resultaten_nummer: ResultatenNummer =
      this.resultaten_nummers[nummer_id];
    const resultaten_stem = resultaten_nummer.get_stem(stemmer_id);
    if (resultaten_stem == null) {
      throw new Error("Geen stem");
    }
    await resultaten_stem.verwijderen();
  }

  /**
   * Change event op een vinkhokje is_behandeld.
   *
   * @param elem - Vinkhokje dat veranderd is.
   */
  private async check_behandeld_handler(elem: HTMLInputElement) {
    const stemmer_id = Number.parseInt(
      elem.closest("[data-stemmer-id]")?.getAttribute("data-stemmer-id") ?? "",
    );
    const nummer_id = Number.parseInt(
      elem.closest("[data-nummer-id]")?.getAttribute("data-nummer-id") ?? "",
    );
    const resultaten_nummer: ResultatenNummer =
      this.resultaten_nummers[nummer_id];
    const resultaten_stem = resultaten_nummer.get_stem(stemmer_id);
    if (resultaten_stem == null) {
      throw new Error("Geen stem");
    }
    await resultaten_stem.behandeld_handler();
  }

  private async exporteer_resultaten_handler() {
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

  private get_van(): Date | null {
    const van = new Date(this.e_filter_van.value);
    if (isNaN(van.getTime())) {
      return null;
    } else {
      van.setHours(0);
      van.setMinutes(0);
      van.setSeconds(0);
      van.setMilliseconds(0);
      return van;
    }
  }

  private get_tot(): Date | null {
    const tot = new Date(this.e_filter_tot.value);
    if (isNaN(tot.getTime())) {
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

const modal_template: HTMLElement =
  functies.get_html_template_enkel(html_template);

export { ResultatenModal };
