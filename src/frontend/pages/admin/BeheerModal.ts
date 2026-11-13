// Libraries js
import "bootstrap";
import { Modal } from "bootstrap";
import "datatables.net-select-bs5";
import * as gld from "gld-ts-lib/functions";
import { TypedEvent } from "gld-ts-lib";

// Project js
import * as functies from "@muzieklijsten/functies";
import * as api from "@muzieklijsten/api";
import type { AjaxMap } from "@muzieklijsten/api/types";

// css
import "./beheer-modal.scss";

// HTML
import html_beheer_modal from "./beheer-modal.html";

class BeheerModal {
  private readonly lijst_id: number | null;
  private readonly e_modal: HTMLDivElement;
  private readonly modal: Modal;
  private readonly e_form: HTMLFormElement;
  private readonly e_form_lijst;
  private readonly e_form_naam;
  private readonly e_form_email;
  private readonly e_form_bedankt_tekst;
  private readonly e_form_starttijd;
  private readonly e_form_eindtijd;
  private readonly e_form_minkeuzes;
  private readonly e_form_maxkeuzes;
  private readonly e_form_vrijekeuze;
  private readonly e_form_stemmen_per_ip;
  private readonly e_form_artiest_eenmalig;
  private readonly e_form_recaptcha;
  private readonly e_form_mail_stemmers;
  private readonly e_form_random_volgorde;
  private readonly e_velden_zichtbaar_kolom;
  private readonly e_velden_verplicht_kolom;
  public readonly on_lijst_gemaakt: TypedEvent<{ id: number; naam: string }>;
  public readonly on_lijst_veranderd: TypedEvent<{ id: number; naam: string }>;
  public readonly on_lijst_verwijderd: TypedEvent<{ id: number }>;

  constructor(
    lijst_id: number | null,
    config_promise: Promise<{
      heeft_openai_key: boolean;
      heeft_recaptcha_key: boolean;
    }>,
  ) {
    this.on_lijst_gemaakt = new TypedEvent();
    this.on_lijst_veranderd = new TypedEvent();
    this.on_lijst_verwijderd = new TypedEvent();
    this.lijst_id = lijst_id;
    this.e_modal = beheer_modal_template.cloneNode(true) as HTMLDivElement;
    this.e_form = gld.querySelectorTagName("form", undefined, this.e_modal);
    this.e_form_lijst = this.e_form.elements.namedItem(
      "lijst",
    ) as HTMLInputElement;
    this.e_form_naam = this.e_form.elements.namedItem(
      "naam",
    ) as HTMLInputElement;
    this.e_form_email = this.e_form.elements.namedItem(
      "email",
    ) as HTMLInputElement;
    this.e_form_bedankt_tekst = this.e_form.elements.namedItem(
      "bedankt-tekst",
    ) as HTMLInputElement;
    this.e_form_starttijd = this.e_form.elements.namedItem(
      "starttijd",
    ) as HTMLInputElement;
    this.e_form_eindtijd = this.e_form.elements.namedItem(
      "eindtijd",
    ) as HTMLInputElement;
    this.e_form_minkeuzes = this.e_form.elements.namedItem(
      "minkeuzes",
    ) as HTMLInputElement;
    this.e_form_maxkeuzes = this.e_form.elements.namedItem(
      "maxkeuzes",
    ) as HTMLInputElement;
    this.e_form_vrijekeuze = this.e_form.elements.namedItem(
      "vrijekeuzes",
    ) as HTMLInputElement;
    this.e_form_stemmen_per_ip = this.e_form.elements.namedItem(
      "stemmen-per-ip",
    ) as HTMLInputElement;
    this.e_form_artiest_eenmalig = this.e_form.elements.namedItem(
      "artiest-eenmalig",
    ) as HTMLInputElement;
    this.e_form_recaptcha = this.e_form.elements.namedItem(
      "recaptcha",
    ) as HTMLInputElement;
    this.e_form_mail_stemmers = this.e_form.elements.namedItem(
      "mail-stemmers",
    ) as HTMLInputElement;
    this.e_form_random_volgorde = this.e_form.elements.namedItem(
      "random-volgorde",
    ) as HTMLInputElement;
    this.e_velden_zichtbaar_kolom = gld.querySelector(
      ".velden-zichtbaar-kolom",
      this.e_modal,
    );
    this.e_velden_verplicht_kolom = gld.querySelector(
      ".velden-verplicht-kolom",
      this.e_modal,
    );

    config_promise
      .then(({ heeft_recaptcha_key }) => {
        if (!heeft_recaptcha_key) {
          this.e_form_recaptcha.disabled = true;
        }
      })
      .catch(this.error_handler.bind(this));

    if (this.lijst_id == null) {
      this.e_modal.classList.add("is-nieuw");
      this.maak_lege_veld_checks().catch(this.error_handler.bind(this));
    } else {
      this.vul_data().catch(this.error_handler.bind(this));
    }
    gld.querySelectorTagName("body").appendChild(this.e_modal);

    // Lijst opslaan
    this.e_form.addEventListener("submit", (event) => {
      this.opslaan(event).catch(this.error_handler.bind(this));
    });

    // Lijst verwijderen
    document
      .getElementById("verwijder-lijst")
      ?.addEventListener("click", () => {
        this.verwijder_lijst().catch(this.error_handler.bind(this));
      });

    this.modal = new Modal(this.e_modal, {
      backdrop: true,
      focus: true,
      keyboard: true,
    });
    this.e_modal.addEventListener("hidden.bs.modal", this.destroy.bind(this));

    this.modal.show();
  }

