// Libraries js
import "bootstrap";
import * as gld from "gld-ts-lib/functions";

// Project js
import * as functies from "@muzieklijsten/functies";
import * as server from "@muzieklijsten/server";

// css
import "/src/scss/mod-vrijekeuzes.scss";
import TypedEvent from "@muzieklijsten/TypedEvent";

interface IDMap {
  "vrijekeuze-item": HTMLTemplateElement;
  lijst: HTMLElement;
  "meer-laden": HTMLButtonElement;
  errormsg: HTMLElement;
}

type AISuggestieType =
  | {
      is_correct: true;
    }
  | {
      is_correct: false;
      suggestie?: {
        artiest: string;
        titel: string;
      };
    };

interface Nummer {
  id: number;
  artiest: string;
  titel: string;
  ai_suggestie: AISuggestieType;
}

interface VrijeKeuzeItemWaardes {
  artiest: string;
  titel: string;
  db: boolean;
}

interface OpslaanData extends VrijeKeuzeItemWaardes {
  id: number;
}

class Main {
  private readonly item_controllers: Map<number, ItemController>;
  private readonly view;

  constructor() {
    this.view = new View();
    this.item_controllers = new Map();

    this.view.on_meer_laden.on(this.meer_laden.bind(this));
    this.view.on_nummer_kies_suggestie.on((id) => {
      this.item_controllers.get(id)?.kies_suggestie();
    });
    this.view.on_nummer_kies_opslaan_suggestie.on(async (id) => {
      const item_controller = this.item_controllers.get(id);
      item_controller?.kies_suggestie();
      await item_controller?.opslaan();
    });
    this.view.on_nummer_opslaan.on(async (id) => {
      await this.item_controllers.get(id)?.opslaan();
    });
    this.view.on_nummer_verwijderen.on(async (id) => {
      await this.item_controllers.get(id)?.verwijderen();
    });
  }

  private async meer_laden() {
    this.view.set_meer_laden_bezig(true);
    let nummers;
    try {
      nummers = await server.post("mod_vrijekeuze_get_nummers", {
        niet_ids: Array.from(this.item_controllers.keys()),
      });
    } catch (error) {
      this.view.set_error(error);
      this.view.set_meer_laden_bezig(false);
      throw error;
    }
    if (nummers.length === 0) {
      this.view.disable_meer_laden();
    }
    for (const nummer of nummers) {
      this.item_controllers.set(
        nummer.id,
        new ItemController(
          this.view.lijst_container,
          this.view.item_template,
          nummer,
        ),
      );
    }
    this.view.set_meer_laden_bezig(false);
    this.view.set_eerste_items_geladen();
  }
}

class ItemController {
  private readonly nummer;
  private readonly view;
  public readonly on_verwijderd;

  constructor(
    container: HTMLElement,
    template: DocumentFragment,
    nummer: Nummer,
  ) {
    this.nummer = nummer;
    this.view = new ItemView(container, template, nummer);
    this.on_verwijderd = new TypedEvent<void>();
  }

  public kies_suggestie() {
    if (
      !this.nummer.ai_suggestie.is_correct &&
      this.nummer.ai_suggestie.suggestie != null
    ) {
      this.view.kies_suggestie(
        this.nummer.ai_suggestie.suggestie.artiest,
        this.nummer.ai_suggestie.suggestie.titel,
      );
      this.view.wis_suggestie();
    }
  }

  public async opslaan() {
    this.view.set_bezig(true);
    try {
      const data = {
        id: this.nummer.id,
        ...this.view.get_waardes(),
      };
      if (data.artiest === "") {
        throw "De artiest mag niet leeg zijn.";
      }
      if (data.titel === "") {
        throw "De titel mag niet leeg zijn.";
      }
      try {
        server.post("mod_vrijekeuze_nummer_opslaan", data);
      } catch {
        throw "Opslaan mislukt";
      }
      this.view.verwijder();
      this.on_verwijderd.emit();
    } catch (error) {
      this.view.set_bezig(false);
      this.view.set_mislukt(String(error));
    }
  }

