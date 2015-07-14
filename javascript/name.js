/* Start with some initialization of Bootstrap */
$(function () {
    $('[data-toggle="tooltip"]').tooltip()
});

var nameApp = angular.module('nameApp', ['ui.bootstrap'])
    .service('dataService', ['$http', function ($http) {

        var suggestions = [],
            votes = [],
            submit = function (suggestion) {
                var config = {
                    method: "get",
                    url: "php/name.php",
                    params: suggestion
                };
                return $http(config);
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
    ['$scope', 'dataService', 'userService', 'alertService',
        function ($scope, dataService, userService, alertService) {
            //console.log(decrypted.toString(CryptoJS.enc.Utf8));

            $scope.canVote = userService.canVote;
            $scope.userId = userService.userId;

            $scope.suggestions = dataService.suggestions;
            $scope.showPass = "password";

            $scope.togglePassPhrase = function () {
                $scope.showPass = $scope.showPass == "text" ? "password" : "text";
            };

            $scope.hideInstructions = localStorage.getItem("hideInstructions") === "true";

            $scope.toggleInstructions = function () {
                $scope.hideInstructions = $scope.hideInstructions ? false : true;
                localStorage.setItem("hideInstructions", $scope.hideInstructions);
            };

            $scope.passphrase = localStorage.getItem("passPhrase");
            $scope.savePassPhrase = function () {
                localStorage.setItem("passPhrase", $scope.passphrase);
                $scope.passForm.$setPristine();
            };

            $scope.submitSuggestion = function () {
                var encrypted = CryptoJS.AES.encrypt($scope.suggestion, $scope.passphrase),
                    suggestion = {
                        suggestion: $scope.suggestion,
                        verification: encrypted.toString(),
                        motivation: $scope.motivation
                    };
                dataService.submit(suggestion).then(function (result) {
                    console.log(result.data.result.error);
                    if(result.data.result.error === false ) {
                        alertService.showAlert('Submission successful. Dismiss to view.', "success", "S" + (result.data.result.id -1));
                    } else {
                        alertService.showAlert("Submission failed, message from server: " + result.data.result.message, "danger");
                    }
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
            $scope.email = userService.userId;

            $scope.login = function () {
                window.location = './name.php?login';
            };

            $scope.disconnect = function () {
                window.location = './?logout';
            };
        }]).service('alertService', [
        function () {
            var alert = {},
                showAlert = function (message, type, position) {
                    alert.message = message;
                    alert.type = type;
                    alert.show = true;
                    if(position) {
                        alert.position = position;
                    }
                };

            return {
                showAlert: showAlert,
                alert: alert

            };
        }]).controller('NameAlertController', ['$scope', 'alertService', '$anchorScroll', '$timeout',
        function ($scope, alertService, $anchorScroll, $timeout) {
            var timerRunning = false,
                timer = undefined;
            $scope.alert = alertService.alert;
            $scope.closeAlert = function () {
                $scope.alert.show = false;
                if($scope.alert.position) {
                    $anchorScroll($scope.alert.position);
                }
            };
            $scope.$watch(function() {
                return $scope.alert.show;
            },function() {
                if($scope.alert && $scope.alert.show == true && timerRunning == false) {
                    timerRunning = true;
                    timer = $timeout(function() {
                        timerRunning = false;
                        $scope.alert.show = false;
                    }, 5000);
                } else if(timer !== undefined && timerRunning == true) {
                    console.log("Timeout result: " + $timeout.cancel(timer));
                    timer = undefined;
                }
                console.log("Alert is: " + $scope.alert.show );
            })
        }]);





