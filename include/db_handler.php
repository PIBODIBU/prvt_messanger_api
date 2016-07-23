<?php

/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Ravi Tamada
 */
class DbHandler
{
    private $conn;

    function __construct()
    {
        require_once dirname(__FILE__) . '/db_connect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    /**
     * @return database
     */
    public function getConn()
    {
        return $this->conn;
    }

    // updating user GCM registration ID
    public function updateGcmID($user_id, $gcm_registration_id)
    {
        $response = array();
        $query = $this->conn->query("UPDATE users SET gcm_registration_id = '$gcm_registration_id' WHERE user_id = '$user_id'");

        if ($query) {
            // User successfully updated
            $response["error"] = false;
            $response["message"] = 'GCM registration ID updated successfully';
        } else {
            // Failed to update user
            $response["error"] = true;
            $response["message"] = "Failed to update GCM registration ID";
        }

        return $response;
    }

    public function getUser($token)
    {
        $query = $this->conn->query("SELECT * FROM users WHERE BINARY token='$token'");
        $user = $query->fetch_assoc();
        return isset($user) ? $user : NULL;
    }
}