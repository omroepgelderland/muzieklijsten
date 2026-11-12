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

    if (data instanceof FormData) {
      data.append("functie", String(functie));
      xhr.send(data);
    } else {
      const send_data = JSON.stringify({
        functie: functie,
        ...data,
      });
      xhr.setRequestHeader("Content-Type", "application/json");
      xhr.send(send_data);
    }
  });
}

/**
 * Verwerkt een serverrespons.
 *
 * @param resolve - Uit te voeren functie bij een succesvolle uitvoering.
 * @param reject - Uit te voeren functie bij een mislukt request.
 */
function post_verwerk_respons<T>(
  xhr: XMLHttpRequest,
  resolve: (respons: T) => void,
  reject: (error: Error) => void,
  _event: ProgressEvent,
) {
  try {
    const data = JSON.parse(xhr.responseText) as unknown;
    if (typeof data !== "object" || data === null) {
      throw new Error(xhr.responseText);
    }
    if ("error" in data && data.error !== false) {
      if ("errordata" in data && typeof data.errordata === "string") {
        throw new Error(data.errordata);
      } else {
        throw new Error(xhr.responseText);
      }
    }
    if (!("data" in data)) {
      throw new Error(xhr.responseText);
    }
    resolve(data.data as T);
  } catch (error) {
    if (error instanceof Error) {
      reject(error);
    } else if (typeof error === "string") {
      reject(new Error(error));
    } else {
      reject(new Error(xhr.responseText));
    }
  }
}

export { post };
