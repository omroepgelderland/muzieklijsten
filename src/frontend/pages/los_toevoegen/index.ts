// Libraries js
import "bootstrap";
import * as gld from "gld-ts-lib/functions";

// Project js
import * as api from "@muzieklijsten/api";
// import * as functies from '@muzieklijsten/functies';

// css
import "./index.scss";

type NummerField = "artiest" | "titel" | "jaar" | "duur";

class Nummer {
  constructor(
    public artiest = "",
    public titel = "",
    public jaar = "",
    public duur = "",
  ) {}

  public isLeeg(): boolean {
    return this.artiest.trim() === "" || this.titel.trim() === "";
  }
}

class Lijst {
  public geselecteerd: boolean;

  constructor(
    public id: string | number,
    public naam: string,
  ) {
    this.geselecteerd = false;
  }
}

async function get_lijsten(): Promise<Lijst[]> {
  const data = await api.post("get_lijsten", {});
  return data.map(({ id, naam }) => {
    return new Lijst(id, naam);
  });
}

class LosseNummersView {
  private readonly form;
  private readonly loginErrorContainer;
  private readonly loginErrorMessage;
  private readonly pageContent;
  private readonly listContainer;
  private readonly rowsContainer;
  private readonly errorAlert;
  private readonly errorMessage;
  private readonly eenToegevoegd;
  private readonly meerdereToegevoegd;
  private readonly eenDubbel;
  private readonly meerdereDubbel;
  private readonly eenLijstToegevoegd;
  private readonly meerdereLijstToegevoegd;

  private nummers: Nummer[] = [new Nummer()];
  private lijsten: Lijst[] = [];
  private toegevoegdeNummers = 0;
  private dubbeleNummers = 0;
  private lijstenNummers = 0;

  constructor() {
    this.form = gld.querySelectorTagName("form", "#losse-nummers-form");
    this.loginErrorContainer = gld.getElementById("login-error-container");
    this.loginErrorMessage = gld.getElementById("login-error-message");
    this.pageContent = gld.getElementById("losse-nummers-content");
    this.listContainer = gld.getElementById("lijst-container");
    this.rowsContainer = gld.querySelectorTagName("tbody", "#nummer-rows");
    this.errorAlert = gld.getElementById("error-alert");
    this.errorMessage = gld.getElementById("error-message");
    this.eenToegevoegd = gld.getElementById("een-toegevoegd");
    this.meerdereToegevoegd = gld.getElementById("meerdere-toegevoegd");
    this.eenDubbel = gld.getElementById("een-dubbel");
    this.meerdereDubbel = gld.getElementById("meerdere-dubbel");
    this.eenLijstToegevoegd = gld.getElementById("een-lijst-toegevoegd");
    this.meerdereLijstToegevoegd = gld.getElementById(
      "meerdere-lijst-toegevoegd",
    );

    this.bindEvents();
    this.renderNummers();
    this.renderResultStatus();
    this.init().catch((error) => this.error_handler(error));
  }

  private bindEvents(): void {
    this.form.addEventListener("submit", (event) => {
      event.preventDefault();
      this.toevoegen().catch((error) => this.error_handler(error));
    });
  }

  private async init(): Promise<void> {
    try {
      await api.post("login", {});
      this.pageContent.hidden = false;
      this.loginErrorContainer.hidden = true;
    } catch (msg) {
      this.loginErrorContainer.hidden = false;
      this.loginErrorMessage.textContent = String(msg);
      this.pageContent.hidden = true;
      return;
    }

    this.lijsten = await get_lijsten();
    this.renderLijsten();
  }

