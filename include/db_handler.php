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
    public function updateGcmID($token, $gcm_registration_id)
    {
        $response = array();
        $query = $this->conn->query("UPDATE users SET gcm_registration_id = '$gcm_registration_id' WHERE BINARY token = '$token'");

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

    public function getUserByToken($token)
    {
        $query = $this->conn->query("SELECT * FROM users WHERE BINARY token='$token'");
        $user = $query->fetch_assoc();
        return isset($user) ? $user : NULL;
    }

    public function getUserById($id)
    {
        $query = $this->conn->query("SELECT * FROM users WHERE user_id='$id'");
        $user = $query->fetch_assoc();
        return $user;
    }

    public function getChatById($id)
    {
        $query = $this->conn->query("SELECT * FROM chat_rooms WHERE chat_room_id='$id'");
        $user = $query->fetch_assoc();
        return $user;
    }

    public function isItMyChat($user_id, $chat_id)
    {
        $query = $this->query("SELECT * FROM chat_relations WHERE user_id='$user_id' AND chat_id='$chat_id'");
        $result = $query->fetch_assoc();
        return isset($result);
    }

    public function query($sql)
    {
        return $this->getConn()->query($sql);
    }
}