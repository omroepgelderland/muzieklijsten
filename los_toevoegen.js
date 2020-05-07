var app = angular.module('losseNummers', []);

function Nummer(artiest, titel) {
	var _self = this;
	if ( artiest == undefined ) {
		artiest = '';
	}
	if ( titel == undefined ) {
		titel = '';
	}
	_self.artiest = artiest;
	_self.titel = titel;
}

//Nummer.prototype.set = function() {}

Nummer.prototype.isLeeg = function() {
	var _self = this;
	return ( _self.artiest == '' && _self.titel == '' );
};

function Lijst(id, naam) {
	var _self = this;
	_self.id = id;
	_self.naam = naam;
	_self.geselecteerd = false;
}

app.controller('MainCtrl', [
	'$scope', '$http',
	function($scope, $http) {
		$scope.nummers = [new Nummer()];
		$scope.een_toegevoegd = false;
		$scope.meerdere_toegevoegd = false;
		$scope.error = false;
		
		$scope.lijsten = [];
		$http.post('los_toevoegen.php', {
			'query': 'getLijsten'
		}).then(function(data) {
			console.log(data);
			angular.forEach(data['data'], function(value, key) {
				$scope.lijsten.push(new Lijst(value['id'], value['naam']));
			});
		}, function(data) {
			console.log(data);
		});
		
		$scope.inputChangeHandler = function(ch_nummer) {
			if ( $scope.nummers.indexOf(ch_nummer) == $scope.nummers.length - 1 ) {
				// Laatste item is aangepast
				if ( ! ch_nummer.isLeeg() ) {
					$scope.nummers.push(new Nummer());
				}
			}
		};
		$scope.toevoegen = function() {
			var lijsten = [];
			angular.forEach($scope.lijsten, function(lijst, key) {
				if ( lijst.geselecteerd ) {
					lijsten.push(lijst.id);
				}
			});
			$http.post('los_toevoegen.php', {
				'query': 'toevoegen',
				'loginnaam': $scope.loginnaam,
				'wachtwoord': $scope.wachtwoord,
				'nummers': $scope.nummers,
				'lijsten': lijsten
			}).then(function(data) {
				// Gelukt
				//console.log('gelukt');
				//console.log(data);
				$scope.toegevoegde_nummers = data['data']['toegevoegd'];
				$scope.dubbele_nummers = data['data']['dubbel'];
				$scope.een_toegevoegd = ( $scope.toegevoegde_nummers == 1 );
				$scope.meerdere_toegevoegd = ( $scope.toegevoegde_nummers != 1 );
				$scope.een_dubbel = ( $scope.dubbele_nummers == 1 );
				$scope.meerdere_dubbel = ( $scope.dubbele_nummers != 1 && $scope.dubbele_nummers != 0 );
				$scope.error = false;
				$scope.nummers = [new Nummer()];
			}, function(data) {
				// Mislukt
				//console.log('mislukt');
				//console.log(data);
				$scope.error_msg = angular.fromJson(data['statusText'])['message'];
				$scope.een_toegevoegd = false;
				$scope.meerdere_toegevoegd = false;
				$scope.error = true;
			});
		};
}]);
