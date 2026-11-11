// Libraries js
import "bootstrap";

// Project js
// import * as api from '@muzieklijsten/api';
// import * as functies from '@muzieklijsten/functies';

// css
import "./index.scss";

(function (d, s, id) {
  const fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  const js = d.createElement(s) as HTMLScriptElement;
  js.id = id;
  js.src =
    "https://connect.facebook.net/nl_NL/sdk.js#xfbml=1&version=v2.10&appId=1269120393132176";
  if (fjs && fjs.parentNode) {
    fjs.parentNode.insertBefore(js, fjs);
  }
})(document, "script", "facebook-jssdk");
