// Libraries js
import { Modal } from "bootstrap";
import * as gld from "gld-ts-lib/functions";
import { TypedEvent } from "gld-ts-lib";

// Project js
import * as api from "@muzieklijsten/api";
import * as functies from "@muzieklijsten/functies";
import type { AjaxMap } from "@muzieklijsten/api/types";

// css
import "./powergold-import-result-modal.scss";

// HTML
import html_template from "./powergold-import-result-modal.html";

class PowergoldImportResultModal {
  private readonly e_modal: HTMLDivElement;
  private readonly modal: Modal;
  private readonly e_aantal_verwijderd_regel;
  private readonly e_aantal_verwijderd;
  private readonly e_te_verwijderen;
  private readonly e_bevestig_verwijderen: HTMLButtonElement;
  private readonly e_verwijder_foutmelding;
  public readonly on_nummers_verwijderd: TypedEvent<void>;

  constructor(resultaat: AjaxMap["powergold_importeren"]["response"]) {
    this.e_modal = modal_template.cloneNode(true) as HTMLDivElement;
    gld.querySelector(".aantal-toegevoegd", this.e_modal).textContent = String(
      resultaat.toegevoegd,
    );
    gld.querySelector(".aantal-bijgewerkt", this.e_modal).textContent = String(
      resultaat.bijgewerkt,
    );
    this.e_aantal_verwijderd = gld.querySelector(
      ".aantal-verwijderd",
      this.e_modal,
    );
    this.e_aantal_verwijderd_regel = gld.querySelector(
      ".aantal-verwijderd-regel",
      this.e_modal,
    );
    this.e_te_verwijderen = gld.querySelector(".te-verwijderen", this.e_modal);
    this.e_bevestig_verwijderen = gld.querySelectorTagName(
      "button",
      ".bevestig-verwijderen",
      this.e_modal,
    );
    this.e_verwijder_foutmelding = gld.querySelector(
      ".verwijder-foutmelding",
      this.e_modal,
    );
    this.on_nummers_verwijderd = new TypedEvent<void>();

    if (resultaat.heeft_verwijder_bevestiging_nodig) {
      this.e_te_verwijderen.textContent = String(resultaat.te_verwijderen);
      this.set_state_verwijder_bevestiging(true);
      this.e_bevestig_verwijderen.addEventListener("click", () => {
        this.bevestig_verwijderen().catch(this.error_handler.bind(this));
      });
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
    this.modal.show();
  }

  private error_handler(e: unknown) {
    this.e_bevestig_verwijderen.disabled = false;
    this.e_verwijder_foutmelding.textContent =
      e instanceof Error ? e.message : "Onbekende fout tijdens verwijderen.";
    this.e_verwijder_foutmelding.classList.remove("d-none");
  }

  private async bevestig_verwijderen(): Promise<void> {
    if (this.e_bevestig_verwijderen.disabled) {
      return;
    }
    this.e_bevestig_verwijderen.disabled = true;
    this.e_verwijder_foutmelding.classList.add("d-none");
    const resultaat = await api.post(
      "powergold_import_verwijderen_bevestigen",
      {},
    );
    this.toon_aantal_verwijderd(resultaat.verwijderd);
    this.set_state_verwijder_bevestiging(false);
    this.on_nummers_verwijderd.emit();
  }

  private set_state_verwijder_bevestiging(visible: boolean): void {
    if (visible) {
      this.e_modal.classList.add("toon-verwijder-bevestiging");
    } else {
      this.e_modal.classList.remove("toon-verwijder-bevestiging");
    }
  }

  private toon_aantal_verwijderd(aantal: number): void {
    this.e_aantal_verwijderd.textContent = String(aantal);
    this.e_aantal_verwijderd_regel.classList.remove("d-none");
  }
}

const modal_template: HTMLElement =
  functies.get_html_template_enkel(html_template);

export { PowergoldImportResultModal };
