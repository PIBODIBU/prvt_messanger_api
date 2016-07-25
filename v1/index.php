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
Flight::dbH()->query("SET NAMES utf8");

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

//get contact list
Flight::route('GET /contacts', function (){
    if(isset($_GET['token'])) {


        $token = $_GET['token'];

        $user = Flight::dbH()->getUser($token);

        if($user == NULL){
            error("such token doesn't exist");
        } else{
            $users = array();
            $users = Flight::dbH()->getAllUsersWithIgnore($user);

            Flight::json($users,200);
        }



    } else{
        error("value token missed");
    }



});

Flight::route('GET /my/chats', function () {
    verifyRequiredParams(array('token'));

    $my_rooms = array();

    $user = Flight::dbH()->getUser($_GET['token']);

    if ($user == NULL) {
        $response = array();
        $response['error'] = TRUE;
        $response['error_msg'] = 'Can\'t fetch user';
        Flight::json($response);
    }

    $user_id = $user['user_id'];

    // Get all chat relations
    $query = Flight::dbH()->query("SELECT * FROM chat_relations WHERE user_id='$user_id'");

    while ($row = $query->fetch_array(MYSQLI_ASSOC)) {
        $chat_id = $row['chat_id'];

        $query_chat = Flight::dbH()->query("SELECT * FROM chat_rooms WHERE chat_room_id='$chat_id'");
        $query_messages = Flight::dbH()->query("SELECT * FROM messages WHERE chat_room_id='$chat_id' ORDER BY message_id DESC");
        $query_participants = Flight::dbH()->query("SELECT * FROM chat_relations WHERE chat_id='$chat_id'");

        $last_message = $query_messages->fetch_assoc();
        $chat = $query_chat->fetch_assoc();

        $chat['participants_count'] = $query_participants->num_rows;
        while ($row = $query_participants->fetch_array(MYSQLI_ASSOC)) {
            if ($row['user_id'] != $user_id) {
                $user = Flight::dbH()->getUserById($row['user_id']);
                $chat['participants'][] = $user;
            }
        }
        $chat['last_message'] = $last_message;
        $my_rooms[] = $chat;
    }

    Flight::json($my_rooms, 200);
});

Flight::route('GET /chat/@id/messages', function ($chat_id) {
    $token = $_GET['token'];
    $response = array();

    verifyRequiredParams(array('token'));
    if (!isTokenValid($token)) {
        $response = array(
            'error' => true,
            'error_msg' => 'Bad token'
        );
        Flight::json($response, 400);
    }

    $user = Flight::dbH()->getUser($token);

    if (!Flight::dbH()->isItMyChat($user['user_id'], $chat_id)) {
        $response = array(
            'error' => true,
            'error_msg' => 'It is not your chat'
        );
        Flight::json($response, 400);
    }

    $query = Flight::dbH()->query("SELECT * FROM messages WHERE chat_room_id='$chat_id' ORDER BY created_at DESC");
    while ($message = $query->fetch_array(MYSQLI_ASSOC)) {
        $user = Flight::dbH()->getUserById($message['user_id'])['user_id'];
        $message['sender'] = $user;
        $response[] = $message;
    }


    echo '<head><meta charset="UTF-8"/></head>';
    echo '<pre>';
    print_r($response);
    echo '</pre>';

    echo '<br/>';
    echo '<br/>';
    echo '<br/>';
    echo '<br/>';

    //Flight::json($response, 200);
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

function isTokenValid($token)
{
    $query = Flight::dbH()->query("SELECT * FROM users WHERE BINARY token='$token'");
    $result = $query->fetch_assoc();
    return isset($result);
}

// error generation
function error($message){
    $response = array();
    $response['error'] = true;
    $response['message'] = $message;
    Flight::json($response,400);
}

/**
 * sending notifacation to firebase for
 * that @tokens
 * this @message
 * */
function send_notification($tokens, $message){
    $url = 'https://fcm.googleapis.com/fcm/send';

    $fields = array(
        'registration_ids' => $tokens,
        'data' => $message
    );

    $headers = array(
        'Authorization:key =
            ',
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);
    if($result === FALSE){
        die('Curl failed: '. curl_error($ch));
    }
    cubrid_close($ch);

    return $result;
}

Flight::start();