  private error_handler(e: unknown) {
    if (e instanceof Error) {
      console.error(e.message);
    } else {
      console.error(e);
    }
  }

  private async vul_data() {
    if (this.lijst_id == null) {
      return;
    }
    this.e_form_lijst.value = String(this.lijst_id);
    const data = await api.post("get_beheer_lijstdata", {
      lijst: this.lijst_id,
    });

    for (const e_lijst_naam of this.e_modal.querySelectorAll(".lijst-naam")) {
      e_lijst_naam.textContent = data.naam;
    }
    this.e_form_naam.value = data.naam;
    this.e_form_starttijd.value =
      data.starttijd == null
        ? ""
        : functies.date_to_datetime_local(data.starttijd);
    this.e_form_eindtijd.value =
      data.eindtijd == null
        ? ""
        : functies.date_to_datetime_local(data.eindtijd);
    this.e_form_minkeuzes.value = String(data.minkeuzes);
    this.e_form_maxkeuzes.value = String(data.maxkeuzes);
    this.e_form_vrijekeuze.value = String(data.vrijekeuzes);
    this.e_form_stemmen_per_ip.value = String(data.stemmen_per_ip);
    this.e_form_artiest_eenmalig.checked = data.artiest_eenmalig;
    this.e_form_mail_stemmers.checked = data.mail_stemmers;
    this.e_form_random_volgorde.checked = data.random_volgorde;
    this.e_form_recaptcha.checked = data.recaptcha;
    this.e_form_email.value = data.email;
    this.e_form_bedankt_tekst.value = data.bedankt_tekst;

    for (const veld of data.velden) {
      this.maak_veld_checks(veld);
    }
  }

  private async maak_lege_veld_checks() {
    const velden = await api.post("get_alle_velden", {});
    for (const veld of velden) {
      this.maak_veld_checks(veld);
    }
  }

  private maak_veld_checks(
    veld: AjaxMap["get_beheer_lijstdata"]["response"]["velden"][number],
  ) {
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
    e_zichtbaar_label.setAttribute("for", zichtbaar_id);

    e_verplicht_check.classList.add("form-check-input");
    e_verplicht_check.type = "checkbox";
    e_verplicht_check.id = verplicht_id;
    e_verplicht_check.name = `velden[${veld.id}][verplicht]`;
    e_verplicht_check.checked = veld.verplicht;
    e_verplicht_check.disabled = !veld.tonen;

    e_verplicht_label.classList.add("form-check-label");
    e_verplicht_label.setAttribute("for", verplicht_id);
  }

  /**
   * Lijst metadata opslaan.
   */
  private async opslaan(event: Event) {
    event.preventDefault();

    const minkeuzes = Number.parseInt(this.e_form_minkeuzes.value);
    const min_vrijekeuzes = Number.parseInt(this.e_form_vrijekeuze.value);
    if (minkeuzes + min_vrijekeuzes < 1) {
      alert(
        "Er moet minimaal 1 keuze mogelijk zijn (gewone keuzes + vrije keuzes).",
      );
      return;
    }

    const fd = new FormData(this.e_form);
    fd.set(
      "starttijd",
      functies
        .get_datetime_local_value(this.e_form_starttijd.value)
        ?.toISOString() ?? "",
    );
    fd.set(
      "eindtijd",
      functies
        .get_datetime_local_value(this.e_form_eindtijd.value)
        ?.toISOString() ?? "",
    );

    if (this.lijst_id == null) {
      // Nieuwe lijst
      try {
        const lijst_id = await api.post("lijst_maken", fd);
        this.on_lijst_gemaakt.emit({
          id: lijst_id,
          naam: this.e_form_naam.value,
        });
        this.modal.hide();
      } catch (error: unknown) {
        const msg = error instanceof Error ? error.message : String(error);
        alert(msg);
      }
    } else {
      // bestaande lijst
      try {
        await api.post("lijst_opslaan", fd);
        this.on_lijst_veranderd.emit({
          id: this.lijst_id,
          naam: this.e_form_naam.value,
        });
        this.modal.hide();
      } catch (error: unknown) {
        const msg = error instanceof Error ? error.message : String(error);
        alert(msg);
      }
    }
  }

  private async verwijder_lijst() {
    if (this.lijst_id == null) return;
    const vraag =
      "Deze lijst verwijderen?\nOok alle stemmen op nummers uit deze lijst worden verwijderd.";
    if (confirm(vraag)) {
      try {
        await api.post("verwijder_lijst", { lijst: this.lijst_id });
        this.on_lijst_verwijderd.emit({
          id: this.lijst_id,
        });
        this.modal.hide();
      } catch (error: unknown) {
        const msg = error instanceof Error ? error.message : String(error);
        alert(msg);
      }
    }
  }

  /**
   * Handler voor wanneer er een vinkje verandert in de invoervelden.
   * Wanneer een vinkje wordt uitgezet wordt het bijbehorende 'verplicht'-vinkje
   * uitgezet en disabled.
   */
  private check_verplicht(event: Event) {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    const verplicht_id = target.getAttribute("data-input-verplicht") ?? "";
    const verplicht_elem = document.getElementById(verplicht_id);
    if (verplicht_elem instanceof HTMLInputElement) {
      if (target.checked) {
        verplicht_elem.disabled = false;
      } else {
        verplicht_elem.disabled = true;
        verplicht_elem.checked = false;
      }
    }
  }

  private destroy() {
    this.e_modal.remove();
  }
}

const beheer_modal_template: HTMLElement =
  functies.get_html_template_enkel(html_beheer_modal);

export { BeheerModal };
