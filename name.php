<?php
require 'vendor/autoload.php';
session_start();
$client = new Google_Client();
$client->setAuthConfigFile("http://localhost/configuration/name/google-credentials.json");
$client->addScope("https://www.googleapis.com/auth/userinfo.profile");
$client->addScope("https://www.googleapis.com/auth/userinfo.email");
$client->setAccessType('offline');
$client->setApprovalPrompt('auto');
$client->setRedirectUri('postmessage');

if (function_exists('setProxy')) {
    $client->getIo()->setOptions(array(
        CURLOPT_PROXY => 'localhost',
        CURLOPT_PROXYPORT => 8888
    ));
}


if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    include_once 'php/name.php';
    $client->setAccessToken($_SESSION['access_token']);
    try {

        if (!isset($_SESSION['token_data'])) {
            $token_data = $client->verifyIdToken()->getAttributes();
            if ($token_data["payload"]["email_verified"]) {
                $_SESSION['token_data'] = $token_data;
            }
        }
        $_SESSION['canVote'] = checkVotingRights($_SESSION["token_data"]["payload"]["email"]);

        $oAuth2 = new \Google_Service_Oauth2($client);
        $oAttr = $oAuth2->userinfo->get();


    } catch (Exception $e) {
        unset($_SESSION['access_token']);
        $redirect_uri = $_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER['HTTP_HOST'];
        header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
    }
}


if (isset($_GET['logout'])) {
    // Need to proxy if needed.
    if (function_exists('setProxy')) {
        setProxy();
        $client->getIo()->setOptions(array(
            CURLOPT_PROXY => 'localhost',
            CURLOPT_PROXYPORT => 8888
        ));

    }
    $client->revokeToken($_SESSION['access_token']);
    unset($_SESSION['access_token']);
    $_SESSION = array();
    $redirect_uri = $_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER['HTTP_HOST'];
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
    die;
}

/** Send the user for authentication with Google
 * will call us again with ?code set.*/
if (isset($_GET['login'])) {
    $client->setRedirectUri($_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER['HTTP_HOST']);
    $client->addScope("https://www.googleapis.com/auth/userinfo.profile");
    $client->addScope("https://www.googleapis.com/auth/userinfo.email");


    // Need to proxy if needed.
    if (function_exists('setProxy')) {
        $client->getIo()->setOptions(array(
            CURLOPT_PROXY => 'localhost',
            CURLOPT_PROXYPORT => 8888
        ));
        setProxy();
    }
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
}

/** Called when authenticated with google. Will take the user to the
 * normal page */
if (isset($_GET['code'])) {
    $client->setRedirectUri($_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER['HTTP_HOST']);
    error_log($_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER['HTTP_HOST']);
    try {
        $client->authenticate($_GET['code']);
    } catch(Exception  $e) {
        var_dump($e); die;
    }


    $_SESSION['access_token'] = $client->getAccessToken();
    $token_data = $client->verifyIdToken()->getAttributes();

    $redirect_uri = $_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER['HTTP_HOST'];
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));

}
?>

<!DOCTYPE html>
<!--suppress ALL -->
<html data-ng-app="nameApp">
<head lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

    <link href="./css/3pp/bootstrap.min.css" rel="stylesheet"/>
    <link href="./css/3pp/bootstrap-theme.min.css" rel="stylesheet"/>
    <link href="./css/3pp/font-awesome.min.css" rel="stylesheet"/>
    <link href="./css/3pp/bootstrap-social.css" rel="stylesheet"/>
    <link rel="stylesheet" href="./css/3pp/angular-material.min.css">
    <link rel="stylesheet" href="./css/fonts/roboto.css">

    <!-- Must load this as last! -->
    <!-- link href="./css/admin.css" rel="stylesheet"/-->
    <!-- inject:css -->

    <link rel="stylesheet" href="/css/name.css">

    <!-- endinject -->
    <title>Name competition (&alpha;)</title>
