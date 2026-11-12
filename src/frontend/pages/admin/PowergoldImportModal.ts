// Libraries js
import { Modal } from "bootstrap";
import * as gld from "gld-ts-lib/functions";
import { TypedEvent } from "gld-ts-lib";

// Project js
import * as api from "@muzieklijsten/api";
import * as functies from "@muzieklijsten/functies";
import { PowergoldImportResultModal } from "./PowergoldImportResultModal";

// css
import "./powergold-import-modal.scss";

// HTML
import html_template from "./powergold-import-modal.html";

class PowergoldImportModal {
  private readonly e_modal: HTMLDivElement;
  private readonly modal: Modal;
  private readonly e_form: HTMLFormElement;
  private readonly e_status: HTMLDivElement;
  private readonly e_submit_knop: HTMLButtonElement;
  private readonly e_icon_ready: HTMLElement;
  private readonly e_icon_loading: HTMLElement;
  /**
   * Event dat wordt geactiveerd wanneer de import is voltooid. Dit wordt geactiveerd voordat er nummers zijn
   * verwijderd.
   */
  public readonly on_import: TypedEvent<void>;
  /**
   * Event dat wordt geactiveerd nadat de nummers zijn verwijderd tijdens de import.
   */
  public readonly on_nummers_verwijderd: TypedEvent<void>;

  constructor() {
    this.e_modal = modal_template.cloneNode(true) as HTMLDivElement;
    this.e_form = gld.querySelectorTagName("form", undefined, this.e_modal);
    this.e_status = gld.querySelector(".powergold-import-status", this.e_modal);
    this.e_submit_knop = gld.querySelectorTagName(
      "button",
      "[type='submit']",
      this.e_modal,
    );
    this.e_icon_ready = gld.querySelector(
      ".powergold-icon-ready",
      this.e_modal,
    );
    this.e_icon_loading = gld.querySelector(
      ".powergold-icon-loading",
      this.e_modal,
    );
    this.on_import = new TypedEvent<void>();
    this.on_nummers_verwijderd = new TypedEvent<void>();

    this.e_form.addEventListener("submit", (event) => {
      this.submit_handler(event).catch(this.error_handler.bind(this));
    });

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
    this.set_status(
      "danger",
      e instanceof Error ? e.message : "Onbekende fout tijdens importeren.",
    );
    this.set_busy(false);
  }

  private set_busy(is_busy: boolean) {
    for (const elem of this.e_modal.querySelectorAll<
      HTMLInputElement | HTMLButtonElement
    >("input, button")) {
      elem.disabled = is_busy;
    }
    this.e_icon_ready.classList.toggle("d-none", is_busy);
    this.e_icon_loading.classList.toggle("d-none", !is_busy);
    this.e_submit_knop.textContent = is_busy ? "Importeren..." : "Importeren";
    this.e_submit_knop.prepend(
      is_busy ? this.e_icon_loading : this.e_icon_ready,
      document.createTextNode(" "),
    );
  }

  private set_status(
    type: "info" | "success" | "danger",
    message: string | null,
  ) {
    this.e_status.classList.remove(
      "d-none",
      "alert-info",
      "alert-success",
      "alert-danger",
    );
    if (message === null) {
      this.e_status.textContent = "";
      this.e_status.classList.add("d-none");
      return;
    }
    this.e_status.classList.add(`alert-${type}`);
    this.e_status.textContent = message;
  }

  private async submit_handler(event: Event) {
    event.preventDefault();
    if (this.e_submit_knop.disabled) {
      return;
    }

    const form_data = new FormData(this.e_form);
    this.set_status("info", "Importeren is bezig. Even geduld...");
    this.set_busy(true);
    try {
      const resultaat = await api.post("powergold_importeren", form_data);
      this.e_form.reset();
      this.set_busy(false);
      this.modal.hide();
      this.on_import.emit();
      const result_modal = new PowergoldImportResultModal(resultaat);
      result_modal.on_nummers_verwijderd.on(
        this.on_nummers_verwijderd.emit.bind(this.on_nummers_verwijderd),
      );
    } catch (error) {
      const msg = error instanceof Error ? error.message : String(error);
      this.set_status("danger", msg);
      this.set_busy(false);
    }
  }
}

const modal_template: HTMLElement =
  functies.get_html_template_enkel(html_template);

export { PowergoldImportModal };
