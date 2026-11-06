import type { AjaxMap } from "./types";

/**
 * Voert een post-request uit aan de server.
 */
function post<F extends keyof AjaxMap>(
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

export { post };
