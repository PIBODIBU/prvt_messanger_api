<?php

error_reporting(-1);
ini_set('display_errors', 'On');

require_once '../include/db_handler.php';
require '../libs/flight/Flight.php';
require_once '../include/config.php';

// Register class with constructor parameters
Flight::register('db', 'mysqli', array(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME));

// Set encoding to UTF8
Flight::db()->query("SET NAMES utf8");


// User login
Flight::route('/user/login', function () {

});

/* * *
 * Updating user
 *  we use this url to update user's gcm registration id
 */
Flight::route('POST /user/@id', function ($id) {
    verifyRequiredParams(array('gcm_registration_id'));

    $gcm_registration_id = $_POST['gcm_registration_id'];

    $db = new DbHandler();
    $response = $db->updateGcmID($id, $gcm_registration_id);

    Flight::json($response, 200);
});

Flight::route('GET /my/chats', function () {
    verifyRequiredParams(array('token'));

    $db = new DbHandler();
    $my_rooms = array();

    $user = $db->getUser($_GET['token']);

    if ($user == NULL) {
        $response = array();
        $response['error'] = TRUE;
        $response['error_msg'] = 'Can\'t fetch user';
        Flight::json($response);
    }

    $user_id = $user['user_id'];

    // Get all chat relations
    $query = $db->getConn()->query("SELECT * FROM chat_relations WHERE user_id='$user_id'");

    while ($row = $query->fetch_array(MYSQLI_ASSOC)) {
        $chat_id = $row['chat_id'];
        $query_chat = $db->getConn()->query("SELECT * FROM chat_rooms WHERE chat_room_id='$chat_id'");
        $my_rooms[] = $query_chat->fetch_assoc();
    }

    Flight::json($my_rooms, 200);
});

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields)
{
    $error = false;
    $error_fields = "";
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        parse_str(file_get_contents('php://input'), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        Flight::json($response, 400);
    }
}

/**
 * Validating email address
 */
function validateEmail($email)
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        Flight::json($response, 400);
    }
}

Flight::start();