  public async verwijderen() {
    this.view.set_bezig(true);
    try {
      await server.post("mod_vrijekeuze_nummer_verwijderen", {
        nummer: this.nummer.id,
      });
      this.view.verwijder();
      this.on_verwijderd.emit();
    } catch {
      this.view.set_bezig(false);
      this.view.set_mislukt("Verwijderen mislukt");
    }
  }
}

class View {
  private readonly body;
  private readonly meer_laden;
  public readonly lijst_container;
  public readonly item_template;
  public readonly on_nummer_kies_suggestie;
  public readonly on_nummer_kies_opslaan_suggestie;
  public readonly on_nummer_opslaan;
  public readonly on_nummer_verwijderen;
  public readonly on_meer_laden;

  constructor() {
    this.body = gld.querySelectorTagName("body");
    this.meer_laden = getElementById("meer-laden");
    this.lijst_container = getElementById("lijst");
    this.item_template = getElementById("vrijekeuze-item").content;
    this.on_nummer_kies_suggestie = new TypedEvent<number>();
    this.on_nummer_kies_opslaan_suggestie = new TypedEvent<number>();
    this.on_nummer_opslaan = new TypedEvent<number>();
    this.on_nummer_verwijderen = new TypedEvent<number>();
    this.on_meer_laden = new TypedEvent<void>();

    this.meer_laden.addEventListener(
      "click",
      this.meer_laden_handler.bind(this),
    );
    document.addEventListener("click", this.click_handler.bind(this));
    document.addEventListener("submit", this.submit_handler.bind(this));
  }

  private meer_laden_handler() {
    this.on_meer_laden.emit();
  }

  private click_handler(event: Event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const id = View.get_event_nummer_id(target);

    // Nummer opslaan
    const verwijderknop = target.closest("button.verwijderknop");
    if (verwijderknop instanceof HTMLButtonElement && id != null) {
      this.on_nummer_verwijderen.emit(id);
      return;
    }

    // Kies suggestie
    const kiesknop = target.closest("button.kies-suggestie");
    if (kiesknop instanceof HTMLButtonElement && id != null) {
      this.on_nummer_kies_suggestie.emit(id);
    }

    // Kiezen & goedkeuren
    const kies_opslaan_knop = target.closest("button.kies-suggestie-opslaan");
    if (kies_opslaan_knop instanceof HTMLButtonElement && id != null) {
      this.on_nummer_kies_opslaan_suggestie.emit(id);
    }
  }

  private submit_handler(event: SubmitEvent): void {
    event.preventDefault();
    event.stopPropagation();
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const id = View.get_event_nummer_id(target);
    if (id != null) {
      this.on_nummer_opslaan.emit(id);
    }
  }

  public disable_meer_laden(): void {
    this.meer_laden.remove();
    this.body.classList.add("geen-items");
  }

  private static get_event_nummer_id(target: Element): number | null {
    const form = target.closest("form.vrijekeuze-item");
    if (form instanceof HTMLFormElement) {
      const id = Number.parseInt(form.getAttribute("data-id") ?? "");
      return isNaN(id) ? null : id;
    } else {
      return null;
    }
  }

  public set_meer_laden_bezig(is_bezig: boolean): void {
    this.meer_laden.disabled = is_bezig;
    if (is_bezig) {
      this.body.classList.add("items-laden-bezig");
    } else {
      this.body.classList.remove("items-laden-bezig");
    }
  }

  public set_error(error: unknown): void {
    this.body.classList.add("error");
    getElementById("errormsg").textContent = String(error);
  }

  public set_eerste_items_geladen(): void {
    this.body.classList.add("eerste-items-geladen");
  }
}

class ItemView {
  private readonly elems;
  private readonly form;
  private readonly artiest_input;
  private readonly titel_input;
  private readonly db_check;
  private readonly mislukt_msg;
  private readonly suggestie: Element;

