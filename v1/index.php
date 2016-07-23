<?php

error_reporting(-1);
ini_set('display_errors', 'On');

require_once '../include/db_handler.php';
require '../libs/flight/Flight.php';
require_once '../include/config.php';

// User login
Flight::route('/user/login', function () {
    // check for required params
    verifyRequiredParams(array('name', 'email'));

    // reading post params
    $name = $_POST['name'];
    $email = $_POST['email'];

    // validating email address
    validateEmail($email);

    $db = new DbHandler();
    $response = $db->createUser($name, $email);

    // echo json response
    Flight::json($response, 200);
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

/* * *
 * fetching all chat rooms
 */
Flight::route('GET /chat_rooms', function () {
    $response = array();
    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->getAllChatrooms();

    $response["error"] = false;
    $response["chat_rooms"] = array();

    // pushing single chat room into array
    while ($chat_room = $result->fetch_assoc()) {
        $tmp = array();
        $tmp["chat_room_id"] = $chat_room["chat_room_id"];
        $tmp["name"] = $chat_room["name"];
        $tmp["created_at"] = $chat_room["created_at"];
        array_push($response["chat_rooms"], $tmp);
    }

    Flight::json($response, 200);
});

/**
 * Messaging in a chat room
 * Will send push notification using Topic Messaging
 *  */
Flight::route('POST /chat_rooms/@chat_room_id/message', function ($chat_room_id) {
    $db = new DbHandler();

    verifyRequiredParams(array('user_id', 'message'));

    $user_id = $_POST['user_id'];
    $message = $_POST['message'];

    $response = $db->addMessage($user_id, $chat_room_id, $message);

    if ($response['error'] == false) {
        require_once __DIR__ . '/../libs/gcm/gcm.php';
        require_once __DIR__ . '/../libs/gcm/push.php';
        $gcm = new GCM();
        $push = new Push();

        // get the user using userid
        $user = $db->getUser($user_id);

        $data = array();
        $data['user'] = $user;
        $data['message'] = $response['message'];
        $data['chat_room_id'] = $chat_room_id;

        $push->setTitle("Private messenger");
        $push->setIsBackground(FALSE);
        $push->setFlag(PUSH_FLAG_CHATROOM);
        $push->setData($data);

        // echo json_encode($push->getPush());exit;

        // sending push message to a topic
        $gcm->sendToTopic('topic_' . $chat_room_id, $push->getPush());

        $response['user'] = $user;
        $response['error'] = false;
    }

    Flight::json($response, 200);
});

Flight::route('GET /push/test', function () {
    require_once __DIR__ . '/../libs/gcm/gcm.php';
    require_once __DIR__ . '/../libs/gcm/push.php';
    $gcm = new GCM();
    $push = new Push();
    $db = new DbHandler();
    $ids = array();

    // get the user using userid
    $user = $db->getUser(1);

    array_push($ids, $user['gcm_registration_id']);
    array_push($ids, 'derc-a9bhQM:APA91bEleA1ImL0DCbxFqez2m9OdioAAr-_-Wti2_2ZcAR_KhZFTRtRw91hvQtSkziop1X3P3oAm-vsPrhMP87JXymYA54TMA3t17JtW_cAWLfNiIT-wgdQzAWtq3xm2XMfTNKo0YrfF');

    $data = array();
    $data['user'] = $user;
    $data['message'] = 'This is test mssage';
    $data['chat_room_id'] = 1;

    $push->setTitle("Private messenger");
    $push->setIsBackground(FALSE);
    $push->setFlag(PUSH_FLAG_CHATROOM);
    $push->setData($data);

    $url = 'https://android.googleapis.com/gcm/send';
    $fields = array(
        'registration_ids' => $ids,
        'data' => $push->getPush(),
    );

    $headers = array(
        'Authorization:key=' . GOOGLE_API_KEY,
        'Content-Type: application/json'
    );
    echo json_encode($fields);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

    $result = curl_exec($ch);
    $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($result === false)
        die('Curl failed ' . curl_error($ch));

    curl_close($ch);

    /* $data = array();
     $data['user'] = $user;
     $data['message'] = 'This is test mssage';
     $data['chat_room_id'] = 1;
 
     $push->setTitle("Private messenger");
     $push->setIsBackground(FALSE);
     $push->setFlag(PUSH_FLAG_CHATROOM);
     $push->setData($data);
 
     // echo json_encode($push->getPush());exit;
 
     // sending push message to a topic
     $gcm->send($user['gcm_registration_id'], $push->getPush());
 
     $response['user'] = $user;
     $response['error'] = false;
     $response['result'] = $gcm;*/

    echo $response;
});

/**
 * Sending push notification to a single user
 * We use user's gcm registration id to send the message
 * * */
Flight::route('POST /users/@to_user_id/message', function ($to_user_id) {
    $db = new DbHandler();

    verifyRequiredParams(array('message'));

    $from_user_id = $_POST['user_id'];
    $message = $_POST['message'];

    $response = $db->addMessage($from_user_id, $to_user_id, $message);

    if ($response['error'] == false) {
        require_once __DIR__ . '/../libs/gcm/gcm.php';
        require_once __DIR__ . '/../libs/gcm/push.php';
        $gcm = new GCM();
        $push = new Push();

        $user = $db->getUser($to_user_id);

        $data = array();
        $data['user'] = $user;
        $data['message'] = $response['message'];
        $data['image'] = '';

        $push->setTitle("Google Cloud Messaging");
        $push->setIsBackground(FALSE);
        $push->setFlag(PUSH_FLAG_USER);
        $push->setData($data);

        // sending push message to single user
        $gcm->send($user['gcm_registration_id'], $push->getPush());

        $response['user'] = $user;
        $response['error'] = false;
    }

    Flight::json($response, 200);
});


/**
 * Sending push notification to multiple users
 * We use gcm registration ids to send notification message
 * At max you can send message to 1000 recipients
 * * */
Flight::route('POST /users/message', function () {

    $response = array();
    verifyRequiredParams(array('user_id', 'to', 'message'));

    require_once __DIR__ . '/../libs/gcm/gcm.php';
    require_once __DIR__ . '/../libs/gcm/push.php';

    $db = new DbHandler();

    $user_id = $_POST['user_id'];
    $to_user_ids = array_filter(explode(',', $_POST['to']));
    $message = $_POST['message'];

    $user = $db->getUser($user_id);
    $users = $db->getUsers($to_user_ids);

    $registration_ids = array();

    // preparing gcm registration ids array
    foreach ($users as $u) {
        array_push($registration_ids, $u['gcm_registration_id']);
    }

    // insert messages in db
    // send push to multiple users
    $gcm = new GCM();
    $push = new Push();

    // creating tmp message, skipping database insertion
    $msg = array();
    $msg['message'] = $message;
    $msg['message_id'] = '';
    $msg['chat_room_id'] = '';
    $msg['created_at'] = date('Y-m-d G:i:s');

    $data = array();
    $data['user'] = $user;
    $data['message'] = $msg;
    $data['image'] = '';

    $push->setTitle("Google Cloud Messaging");
    $push->setIsBackground(FALSE);
    $push->setFlag(PUSH_FLAG_USER);
    $push->setData($data);

    // sending push message to multiple users
    $gcm->sendMultiple($registration_ids, $push->getPush());

    $response['error'] = false;

    Flight::json($response, 200);
});

Flight::route('POST /users/send_to_all', function () {

    $response = array();
    verifyRequiredParams(array('user_id', 'message'));

    require_once __DIR__ . '/../libs/gcm/gcm.php';
    require_once __DIR__ . '/../libs/gcm/push.php';

    $db = new DbHandler();

    $user_id = $_POST['user_id'];
    $message = $_POST['message'];

    require_once __DIR__ . '/../libs/gcm/gcm.php';
    require_once __DIR__ . '/../libs/gcm/push.php';
    $gcm = new GCM();
    $push = new Push();

    // get the user using userid
    $user = $db->getUser($user_id);

    // creating tmp message, skipping database insertion
    $msg = array();
    $msg['message'] = $message;
    $msg['message_id'] = '';
    $msg['chat_room_id'] = '';
    $msg['created_at'] = date('Y-m-d G:i:s');

    $data = array();
    $data['user'] = $user;
    $data['message'] = $msg;
    $data['image'] = 'http://www.androidhive.info/wp-content/uploads/2016/01/Air-1.png';

    $push->setTitle("Google Cloud Messaging");
    $push->setIsBackground(FALSE);
    $push->setFlag(PUSH_FLAG_USER);
    $push->setData($data);

    // sending message to topic `global`
    // On the device every user should subscribe to `global` topic
    $gcm->sendToTopic('global', $push->getPush());

    $response['user'] = $user;
    $response['error'] = false;

    Flight::json($response, 200);
});

/**
 * Fetching single chat room including all the chat messages
 *  */
Flight::route('GET /chat_rooms/@chat_room_id', function ($chat_room_id) {
    $db = new DbHandler();

    $result = $db->getChatRoom($chat_room_id);


    $response["error"] = false;
    $response["messages"] = array();
    $response['chat_room'] = array();

    $i = 0;
    // looping through result and preparing tasks array
    while ($chat_room = $result->fetch_array(MYSQLI_ASSOC)) {
        // adding chat room node
        if ($i == 0) {
            $tmp = array();
            $tmp["chat_room_id"] = $chat_room["chat_room_id"];
            $tmp["name"] = $chat_room["name"];
            $tmp["created_at"] = $chat_room["chat_room_created_at"];
            $response['chat_room'] = $tmp;
        }

        if ($chat_room['user_id'] != NULL) {
            // message node
            $cmt = array();
            $cmt["message"] = $chat_room["message"];
            $cmt["message_id"] = $chat_room["message_id"];
            $cmt["created_at"] = $chat_room["created_at"];

            // user node
            $user = array();
            $user['user_id'] = $chat_room['user_id'];
            $user['username'] = $chat_room['username'];
            $cmt['user'] = $user;

            array_push($response["messages"], $cmt);
        }
    }

    Flight::json($response, 200);
});

Flight::route('GET /info', function () {
    phpinfo();
});

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields)
{
    $error = false;
    $error_fields = "";
    $request_params = array();
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

function IsNullOrEmptyString($str)
{
    return (!isset($str) || trim($str) === '');
}

// Register class with constructor parameters
Flight::register('db', 'mysqli', array(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME));

// Set encoding to UTF8
Flight::db()->query("SET NAMES utf8");

// Mapping method for sending error status in JSON format
Flight::map('echoRespnse', function ($error = TRUE, $error_msg = '') {
    $array = array("error" => $error);

    if ($error_msg != '')
        $array["error_msg"] = $error_msg;

    Flight::json($array);
});

Flight::start();