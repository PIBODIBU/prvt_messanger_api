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


Flight::route('GET /test', function () {
    /*
    $id = 1;
    $message = 'kaka';
    $author = 'pa1uuNuophuo2eechaix';

    Flight::dbH()->save_message($id,$message,$author);
*/

    $some = array();
    $some = Flight::dbH()->get_users_from_chatrooms_with_ignore(1, "9fcAVbPOgLKO10qqoWcx");
    print_r($some);
});


// User login
Flight::route('POST /user/login', function () {
    $name = $_POST['name'];
    $phone = $_POST['phone'];


    // Adding new user to Db
    $token_generated = false;
    while ($token_generated == false) {
        $token = generateToken(20);
        if (Flight::dbH()->is_token_occupied($token)) {
            continue;
        } else {
            $token_generated = true;
        }
    }


    $response = array();
    $error = array();

    verifyRequiredParams(array('name', 'phone'));

    $query = Flight::dbH()->query("SELECT * FROM users WHERE phone='$phone'");
    $user = $query->fetch_assoc();

    if (isset($user)) {
        // Пользовательно авторизовался ранее
        if ($user['name'] != $name) {
            $query = Flight::dbH()->query("UPDATE users SET name='$name' WHERE phone='$phone'");
        }
        $query = Flight::dbH()->query("UPDATE users SET token='$token' WHERE phone='$phone'");

        $query = Flight::dbH()->query("SELECT * FROM users WHERE phone='$phone'");
        $user = $query->fetch_assoc();

        $error['error'] = false;
        $error['error_msg'] = "";
        $response['user'] = $user;
    } else {
        // Пользователь авторизуется впервые

        //echo $token;

        $query = Flight::dbH()->query("INSERT INTO users (token,name,phone) VALUES ('$token','$name','$phone')");;

        if ($query) {
            // User added
            $user = Flight::dbH()->getUser($token);
            $error['error'] = false;
            $error['error_msg'] = "";
            $response['user'] = $user;
        } else {
            // Error occurred during SQL query

            $error['error'] = true;
            $error['error_msg'] = 'Error occurred during login';
        }
    }

    $response['error'] = $error;

    Flight::json($response, 200);
});


Flight::route('POST /my/gcm/id/update', function () {
    $token = $_POST['token'];
    $gcm_registration_id = $_POST['gcm'];

    Flight::dbH()->query("UPDATE users SET gcm_registration_id='$gcm_registration_id' WHERE users.token='$token'");
});