</head>
<body>
<nav class="navbar navbar-default navbar-fixed-top navbar-inverse">
    <div class="container-fluid" data-ng-controller="LoginController">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse"
                    data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#home">DITS Name competition (&alpha;)</a>
        </div>

        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav navbar-right">
                <li><a class="" data-ng-click="disconnect()" data-ng-show="loggedIn">
                        <i class="fa fa-google adjust-google"></i>Log out
                    </a>
                </li>
                <li><a class="" data-ng-click="login()" data-ng-show="!loggedIn">
                        <i class="fa fa-google adjust-google"></i>Log in
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container">
    <div class="row">
        <div class="col-md-3"></div>
        <div class="col-xs-12 col-md-6">
            <div class="container" data-ng-controller="LoginController">
                <div class="row">
                    <div class="col-sm-3 col-centered">
                        <a class="btn btn-social btn-google" data-ng-click="login()" data-ng-show="!loggedin">
                            <i class="fa fa-google"></i>
                            Sign in with Google
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div data-ng-controller="nameController">
        <div class="row">
            <div class="col-md-3"></div>
            <div class="col-xs-12 col-md-6">
                <h1>DITS name competition (&alpha;)</h1>

                <p>This is a competition to select a name for DITS. Anyone can suggest but only board members (with spouses) can
                    vote.</p>
                <p><b>Please read instructions carefully!</b></p>

                <h2 class=""
                    data-ng-click="toogleInstructions()">Instructions
                <span class="glyphicon clickable hideicon"
                      data-ng-class="{ 'glyphicon-chevron-up' : hideInstructions, 'glyphicon-chevron-down' : !hideInstructions}">
                </span>
                </h2>

                <div data-ng-show="!hideInstructions">
                    <ol>
                        <li>Select a pass phrase, about 10 or twenty characters, what ever you like but please remember
                            it. It will be used to verify that it is you who made the suggestion. Keep it a secret.
                        </li>
                        <li>Enter a suggestion, this will be stored together with the encrypted (AES-256) version, encrypted
                        with you pass phrase, a pass phrase that only you should know.</li>
                        <li>Check back in later and vote, you can vote for as many as you want, but you can only cast
                            one vote per suggestion
                        </li>
                        <li>You will currently need to reload the page to see changes from other users.
                        </li>
                        <li>To be able to vote you have to login using your Google account. Do so in the menu.</li>
                    </ol>
                    <form class="" name="passForm">
                        <div class="form-group has-feedback">
                            <label for="passphrase" class="control-label">Pass phrase

                            </label>

                    <span class="glyphicon clickable hideicon"
                          data-ng-click="togglePassphrase()"
                          data-ng-class="{ 'glyphicon-eye-open' : showPass == 'password', 'glyphicon-eye-close' : showPass == 'text'}">
                    </span>

                            <input id="passphrase"
                                   class="form-control"
                                   type="{{showPass}}"
                                   name="passphrase"
                                   data-ng-model="passphrase"
                                   placeholder="Enter you pass phrase here">
                        </div>
                        <div class="form-group has-feedback">
                            <button type="button" class="btn btn-default"
                                    data-ng-class="{'disabled' : ! passForm.$valid || !passForm.$dirty }"
                                    data-ng-click="saveCookie()">
                                Save
                            </button>
                        </div>

                    </form>
                </div>
                <h2>Suggest</h2>

                <form class="" name="suggestionForm">
                    <div class="form-group has-feedback"
                         data-ng-class="{ 'has-error' : !suggestionForm.suggestion.$valid && suggestionForm.suggestion.$dirty, 'has-success' : suggestionForm.suggestion.$valid && suggestionFrom.suggestion.$dirty}">
                        <label for="suggestion" class="control-label">Name suggestion</label>
                        <input id="suggestion"
                               class="form-control"
                               type="text"
                               name="suggestion"
                               data-ng-model="suggestion"
                               placeholder="Your name suggestion..."
                               required>

                    <span class="glyphicon form-control-feedback"
                          aria-hidden="true"
                          data-ng-class="{ 'glyphicon-ok' : suggestionForm.$valid, 'glyphicon-cancel' : suggestionForm.$invald && suggestionForm.$dirty}">
                    </span>
                    </div>
                    <div class="form-group">
                        <label for="motivation" class="control-label">Motivation</label>
                    <textarea id="motivation"
                              class="form-control"
                              type="text"
                              name="motivation"
                              data-ng-model="motivation"
                              rows="3"
                              placeholder="Write a snappy motivation for this name"></textarea>
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-default"
                                data-ng-class="{'disabled' : ! suggestionForm.$valid || !suggestionForm.$dirty }"
                                data-ng-click="submitSuggestion()">
                            Submit
                        </button>
                    </div>
                </form>
                <h2>Suggestions</h2>

                <div id="suggestions list-goup">

                    <div class="row suggestionItem list-group-item list-group-item-info"
                    ">
                    <div class="col-xs-10 col-md-10">
                        <div class="suggestion">
                            Name Suggestion
                        </div>
                        <div class="motivation">Motivation</div>
                    </div>
                    <div class="vote col-xs-2 col-md-2">
                        <div class="vote glyphicon glyphicon-thumbs-up clickable"
                             data-ng-click="vote(suggestion.id)"></div>
                        <div class="votecount">#</div>
                    </div>
                </div>
                <div class="row suggestionItem list-group-item" data-ng-repeat="suggestion in suggestions | orderBy: 'voteCount': 'reverse'"
                     data-ng-class="{'list-group-item-warning' : suggestion.userSuggestion}">
                    <div class="col-xs-10 col-md-10">
                        <div class="suggestion">
                            {{suggestion.suggestion}}
                        </div>
                        <div class="motivation" data-ng-show="suggestion.motivation">{{suggestion.motivation}}</div>
                        <div class="motivation" data-ng-show="!suggestion.motivation">Motivation not provided</div>
                    </div>
                    <div class="vote col-xs-2 col-md-2">
                        <div class="vote glyphicon clickable"
                             data-ng-class="{'glyphicon-thumbs-up' : !suggestion.voted, 'glyphicon-thumbs-down' : suggestion.voted }"
                             data-ng-click="vote(suggestion.id)"></div>
                        <div class="votecount">{{suggestion.voteCount}}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="http://crypto-js.googlecode.com/svn/tags/3.1.2/build/rollups/aes.js"></script>
<script src="javascript/3pp/angular.min.js"></script>
<script src="javascript/3pp/angular-route.min.js"></script>
<script src="javascript/3pp/angular-cookies.min.js"></script>
<script src="javascript/3pp/jquery-1.11.2.min.js"></script>
<script src="javascript/3pp/bootstrap.min.js"></script>
<script src="javascript/3pp/ui-bootstrap-tpls-0.13.0.min.js"></script>
<script src="javascript/name.js"></script>
<script>
    var userService = nameApp.service('userService', function () {
        var userId = "<?php echo isset( $_SESSION['token_data']) ? $_SESSION["token_data"]["payload"]["email"] : ''?>",
            canVote = <?php echo isset( $_SESSION['canVote']) ? $_SESSION['canVote'] : "false" ?>;
        return {
            userId: userId,
            canVote: canVote
        }
    });
</script>
</body>
</html>
