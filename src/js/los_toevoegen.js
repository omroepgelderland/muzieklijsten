// Libraries js
import 'bootstrap';
import 'angular';
import 'angular-route';

// Project js
import * as functies from './functies.js';

// Libraries css
import '../scss/algemeen.scss';
import 'bootstrap/dist/css/bootstrap.min.css';

// Project css
import '../scss/los_toevoegen.scss';

class Nummer {

  artiest;
  titel;

  constructor(artiest, titel) {
    if (artiest === undefined) {
      artiest = '';
    }
    if (titel === undefined) {
      titel = '';
    }
    this.artiest = artiest;
    this.titel = titel;
  }

  isLeeg() {
    return (this.artiest === '' && this.titel === '');
  }

}

class Lijst {

  id;
  naam;
  geselecteerd;

  constructor(id, naam) {
    this.id = id;
    this.naam = naam;
    this.geselecteerd = false;
  }

}

async function get_lijsten() {
  const data = await functies.get_lijsten();
  let lijsten = [];
  for (const item of data) {
    lijsten.push(new Lijst(item.id, item.naam));
  }
  return lijsten;
}

var app = angular.module('losseNummers', []);

app.controller('MainCtrl', [
  '$scope', '$http',
  ($scope, $http) => {
    $scope.nummers = [new Nummer()];
    $scope.een_toegevoegd = false;
    $scope.meerdere_toegevoegd = false;
    $scope.error = false;
    $scope.inlog_error = false;
    $scope.inlog_ok = false;

    functies.login().then(() => {
      $scope.inlog_ok = true;
      $scope.$apply();
    }, (msg) => {
      $scope.inlog_error = true;
      $scope.inlog_error_msg = msg;
      $scope.$apply();
    });

  $scope.lijsten = [];
    get_lijsten().then((lijsten) => {
      for (const lijst of lijsten) {
        $scope.lijsten.push(lijst);
      }
      $scope.$apply();
    }, (msg) => {
      $scope.error = true;
      $scope.error_msg = msg;
      $scope.$apply();
    });
    
    $scope.inputChangeHandler = (ch_nummer) => {
      if ($scope.nummers.indexOf(ch_nummer) == $scope.nummers.length - 1) {
        // Laatste item is aangepast
        if (!ch_nummer.isLeeg()) {
          $scope.nummers.push(new Nummer());
        }
      }
    };
    
    $scope.toevoegen = () => {
      let geselecteerde_lijsten_ids = [];
      for (const lijst of $scope.lijsten) {
        if ( lijst.geselecteerd ) {
          geselecteerde_lijsten_ids.push(lijst.id);
        }
      }
      functies.losse_nummers_toevoegen($scope.nummers, geselecteerde_lijsten_ids).then((data) => {
        // Gelukt
        //console.log('gelukt');
        //console.log(data);
        $scope.toegevoegde_nummers = data.toegevoegd;
        $scope.dubbele_nummers = data.dubbel;
        $scope.lijsten_nummers = data.lijsten_nummers;
        $scope.een_toegevoegd = ($scope.toegevoegde_nummers === 1);
        $scope.meerdere_toegevoegd = ($scope.toegevoegde_nummers != 1);
        $scope.een_dubbel = ($scope.dubbele_nummers == 1);
        $scope.meerdere_dubbel = ($scope.dubbele_nummers != 1 && $scope.dubbele_nummers != 0);
        $scope.een_lijst_toegevoegd = $scope.lijsten_nummers == 1;
        $scope.meerdere_lijst_toegevoegd = !$scope.een_lijst_toegevoegd;
        $scope.error = false;
        $scope.nummers = [new Nummer()];
        $scope.$apply();
      }, (msg) => {
        // Mislukt
        $scope.error_msg = `Het toevoegen is mislukt: ${msg}`;
        $scope.een_toegevoegd = false;
        $scope.meerdere_toegevoegd = false;
        $scope.error = true;
        $scope.$apply();
      });
    };
}]);
