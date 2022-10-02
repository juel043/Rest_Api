<?php
class DB{

	private static  $writrDBConnection;

	private static  $readDBConnection;


	public static function connectionDB(){
		if(self::$writrDBConnection === null){
			 self::$writrDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8','root','');		
                self::$writrDBConnection->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                self::$writrDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
			}

			return self::$writrDBConnection;
	}
	public static function connectReadDB(){

		if(self::$readDBConnection === null){

			self::$readDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8','root','');
			 self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
		}

		return self::$readDBConnection;
	}

}


?>