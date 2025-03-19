<?php

class Database{
    private static $dbName = 'cis355';
    private static $dbHost = 'localhost';
    private static $dbUsername = 'root';
    private static $dbUserPassword = '';


    private static $connection = null;

    public function __construct(){
        exit('No constructor required for class: Database');

    }

    public static function connect(){
         if(null == self::$connection){
            try{
                self::$connection = new PDO(
                    "mysql:host=".self::$dbHost.";"."dbname=".self::$dbName,
                    self::$dbUsername, self::$dbUserPassword);
                
            }
            catch(PDOexception $e){ die($e->getMessage());}

         }
         return self::$connection;
    }
    public static function disconnect(){
        self::$connection = null;
    }
}




?>