<?php

error_reporting(-1);
ini_set('display_errors', 'On');

require_once '../include/db_handler.php';
require_once '../include/config.php';
require_once '../include/utils.php';
require '../libs/flight/Flight.php';
require_once '../libs/gcm/gcm.php';

// Register class with constructor parameters
Flight::register('db', 'mysqli', array(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME));
Flight::register('dbH', 'DBHandler');

// Set encoding to UTF8
Flight::db()->query("SET NAMES utf8");

// User login
Flight::route('POST /user/login', function () {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $token = generateToken();
    $response = array();

    verifyRequiredParams(array('name', 'phone'));

    $query = Flight::dbH()->query("SELECT * FROM users WHERE phone='$phone'");
    $user = $query->fetch_assoc();

    if (isset($user)) {
        // Пользовательно авторизовался ранее

        $response['error'] = false;
        $response['user'] = $user;
    } else {
        // Пользовательно авторизуется впервые

        // Adding new user to Db
        $query = Flight::dbH()->query("INSERT INTO users(token, name, email, phone, gcm_registration_id) VALUES ('$token', '$name','','$phone','')");

        if ($query) {
            // User added
            $user = Flight::dbH()->getUser($token);

            $response['error'] = false;
            $response['user'] = $user;
        } else {
            // Error occurred during SQL query

            $response['error'] = true;
            $response['error_msg'] = 'Error occurred during login';
        }
    }

    Flight::json($response, 200);
});

Flight::route('GET /notification', function () {
    $gcm = new GCM();

    $response = $gcm->send('cJLZVeKxHIo:APA91bEip8YnqqI7Uk1gMpEXI-chtAKvlcInD7Krzc2mvtHDi5eUMhSaYp4dLO2ybG2ogY4bRm6kivHpVVZf9Mt1Cmp9HVGS15nvmY4tfo2Y2TsfpT4aXNF8mSmkDKOg45YUEKWXgvel', array('text', 'FROM FLIGHT'));

    Flight::json($response);
});

/* * *
 * Updating user
 *  we use this url to update user's gcm registration id
 */
Flight::route('POST /my/gcm/id/update', function () {
    verifyRequiredParams(array('gcm_registration_id', 'token'));

    $gcm_registration_id = $_POST['gcm_registration_id'];
    $token = $_POST['token'];

    $db = new DbHandler();
    $response = $db->updateGcmID($token, $gcm_registration_id);

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

        $query_chat = Flight::dbH()->query("SELECT * FROM chat_rooms WHERE chat_room_id='$chat_id'");
        $query_messages = Flight::dbH()->query("SELECT * FROM messages WHERE chat_room_id='$chat_id' ORDER BY message_id DESC");

        $last_message = $query_messages->fetch_assoc();
        $chat = $query_chat->fetch_assoc();

        $chat['last_message'] = $last_message;
        $my_rooms[] = $chat;
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