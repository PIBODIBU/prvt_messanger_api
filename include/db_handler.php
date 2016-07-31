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

    //Close connection
    public function close(){
        mysqli_close($this->conn);
    }

    public function getUserById($id)
    {
        $query = $this->conn->query("SELECT user_id,name,phone,email FROM users WHERE user_id='$id'");
        $user = $query->fetch_assoc();
        return $user;
    }

    /**
     * Получить пользователя по токену
     * @param $token
     * @return null
     */
    public function getUser($token)
    {
        $query = $this->conn->query("SELECT * FROM users WHERE BINARY token='$token'");
        $user = $query->fetch_assoc();
        return isset($user) ? $user : NULL;
    }


    /**
     *Получить всю информацию о всех пользователять, кроме пользоветеля @ignore_user
     */
    public function getAllUsersWithIgnore($ignore_user){
        $token = $ignore_user['token'];
        $query = $this->conn->query("SELECT user_id,name,phone FROM users WHERE BINARY token != '$token'");
        $users = array();
        while ($res = $query->fetch_assoc()){
            array_push($users,$res);
        }
        return $users;
    }

    public function isUserExists(){

    }


    public function isItMyChat($user_id, $chat_id)
    {
        $query = $this->query("SELECT * FROM chat_relations WHERE user_id='$user_id' AND chat_id='$chat_id'");
        $result = $query->fetch_assoc();
        return isset($result);
    }

    //проверить на ошибки присланое сообщение
    public function check_error(){

    }


    //Сохранить сообщение и отформатировать для отправки
    public function save_message_and_return_for_notification($id,$message,$author){

        $response = array();
        $message_container = array();
        $message_id = 0;
        $chat_room_id = 0;
        $message_created_at = "";
        $sender = array();
        $user_id = 0;
        $user_name = "";
        $user_phone = "";
        $user_mail = "";

        //Получение айди пользователя и информации о нём
        $user_id_array = array();
        $user_id_array = $this->getUser($author);
        $user_id = $user_id_array['user_id'];

        //Сохранение сообщения
        $query = $this->query("INSERT INTO messages (chat_room_id, user_id, message) VALUES ('$id','$user_id','$message')");
        $query = $this->query("SELECT LAST_INSERT_ID()");

        //Получение айди сообщения
        $query = $this->conn->query("SELECT LAST_INSERT_ID() FROM messages");
        $message_id = $query->fetch_assoc()['LAST_INSERT_ID()'];

        //Получение айди комнаты чата
        $chat_room_id = $id;


        //Получение даты создания сообщения
        $query = $this->query("SELECT created_at FROM messages WHERE message_id='$message_id'");
        $message_created_at = $query->fetch_assoc()['created_at'];




        $query = $this->conn->query("SELECT name,phone,email FROM users WHERE user_id='$user_id'");
        $result = array();
        $result = $query->fetch_assoc();

        $user_name = $result['name'];
        $user_phone = $result['phone'];
        $user_mail = $result['email'];

        $message_container['message'] = $message;
        $message_container['message_id'] = $message_id;
        $message_container['chat_room_id'] = $chat_room_id;
        $message_container['created_at'] = $message_created_at;

        $sender['id'] = $user_id;
        $sender['name'] = $user_name;
        $sender['phone'] = $user_phone;
        $sender['email'] = $user_mail;

        $error = array("error" => "false"  , "error_msg" => "");
        $response['message'] = $message_container;
        $response['message']['sender'] =$sender;
        $response['error'] = $error;

        return $response;
    }

    //Получить всех пользователей чата
    public function get_users_from_chatrooms_with_ignore($chat_id,$author){
        $users = array();

        //ignore
        $ignore = $this->getUser($author);

        $query = $this->conn->query("SELECT user_id FROM chat_relations WHERE chat_id='$chat_id'");
        while ($res = $query->fetch_assoc()){
            if($res['user_id'] != $ignore['user_id']) {
                array_push($users, $res['user_id']);
            }
        }

        $destination = array();

        foreach ($users as $user){
            $query = $this->conn->query("SELECT gcm_registration_id FROM users WHERE user_id='$user'");
            $res = $query->fetch_assoc();
            $destination[] = $res['gcm_registration_id'];
        }

        return $destination;
    }

    public function is_token_occupied($token){

        $query = $this->conn->query("SELECT * FROM users WHERE token='$token'");
        $res = $query->fetch_assoc();

        if(isset($res)){
            return true;
        } else{
            return false;
        }
    }

    /**
     * Проверка, существует ли чат
     * @param $id
     * @return bool
     */
    public function is_chat_exists($id){
        $query = $this->conn->query("SELECT * FROM chat_rooms WHERE chat_room_id='$id'");
        $res = $query->fetch_assoc();
        if(isset($res)){
            return true;
        } else{
            return false;
        }
    }

    //Создать чат
    public function create_chat($name){
        $query = $this->conn->query("INSERT INTO chat_rooms (name) VALUES '$name'");

        //$query = $this->conn->query("INSERT INTO chat_relations");
    }



    public function make_relation(){

    }

    public function query($sql)
    {
        return $this->getConn()->query($sql);
    }


}