<?php
function getConnection()
{
    $config = json_decode(file_get_contents("http://localhost/configuration/name/database.json"), true);
    $connectString = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8', $config["database"], $config["port"], $config["schema"]);

    $db = new PDO($connectString, $config["user"], $config["password"]);
    return $db;
}

function addSuggestion($suggestion, $validation, $motivation)
{
    $query = "INSERT INTO suggestions VALUES(null, ?, ?, ?)";
    $db = getConnection();
    $stmt = $db->prepare($query);
    try {

        $stmt->execute(array($suggestion, $validation, $motivation));
    } catch (PDOException $ex) {
        //echo "An Error occurred!"; //user friendly message
        //echo $ex->getMessage();
        return array("error" => $ex->getMessage());
    }
    return array("error" => false);
}

function getSuggestions()
{
    $db = getConnection();
    $rows = $db->query('SELECT id, suggestion, verification, motivation, (select count(*) FROM votes where suggestionId = id) AS voteCount FROM suggestions;');
    $result = array();
    foreach ($rows as $row) {
        $result[] =
            array("id" => $row["id"],
                "suggestion" => $row["suggestion"],
                "verification" => $row["verification"],
                "motivation" => $row["motivation"],
                "voteCount" => intval($row["voteCount"]));
    }
    return $result;
}


function checkVotingRights($email)
{
    $q = "SELECT id FROM users WHERE email = ?";
    $db = getConnection();
    $s = $db->prepare($q);
    $s->execute(array($email));

    return $s->rowCount() == 1 ? "true" : "false";
}

function vote($id)
{
    session_start();
    $email = $_SESSION["token_data"]["payload"]["email"];
    if (!isset($email)) {
        echo "Not logged in!";
        die;
    }
    $q = "SELECT * FROM votes WHERE userId = (SELECT id FROM users WHERE email = ?) AND suggestionId =?";
    $db = getConnection();
    $s = $db->prepare($q);
    $s->execute(array($email, $id));

    if ($s->rowCount() == 0) {
        $q = "INSERT INTO votes (suggestionId, userId) VALUES ($id, (SELECT id FROM users WHERE email = ?))";
        $s = $db->prepare($q);
        $s->execute(array($email));
    } else {
        $q = "DELETE FROM votes WHERE userId  = (SELECT id FROM users WHERE email = ?) AND suggestionId =?";
        $s = $db->prepare($q);
        $s->execute(array($email, $id));
    }
    return array("error" => false);
}

function getVotes()
{
    $q = "SELECT votes.suggestionId, users.email FROM votes, users WHERE votes.userId = users.id;";
    $d = getConnection();
    $votes = $d->query($q);
    $result = array();
    while ($row = $votes->fetch(PDO::FETCH_ASSOC)) {
        $result[] = array("vote" => $row["suggestionId"], "email" => $row["email"]);
    }

    return $result;
}

if (isset($_GET["suggestion"])) {
    $suggestion = $_GET["suggestion"];
    $motivation = "";
    $verification = "";
    if (isset($_GET["motivation"])) {
        $motivation = $_GET["motivation"];
    }
    if (isset($verification)) {
        $verification = $_GET["verification"];
    } else {
        header("Content-Type: application/json;charset=utf-8");
        die(json_encode(array("error" => "Missing validation")));
    }
    header("Content-Type: application/json;charset=utf-8");
    echo json_encode(array("result" => addSuggestion($suggestion, $verification, $motivation)));
}

if (isset($_GET["list"])) {
    header("Content-Type: application/json;charset=utf-8");
    echo json_encode(getSuggestions());
}

if (isset($_GET["vote"])) {
    header("Content-Type: application/json;charset=utf-8");
    echo json_encode(vote(filter_var($_GET["id"], FILTER_SANITIZE_NUMBER_INT)));
}

if (isset($_GET["votes"])) {
    header("Content-Type: application/json;charset=utf-8");
    echo json_encode(getVotes());
}