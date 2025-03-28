export type AjaxMap = {
  get_stemlijst_frontend_data: {
    request: {
      lijst: number;
    };
    response: {
      minkeuzes: number;
      maxkeuzes: number;
      vrijekeuzes: number;
      is_artiest_eenmalig: boolean;
      organisatie: string;
      lijst_naam: string;
      heeft_gebruik_recaptcha: boolean;
      is_actief: boolean;
      velden: {
        id: number;
        label: string;
        leeg_feedback: string;
        type: string;
        verplicht: boolean;
        max: number;
        maxlength: number;
        min: number;
        minlength: number;
        placeholder: string;
      }[];
      recaptcha_sitekey: string;
      privacy_url: string;
      random_volgorde: boolean;
    };
  };
  verwijder_lijst: {
    request: {
      lijst: number;
    };
    response: void;
  };
  lijst_opslaan: {
    request: FormData;
    response: void;
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
    response: void;
  };
  verwijder_stem: {
    request: {
      nummer: number;
      lijst: number;
      stemmer: number;
    };
    response: void;
  };
  verwijder_nummer: {
    request: {
      lijst: number;
      nummer: number;
    };
    response: void;
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
    response: void;
  };
  lijst_nummer_verwijderen: {
    request: {
      lijst: number;
      nummer: number;
    };
    response: void;
  };
  get_geselecteerde_nummers: {
    request: {
      lijst: number;
    };
    response: {
      id: number;
      titel: string;
      artiest: string;
      jaar: number;
    }[];
  };
  vul_datatables: {
    request: { [index: string]: any };
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
      nummer: {
        artiest: string;
        id: number;
        is_vrijekeuze: boolean;
        titel: string;
      };
      stemmen: {
        ip: string;
        is_behandeld: boolean;
        stemmer_id: number;
        timestamp: string;
        toelichting: string;
        velden: {
          type: string;
          waarde: string;
        }[];
      }[];
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
      is_actief: boolean;
      minkeuzes: number;
      maxkeuzes: number;
      vrijekeuzes: number;
      stemmen_per_ip: number | null;
      artiest_eenmalig: boolean;
      mail_stemmers: boolean;
      random_volgorde: boolean;
      recaptcha: boolean;
      email: string;
      bedankt_tekst: string;
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
    response: void;
  };
};

/**
 * Voert een post-request uit aan de server.
 */
export function post<F extends keyof AjaxMap>(
  functie: F,
  data: AjaxMap[F]["request"],
): Promise<AjaxMap[F]["response"]> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax.php", true);
    xhr.onload = (post_verwerk_respons<AjaxMap[F]["response"]>).bind(
      undefined,
      xhr,
      resolve,
      reject,
    );
    xhr.onerror = (post_verwerk_respons<AjaxMap[F]["response"]>).bind(
      undefined,
      xhr,
      resolve,
      reject,
    );

    let send_data: FormData | string;
    if (data instanceof FormData) {
      data.append("functie", functie);
      send_data = data;
    } else {
      send_data = JSON.stringify({
        functie: functie,
        ...data,
      });
      xhr.setRequestHeader("Content-Type", "application/json");
    }
    xhr.send(send_data);
  });
}

/**
 * Verwerkt een serverrespons.
 * @param resolve - Uit te voeren functie bij een succesvolle uitvoering.
 * @param reject - Uit te voeren functie bij een mislukt request.
 */
function post_verwerk_respons<T>(
  xhr: XMLHttpRequest,
  resolve: (respons: T) => void,
  reject: (error: Error) => void,
  event: ProgressEvent,
) {
  try {
    const data = JSON.parse(xhr.response);
    if (data.error !== false) {
      reject(new Error(data.errordata));
    } else {
      resolve(data.data);
    }
  } catch (error) {
    reject(new Error(xhr.responseText));
  }
}
