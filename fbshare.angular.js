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

var app = angular.module('fbShare', []);

app.config(function($locationProvider) {
	$locationProvider.html5Mode({
		'enabled': true,
		'requireBase': false
	});
});

app.controller('MainCtrl', [
	'$scope', '$http', '$location',
	function($scope, $http, $location) {
		var so = $location.search();
		$scope.stemmer_id = so.stemmer
		
		$http.post('fbshare.php', {
			'query': 'getNummers',
			'stemmer_id': $scope.stemmer_id
		}).then(function(data) {
			//console.log(data);
			$scope.nummers = [];
			angular.forEach(data['data'], function(value, key) {
				console.log(value);
				$scope.nummers.push(new Nummer(value['artiest'], value['titel']));
			});
		}, function(data) {
			console.log(data);
		});
		
		//$scope.nummers = [new Nummer($routeParams.test, 'test'), new Nummer('test2', 'test2')];
}]);
