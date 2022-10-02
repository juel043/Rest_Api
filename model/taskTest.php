<?php
 
 require_once('Task.php');


 try{
  
  $task = new Task(1,"Title Here","Description Here", "02/02/2022 11:00", "N");
  header('Content-type: application/json;charset=UTF-8');
  echo json_encode($task->returnTasksArray());

 }

catch(TaskException $ex){
	echo "Error: ".$ex->getMessage();
}
?>