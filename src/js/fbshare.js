// Libraries css
import 'bootstrap';

// Project js
// import * as server from '@muzieklijsten/server';
// import * as functies from '@muzieklijsten/functies';

// Libraries css
import 'bootstrap/dist/css/bootstrap.min.css';

// Project css
import '../scss/algemeen.scss';
import '../scss/fbshare.scss';

import '../../assets/afbeeldingen/fbshare_top100.jpg';

(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s);
  js.id = id;
  js.src = 'https://connect.facebook.net/nl_NL/sdk.js#xfbml=1&version=v2.10&appId=1269120393132176';
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));
