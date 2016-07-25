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

    //Сохранить сообщение
    public function save_message($id,$message){

        $destination = $this->get_users_from_chatrooms($id);

        foreach ($destination as $dest){
            $query = $this->conn->query("INSERT INTO messages (chat_room_id, user_id, message) VALUES ('$id','$dest','$message')");
        }
    }

    //Получить всех пользователей чата
    public function get_users_from_chatrooms($id){
        $users = array();
        $query = $this->conn->query("SELECT user_id FROM chat_relations WHERE chat_id='$id'");
        while ($res = $query->fetch_assoc()){
            array_push($users,$res['user_id']);
        }
        return $users;
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
    }

    public function query($sql)
    {
        return $this->getConn()->query($sql);
    }


}