<?php
class db
{
    private const HOST = 'localhost';
    private const DB = 'quickfix_db';
    private const USER = 'root';
    private const PASS = '';
    private const PORT = '3306';
    private const UTF = 'utf8mb4';
    private const ATT = [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE =>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ];

    public function conn(){
        try{
            return new PDO("mysql:host=".self::HOST.";dbname=".self::DB.";port=".self::PORT.";charset=".self::UTF.";",self::USER,self::PASS,self::ATT);
        }catch (PDOException $e){
            die("Connection Failed".$e->getMessage());
        }
        
    }
  
}

?>