  private renderLijsten(): void {
    this.listContainer.replaceChildren(
      ...this.lijsten.map((lijst) => {
        const wrapper = document.createElement("div");
        wrapper.className = "col-12 col-sm-6 col-lg-4 lijst-item";

        const label = document.createElement("label");
        const checkbox = document.createElement("input");

        checkbox.type = "checkbox";
        checkbox.checked = lijst.geselecteerd;
        checkbox.addEventListener("change", () => {
          lijst.geselecteerd = checkbox.checked;
        });

        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(` ${lijst.naam}`));
        wrapper.appendChild(label);
        return wrapper;
      }),
    );
  }

  private renderNummers(): void {
    this.rowsContainer.replaceChildren(
      ...this.nummers.map((nummer, index) => {
        const row = document.createElement("tr");

        const fields: { key: NummerField; label: string }[] = [
          { key: "artiest", label: "Artiest" },
          { key: "titel", label: "Titel" },
          { key: "jaar", label: "Jaartal" },
          { key: "duur", label: "Lengte" },
        ];

        for (const field of fields) {
          const cell = document.createElement("td");
          const wrapper = document.createElement("div");
          const input = document.createElement("input");
          input.type = field.key === "jaar" ? "number" : "text";
          input.className = "form-control";
          input.placeholder = field.label;
          input.autocapitalize = "on";
          input.value = nummer[field.key];
          input.addEventListener("input", () => {
            nummer[field.key] = input.value;
            if (
              index === this.nummers.length - 1 &&
              !this.nummers[index].isLeeg()
            ) {
              this.nummers.push(new Nummer());
              this.renderNummers();
            }
          });

          wrapper.appendChild(input);
          cell.appendChild(wrapper);
          row.appendChild(cell);
        }

        return row;
      }),
    );
  }

  private renderResultStatus(): void {
    this.eenToegevoegd.hidden = this.toegevoegdeNummers !== 1;
    this.meerdereToegevoegd.hidden = this.toegevoegdeNummers <= 1;
    this.eenDubbel.hidden = this.dubbeleNummers !== 1;
    this.meerdereDubbel.hidden =
      this.dubbeleNummers === 0 || this.dubbeleNummers === 1;
    this.eenLijstToegevoegd.hidden = this.lijstenNummers !== 1;
    this.meerdereLijstToegevoegd.hidden = this.lijstenNummers === 0;

    const toegevoegdeTekst =
      this.toegevoegdeNummers === 1
        ? "Er is één nummer toegevoegd aan de database."
        : this.toegevoegdeNummers > 1
          ? `Er zijn ${this.toegevoegdeNummers} nummers toegevoegd aan de database.`
          : "";
    this.meerdereToegevoegd.textContent = toegevoegdeTekst;
    this.eenToegevoegd.textContent = toegevoegdeTekst;

    this.eenDubbel.textContent = "één nummer stond al in de database.";
    this.meerdereDubbel.textContent = `${this.dubbeleNummers} nummers stonden al in de database.`;
    this.eenLijstToegevoegd.textContent =
      "Één nummer is aan een stemlijst toegevoegd.";
    this.meerdereLijstToegevoegd.textContent = `${this.lijstenNummers} nummers zijn aan een stemlijst toegevoegd.`;
  }

  private async toevoegen(): Promise<void> {
    const geselecteerdeLijsten = this.lijsten
      .filter((lijst) => lijst.geselecteerd)
      .map((lijst) => Number(lijst.id));

    try {
      const data = await api.post("losse_nummers_toevoegen", {
        nummers: this.nummers.filter((nummer) => !nummer.isLeeg()),
        lijsten: geselecteerdeLijsten,
      });

      this.toegevoegdeNummers = data.toegevoegd ?? 0;
      this.dubbeleNummers = data.dubbel ?? 0;
      this.lijstenNummers = data.lijsten_nummers ?? 0;
      this.errorAlert.hidden = true;
      this.nummers = [new Nummer()];
      this.renderNummers();
      this.renderResultStatus();
    } catch (error) {
      this.toegevoegdeNummers = 0;
      this.dubbeleNummers = 0;
      this.lijstenNummers = 0;
      this.renderResultStatus();
      throw error;
    }
  }

  private error_handler(error: unknown): void {
    this.errorAlert.hidden = false;
    if (error instanceof Error) {
      this.errorMessage.textContent = error.message;
    } else {
      this.errorMessage.textContent = String(error);
    }
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    new LosseNummersView();
  });
} else {
  new LosseNummersView();
}
