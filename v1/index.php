<?php

error_reporting(-1);
ini_set('display_errors', 'On');

require_once '../include/db_handler.php';
require '../libs/flight/Flight.php';
require_once '../include/config.php';

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

// Register class with constructor parameters
Flight::register('db', 'mysqli', array(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME));

// Set encoding to UTF8
Flight::db()->query("SET NAMES utf8");

Flight::start();