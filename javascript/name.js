var nameApp = angular.module('nameApp', ['ngRoute', 'ui.bootstrap', 'ngCookies'])
    .service('dataService', ['$http', function ($http) {

        var suggestions = [],
            votes = [],
            submit = function (suggestion, callback) {
                var config = {
                    method: "get",
                    url: "php/name.php",
                    params: suggestion
                };
                $http(config).success(function (data, status, headers, config) {
                    console.log(data, status, headers, config);
                    callback(data);
                });
            }, getSuggestions = function (passPhrase, email) {
                var config = {
                    method: "get",
                    url: "php/name.php",
                    params: {list: true}
                };
                $http(config).success(function (data) {
                    var i = 0, suggestion;
                    suggestions.length = 0;
                    for (i; i < data.length; i = i + 1) {
                        suggestion = data[i];
                        if (passPhrase) {
                            var decrypted = CryptoJS.AES.decrypt(suggestion.verification, passPhrase);
                            try {
                                if (decrypted.toString(CryptoJS.enc.Utf8) == suggestion.suggestion) {
                                    suggestion.userSuggestion = true;
                                }
                            } catch (e) {
                                // Sometimes de decryption to string fails.
                            }
                        }
                        suggestions.push(suggestion);
                    }
                    getVotes(email);

                });
            }, vote = function (id) {
                var config = {
                    method: "GET",
                    url: "php/name.php",
                    params: {vote: true, id: id}
                };
                return $http(config);

            };

        function getVotes(email) {
            var config = {
                method: "GET",
                url: "php/name.php",
                params: {votes: true}
            };
            $http(config).success(function (data) {
                var voteList = {}, i = 0;
                console.log(data);
                if (data) {
                    votes.length = 0;
                    for (i; i < data.length; i = i + 1) {
                        if (data[i].email == email) {
                            voteList[data[i].vote] = true;
                        }
                    }
                    suggestions.forEach(function (suggestion) {
                        if (voteList[suggestion.id]) {
                            suggestion.voted = true;
                        }
                    });

                }
            });
        }

        return {
            submit: submit,
            suggestions: suggestions,
            getSuggestions: getSuggestions,
            vote: vote
        };
    }]).
    controller('nameController',
    ['$scope', '$cookies', 'dataService', 'userService',
        function ($scope, $cookies, dataService, userService) {
            var encrypted = CryptoJS.AES.encrypt("A much longer string perhaps long enough", "Secret Passphrase");
            var decrypted = CryptoJS.AES.decrypt(encrypted, "Secret Passphrase");
            console.log(decrypted.toString(CryptoJS.enc.Utf8));

            $scope.canVote = userService.canVote;

            $scope.suggestions = dataService.suggestions;
            $scope.showPass = "password";
            $scope.togglePassphrase = function () {
                $scope.showPass = $scope.showPass == "text" ? "password" : "text";
            };

            $scope.hideInstructions = $cookies.hideInstructions === "true";

            $scope.toogleInstructions = function () {
                $scope.hideInstructions = $scope.hideInstructions ? false : true;
                $cookies.hideInstructions = $scope.hideInstructions;
            };

            $scope.passphrase = $cookies.passphrase;
            $scope.saveCookie = function () {
                $cookies.passphrase = $scope.passphrase;
                $scope.passForm.$setPristine();
            };

            $scope.submitSuggestion = function () {
                console.log($scope.passphrase);
                console.log($scope.suggestion);
                var encrypted = CryptoJS.AES.encrypt($scope.suggestion, $scope.passphrase),
                    suggestion = {
                        suggestion: $scope.suggestion,
                        verification: encrypted.toString(),
                        motivation: $scope.motivation
                    }
                console.log(encrypted.toString());
                dataService.submit(suggestion, function (result) {
                    console.log(result); //TODO: Handle error.
                    dataService.getSuggestions($scope.passphrase);
                })
            };

            $scope.vote = function (id) {
                dataService.vote(id).then(
                    function (data) {
                        console.log(data); // TODO: Fix this error handling.
                        dataService.getSuggestions($scope.passphrase, userService.userId);
                    });

            };
            dataService.getSuggestions($scope.passphrase, userService.userId);
        }]).controller('LoginController', ['$scope', 'userService',
        function ($scope, userService) {
            $scope.loggedIn = userService.userId != '';

            $scope.login = function () {
                window.location = './name.php?login';
            };

            $scope.disconnect = function () {
                window.location = './?logout';
            };
        }]);