  constructor(
    container: HTMLElement,
    template: DocumentFragment,
    nummer: Nummer,
  ) {
    const fragment = template.cloneNode(true) as DocumentFragment;
    this.elems = Array.from(fragment.childNodes);
    this.form = gld.querySelectorTagName("form", undefined, fragment);
    this.form.setAttribute("data-id", String(nummer.id));
    const artiest_label = gld.querySelectorTagName(
      "label",
      ".artiest-label",
      fragment,
    );
    const titel_label = gld.querySelectorTagName(
      "label",
      ".titel-label",
      fragment,
    );
    this.artiest_input = gld.querySelectorTagName(
      "input",
      ".artiest-input",
      fragment,
    );
    this.titel_input = gld.querySelectorTagName(
      "input",
      ".titel-input",
      fragment,
    );
    this.db_check = gld.querySelectorTagName("input", ".db-check", fragment);
    const ai_suggestie = gld.querySelector(".ai-suggestie", fragment);
    const ai_in_orde = gld.querySelector(".ai-in-orde", fragment);
    const ai_geen_suggestie = gld.querySelector(".ai-geen-suggestie", fragment);
    const artiest_suggestie = gld.querySelector(
      ".artiest-suggestie",
      ai_suggestie,
    );
    const titel_suggestie = gld.querySelector(".titel-suggestie", ai_suggestie);
    this.mislukt_msg = gld.querySelector(".mislukt-msg", fragment);

    this.artiest_input.id = gld.get_random_string(8);
    artiest_label.setAttribute("for", this.artiest_input.id);
    this.titel_input.id = gld.get_random_string(8);
    titel_label.setAttribute("for", this.titel_input.id);

    this.artiest_input.value = nummer.artiest;
    this.titel_input.value = nummer.titel;
    if (nummer.ai_suggestie.is_correct) {
      ai_geen_suggestie.remove();
      ai_suggestie.remove();
      this.suggestie = ai_in_orde;
    } else if (nummer.ai_suggestie.suggestie == null) {
      ai_in_orde.remove();
      ai_suggestie.remove();
      this.suggestie = ai_geen_suggestie;
    } else {
      ai_in_orde.remove();
      ai_geen_suggestie.remove();
      this.suggestie = ai_suggestie;
      artiest_suggestie.textContent = nummer.ai_suggestie.suggestie.artiest;
      titel_suggestie.textContent = nummer.ai_suggestie.suggestie.titel;
    }

    container.appendChild(fragment);
  }

  public get_waardes(): VrijeKeuzeItemWaardes {
    return {
      artiest: this.artiest_input.value.trim(),
      titel: this.titel_input.value.trim(),
      db: this.db_check.checked,
    };
  }

  public set_bezig(is_bezig: boolean): void {
    this.form.classList.remove("mislukt");
    for (const button of gld.querySelectorAllTagName(
      "button",
      undefined,
      this.form,
    )) {
      button.disabled = is_bezig;
    }
    for (const input of gld.querySelectorAllTagName(
      "input",
      undefined,
      this.form,
    )) {
      input.disabled = is_bezig;
    }
  }

  public kies_suggestie(artiest: string, titel: string): void {
    this.artiest_input.value = artiest;
    this.titel_input.value = titel;
  }

  public verwijder(): void {
    for (const elem of this.elems) {
      elem.remove();
    }
  }

  public set_mislukt(msg: string): void {
    this.mislukt_msg.textContent = msg;
    this.form.classList.add("mislukt");
  }

  public wis_suggestie(): void {
    this.suggestie.remove();
  }
}

function getElementById<I extends keyof IDMap>(elementId: I): IDMap[I] {
  return gld.getElementById<IDMap[I]>(elementId);
}

new Main();

export {
  Nummer as ModVrijeKeuzeNummer,
  OpslaanData as ModVrijeKeuzeOpslaanData,
};
