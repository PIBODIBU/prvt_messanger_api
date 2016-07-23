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

    // creating new user if not existed
    public function createUser($name, $email)
    {
        $response = array();

        // First check if user already existed in db
        if (!$this->isUserExists($email)) {
            // insert query
            $result = $this->conn->query("INSERT INTO users(name, email) VALUES('$name','$email')");

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                $response["error"] = false;
                $response["user"] = $this->getUserByEmail($email);
            } else {
                // Failed to create user
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registering";
            }
        } else {
            // User with same email already existed in the db
            $response["error"] = false;
            $response["user"] = $this->getUserByEmail($email);
        }

        return $response;
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

    // fetching single user by id
    public function getUser($user_id)
    {
        $query = $this->conn->query("SELECT user_id, name, email, gcm_registration_id, created_at FROM users WHERE user_id = '$user_id'");
        $result = $query->fetch_assoc();

        if (isset($result)) {
            return $result;
        } else {
            return NULL;
        }
    }

    // fetching multiple users by ids
    public function getUsers($user_ids)
    {

        $users = array();
        if (sizeof($user_ids) > 0) {
            $query = "SELECT user_id, name, email, gcm_registration_id, created_at FROM users WHERE user_id IN (";

            foreach ($user_ids as $user_id) {
                $query .= $user_id . ',';
            }

            $query = substr($query, 0, strlen($query) - 1);
            $query .= ')';

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($user = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["user_id"] = $user['user_id'];
                $tmp["name"] = $user['name'];
                $tmp["email"] = $user['email'];
                $tmp["gcm_registration_id"] = $user['gcm_registration_id'];
                $tmp["created_at"] = $user['created_at'];
                array_push($users, $tmp);
            }
        }

        return $users;
    }

    // messaging in a chat room / to personal message
    public function addMessage($user_id, $chat_room_id, $message)
    {
        $response = array();

        $query = $this->conn->query("INSERT INTO messages (chat_room_id, user_id, message) VALUES('$chat_room_id','$user_id','$message')");
        $message_id = $this->conn->insert_id;

        if ($query) {
            $response['error'] = false;

            // get the message
            $query = $this->conn->query("SELECT message_id, user_id, chat_room_id, message, created_at FROM messages WHERE message_id = '$message_id'");
            $result = $query->fetch_assoc();

            if (isset($result)) {
                $response['message'] = $result;
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'Failed send message';
        }

        return $response;
    }

    // fetching all chat rooms
    public function getAllChatrooms()
    {
        return $this->conn->query("SELECT * FROM chat_rooms");
    }

    // fetching single chat room by id
    function getChatRoom($chat_room_id)
    {
        $query = $this->conn->query("SELECT cr.chat_room_id, cr.name, cr.created_at AS chat_room_created_at, u.name AS username, c.* FROM chat_rooms cr LEFT JOIN messages c ON c.chat_room_id = cr.chat_room_id LEFT JOIN users u ON u.user_id = c.user_id WHERE cr.chat_room_id = '$chat_room_id'");
        return $query;
    }

    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email)
    {
        $query = $this->conn->query("SELECT user_id FROM users WHERE email='$email'");
        $result = $query->fetch_assoc();
        return isset($result);
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     * @return null
     */
    public function getUserByEmail($email)
    {
        $query = $this->conn->query("SELECT user_id, name, email, created_at FROM users WHERE email = '$email'");
        $result = $query->fetch_assoc();

        return isset($result) ? $result : NULL;
    }

}