import { AjaxData as DTAjaxData } from "datatables.net-bs5";

/* eslint-disable @typescript-eslint/no-empty-object-type */
interface AISuggestieType {
  is_correct: boolean;
  suggestie?: {
    artiest: string;
    titel: string;
  };
}

interface VrijeKeuzeItemWaardes {
  artiest: string;
  titel: string;
  db: boolean;
}

interface ModVrijeKeuzeNummer {
  id: number;
  artiest: string;
  titel: string;
  ai_suggestie: AISuggestieType;
}

interface ModVrijeKeuzeOpslaanData extends VrijeKeuzeItemWaardes {
  id: number;
  lijst_id: number;
}

interface Config {
  heeft_openai_key: boolean;
  heeft_recaptcha_key: boolean;
}

interface Stem {
  ip: string;
  is_behandeld: boolean;
  stemmer_id: number;
  timestamp: string;
  toelichting: string;
  velden: {
    type: string;
    waarde: string;
  }[];
}

interface Nummer {
  artiest: string;
  id: number;
  is_vrijekeuze: boolean;
  titel: string;
  duur: number | null;
}

interface LijstFrontendData {
  minkeuzes: number;
  maxkeuzes: number;
  vrijekeuzes: number;
  is_artiest_eenmalig: boolean;
  organisatie: string;
  lijst_naam: string;
  heeft_gebruik_recaptcha: boolean;
  starttijd: string | null;
  eindtijd: string | null;
  velden: FrontendVeld[];
  recaptcha_sitekey: string | null;
  privacy_url: string;
  random_volgorde: boolean;
}

interface FrontendVeld {
  id: number;
  label: string;
  leeg_feedback: string;
  type: string;
  verplicht: boolean;
  max?: number;
  maxlength?: number;
  min?: number;
  minlength?: number;
  placeholder?: string;
}

interface AjaxMap {
  get_stemlijst_frontend_data: {
    request: {
      lijst: number;
    };
    response: LijstFrontendData;
  };
  verwijder_lijst: {
    request: {
      lijst: number;
    };
    response: undefined;
  };
  lijst_opslaan: {
    request: FormData;
    response: undefined;
  };
  lijst_maken: {
    request: FormData;
    response: number;
  };
  losse_nummers_toevoegen: {
    request: {
      nummers: {
        artiest: string;
        titel: string;
      }[];
      lijsten: number[];
    };
    response: {
      toegevoegd: number;
      dubbel: number;
      lijsten_nummers: number;
    };
  };
  get_lijsten: {
    request: {};
    response: {
      id: number;
      naam: string;
    }[];
  };
  stem_set_behandeld: {
    request: {
      nummer: number;
      lijst: number;
      stemmer: number;
      waarde: boolean;
    };
    response: undefined;
  };
  verwijder_stem: {
    request: {
      nummer: number;
      lijst: number;
      stemmer: number;
    };
    response: undefined;
  };
  verwijder_nummer: {
    request: {
      lijst: number;
      nummer: number;
    };
    response: undefined;
  };
  get_totaal_aantal_stemmers: {
    request: {
      lijst: number;
      van?: string;
      tot?: string;
    };
    response: number;
  };
  stem: {
    request: FormData;
    response: string;
  };
  lijst_nummer_toevoegen: {
    request: {
      lijst: number;
      nummer: number;
    };
    response: undefined;
  };
  lijst_nummer_verwijderen: {
    request: {
      lijst: number;
      nummer: number;
    };
    response: undefined;
  };
  get_geselecteerde_nummers: {
    request: {
      lijst: number;
    };
    response: {
      id: number;
      titel: string;
      artiest: string;
      jaar: number | null;
    }[];
  };
  vul_datatables: {
    request: DTAjaxData & {
      random_volgorde?: boolean;
      lijst?: number;
      is_vrijekeuze: boolean;
      random_seed?: number;
    };
    response: {
      draw: number;
      recordsTotal: number;
      recordsFiltered: number;
      data: string[][];
    };
  };
  get_resultaten_labels: {
    request: {
      lijst: number;
    };
    response: string[];
  };
  get_resultaten: {
    request: {
      lijst: number;
    };
    response: {
      nummer: Nummer;
      stemmen: Stem[];
    }[];
  };
  get_lijst_metadata: {
    request: {
      lijst: number;
    };
    response: {
      naam: string;
      nummer_ids: number[];
      iframe_url: string;
    };
  };
  get_metadata: {
    request: {};
    response: {
      organisatie: string;
      lijsten: {
        id: number;
        naam: string;
      }[];
      nimbus_url: string;
      totaal_aantal_nummers: number;
    };
  };
  get_alle_velden: {
    request: {};
    response: {
      id: number;
      tonen: false;
      label: string;
      verplicht: false;
    }[];
  };
  get_beheer_lijstdata: {
    request: {
      lijst: number;
    };
    response: {
      naam: string;
      starttijd: string | null;
      eindtijd: string | null;
      minkeuzes: number;
      maxkeuzes: number;
      vrijekeuzes: number;
      stemmen_per_ip: number | null;
      artiest_eenmalig: boolean;
      recaptcha: boolean;
      email: string;
      bedankt_tekst: string;
      mail_stemmers: boolean;
      random_volgorde: boolean;
      velden: {
        id: number;
        tonen: boolean;
        label: string;
        verplicht: boolean;
      }[];
    };
  };
  login: {
    request: {};
    response: undefined;
  };
  mod_vrijekeuze_get_nummers: {
    request: {
      lijst_id: number;
      niet_ids: number[];
    };
    response: ModVrijeKeuzeNummer[];
  };
  mod_vrijekeuze_nummer_opslaan: {
    request: ModVrijeKeuzeOpslaanData;
    response: undefined;
  };
  mod_vrijekeuze_nummer_verwijderen: {
    request: {
      nummer: number;
    };
    response: undefined;
  };
  get_config: {
    request: {};
    response: Config;
  };
}

export type {
  AjaxMap,
  Config,
  FrontendVeld,
  LijstFrontendData,
  ModVrijeKeuzeNummer,
  ModVrijeKeuzeOpslaanData,
  Nummer,
  Stem,
};