//get contact list
Flight::route('GET /contacts', function () {
    if (isset($_GET['token'])) {


        $token = $_GET['token'];

        $user = Flight::dbH()->getUser($token);

        if ($user == NULL) {
            error("such token doesn't exist");
        } else {
            $users = array();
            $users = Flight::dbH()->getAllUsersWithIgnore($user);

            Flight::json($users, 200);
        }


    } else {
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
    $offset = $_GET['offset'];
    $limit = $_GET['limit'];
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

    $query = Flight::dbH()->query("SELECT * FROM messages WHERE chat_room_id='$chat_id' ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

    while ($message = $query->fetch_assoc()) {
        if ($message) {
            $user = Flight::dbH()->getUserById($message['user_id']);
            $message['sender'] = $user;
            $response[] = $message;
        }
    }


    /**
     * echo '<head><meta charset="UTF-8"/></head>';
     * echo '<pre>';
     * print_r($response);
     * echo '</pre>';
     *
     * echo '<br/>';
     * echo '<br/>';
     * echo '<br/>';
     * echo '<br/>';
     */
    Flight::json($response, 200);
});


/**
 * Выход пользователя из аккаунта
 */

Flight::route('POST /user/logout', function () {
    $token = $_POST['token'];
    $response = array();

    verifyRequiredParams(array('token'));

    $query = Flight::dbH()->query("UPDATE users SET gcm_registration_id='' WHERE BINARY token='$token'");

    if ($query) {
        $response['error'] = false;
        $response['error_msg'] = "";
    } else {
        $response['error'] = true;
        $response['error_msg'] = "failed to logout";
    }


    Flight::json($response, 200);
});


/**
 * Получение пользователей чата
 */

Flight::route('GET /chat/@id/users', function ($chat_id) {

    $query = Flight::dbH()->query("SELECT user_id FROM chat_relations WHERE chat_id='$chat_id'");
    while ($row = $query->fetch_assoc()) {
        $result[] = $row;
    }

    Flight::json($result, 200);
});


/**
 * Удаление диалога
 */

Flight::route('POST /chat/@id/delete', function ($chat_id) {

    $query = Flight::dbH()->query("DELETE * FROM chat_relations WHERE chat_id='$chat_id'");
    $query = Flight::dbH()->query("DELETE * FROM chat_rooms WHERE chat_room_id='$chat_id'");

});


/**
 * Когда пользователь начинает общение впервые,
 * создается новая комната
 */
Flight::route('POST /chat/create', function () {

    $json = json_decode(file_get_contents('php://input'), true);

    $chat_name = $json['chat_name'];
    $user_list_id = array();

    foreach ($json['user_ids'] as $id) {
        $user_list_id[] = $id['user_id'];
    }

    //создать комнату в базе
    $query = Flight::dbH()->query("INSERT INTO chat_rooms (name) VALUES ('$chat_name')");

    //Получить айди комнаты чата
    $query = Flight::dbH()->query("SELECT LAST_INSERT_ID() FROM chat_rooms");
    $chat_room_id = $query->fetch_assoc()['LAST_INSERT_ID()'];

    foreach ($user_list_id as $id) {
        $query = Flight::dbH()->query("INSERT INTO chat_relations (chat_id, user_id) VALUES ('$chat_room_id','$id')");
    }


    //сгенерировать ответ

    $query = Flight::dbH()->query("SELECT * FROM chat_rooms WHERE chat_room_id='$chat_room_id'");

    $response = array();
    $response['chat_room_id'] = $chat_room_id;
    $response['name'] = $chat_name;
    $response['created_at'] = $query->fetch_assoc()['created_at'];
    $response['last_message'] = $query->fetch_assoc();
    $response['participants_count'] = count($user_list_id);
    $response['participants'] = array();


    foreach ($user_list_id as $user) {
        $participant = array();

        $query = Flight::dbH()->query("SELECT * FROM users WHERE user_id='$user'");
        $result = $query->fetch_assoc();

        $participant['user_id'] = $user;
        $participant['name'] = $result['name'];
        $participant['phone'] = $result['phone'];
        $participant['email'] = $result['email'];

        array_push($response['participants'], $participant);
    }

    Flight::json($response, 200);
});


/**
 * Когда пользоветель отправляет новое сообщение,
 * функция сохраняет его в базу и отправляет, кому нужно
 */
Flight::route('GET /chat/@id/on_message', function ($chat_id) {

    $message = $_GET['message'];
    $author = $_GET['token'];

    $response = Flight::dbH()->save_message_and_return_for_notification($chat_id, $message, $author);

    $tokens = array();
    $tokens = Flight::dbH()->get_users_from_chatrooms_with_ignore($chat_id, $author);

    $response_for_recipient = array();
    $response_for_recipient = $response;
    unset($response_for_recipient['error']);

    send_notification($tokens, $response_for_recipient);


    Flight::json($response, 200);

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
function error($message)
{
    $response = array();
    $response['error'] = true;
    $response['message'] = $message;
    Flight::json($response, 400);
}

/**
 * sending notifacation to firebase for
 * that @tokens
 * this @message
 * */
function send_notification($tokens, $message)
{
    $url = 'https://fcm.googleapis.com/fcm/send';

    $fields = array(
        'registration_ids' => $tokens,
        'data' => $message
    );

    $headers = array(
        'Authorization:key = AIzaSyC-u_9DBEZjBUeNZBFuxXF19vmIopNFRhs',
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
    if ($result === FALSE) {
        die('Curl failed: ' . curl_error($ch));
    }
    curl_close($ch);

    return $result;
}

Flight::start();












