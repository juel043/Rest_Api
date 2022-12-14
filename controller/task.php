<?php 
 require_once('db.php');
 require_once('../model/Task.php');
 require_once('../model/Response.php');

 try{

 	$writeDB = DB::connectionDB();
 	$readDB = DB::connectReadDB();
 }

 catch(PDOException $ex)
 {
     error_log("Connection Error: " . $ex, 0);
 	$response = new Response();
 	$response->setHttpStatusCode(500);
 	$response->setSuccess(false);
 	$response->addMessage("Database Connection error");
 	$response->send();
 	exit();
 }


 if(array_key_exists("taskid", $_GET)){

 	$taskid = $_GET['taskid'];

 	if($taskid == '' || !is_numeric($taskid)){
        $response = new Response();
 	$response->setHttpStatusCode(400);
 	$response->setSuccess(false);
 	$response->addMessage("Task id can not be blank");
 	$response->send();
 	exit();

 	}

 	if($_SERVER['REQUEST_METHOD'] === 'GET'){
     
      // attempt to query the database
    try {
      // create db query
      $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid');
      $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // create task array to store returned task
      $taskArray = array();

      if ($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Task not found");
        $response->send();
        exit;
      }

      // for each row returned
      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object for each row
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

        // create task and store in array for return in json data
        $taskArray[] = $task->returnTaskAsArray();
      }

      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->toCache(true);
      $response->setData($returnData);
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch (TaskException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    } catch (PDOException $ex) {
      error_log("Database Query Error: " . $ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to get task");
      $response->send();
      exit;
    }
 	}

 	elseif($_SERVER['REQUEST_METHOD'] === 'DELETE'){


    try{

    $query = $writeDB->prepare('delete from tbltasks where id = :taskid');
    $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);

     $query->execute();
     
     $rowCount = $query->rowCount();

     if($rowCount === 0){

      $response = new Response();
      $response->setHttpStatusCode(404);

      $response->setSuccess(false);
      $response->addMessage("Task Not Found");
      $response->send();
      exit;

     }
     $response = new Response();
      $response->setHttpStatusCode(200);

      $response->setSuccess(true);
      $response->addMessage("Task Deleted");
      $response->send();
      exit;


    }
    catch(PDOException $ex){
            
            $response = new Response();
      $response->setHttpStatusCode(500);

      $response->setSuccess(false);
      $response->addMessage("Failed To delete task");
      $response->send();
      exit;
    }

 	}
    elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    // update task
    try {
      // check request's content type header is JSON
      if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Content Type header not set to JSON");
        $response->send();
        exit;
      }

      // get PATCH request body as the PATCHed data will be JSON format
      $rawPatchData = file_get_contents('php://input');

      if (!$jsonData = json_decode($rawPatchData)) {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit;
      }

      // set task field updated to false initially
      $title_updated = false;
      $description_updated = false;
      $deadline_updated = false;
      $completed_updated = false;

      // create blank query fields string to append each field to
      $queryFields = "";

      // check if title exists in PATCH
      if (isset($jsonData->title)) {
        // set title field updated to true
        $title_updated = true;
        // add title field to query field string
        $queryFields .= "title = :title, ";
      }

      // check if description exists in PATCH
      if (isset($jsonData->description)) {
        // set description field updated to true
        $description_updated = true;
        // add description field to query field string
        $queryFields .= "description = :description, ";
      }

      // check if deadline exists in PATCH
      if (isset($jsonData->deadline)) {
        // set deadline field updated to true
        $deadline_updated = true;
        // add deadline field to query field string
        $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
      }

      // check if completed exists in PATCH
      if (isset($jsonData->completed)) {
        // set completed field updated to true
        $completed_updated = true;
        // add completed field to query field string
        $queryFields .= "completed = :completed, ";
      }

      // remove the right hand comma and trailing space
      $queryFields = rtrim($queryFields, ", ");

      // check if any task fields supplied in JSON
      if ($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("No task fields provided");
        $response->send();
        exit;
      }

      // create db query to get task from database to update - use master db
      $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid');
      $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // make sure that the task exists for a given task id
      if ($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("No task found to update");
        $response->send();
        exit;
      }

      // for each row returned - should be just one
      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
      }

      // create the query string including any query fields
      $queryString = "update tbltasks set " . $queryFields . " where id = :taskid";
      // prepare the query
      $query = $writeDB->prepare($queryString);

      // if title has been provided
      if ($title_updated === true) {
        // set task object title to given value (checks for valid input)
        $task->setTitle($jsonData->title);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_title = $task->getTitle();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':title', $up_title, PDO::PARAM_STR);
      }

      // if description has been provided
      if ($description_updated === true) {
        // set task object description to given value (checks for valid input)
        $task->setDescription($jsonData->description);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_description = $task->getDescription();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':description', $up_description, PDO::PARAM_STR);
      }

      // if deadline has been provided
      if ($deadline_updated === true) {
        // set task object deadline to given value (checks for valid input)
        $task->setDeadline($jsonData->deadline);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_deadline = $task->getDeadline();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
      }

      // if completed has been provided
      if ($completed_updated === true) {
        // set task object completed to given value (checks for valid input)
        $task->setCompleted($jsonData->completed);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_completed = $task->getCompleted();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
      }

      // bind the task id provided in the query string
      $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
      // run the query
      $query->execute();

      // get affected row count
      $rowCount = $query->rowCount();

      // check if row was actually updated, could be that the given values are the same as the stored values
      if ($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task not updated - given values may be the same as the stored values");
        $response->send();
        exit;
      }

      // create db query to return the newly edited task - connect to master database
      $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid');
      $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // check if task was found
      if ($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("No task found");
        $response->send();
        exit;
      }
      // create task array to store returned tasks
      $taskArray = array();

      // for each row returned
      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object for each row returned
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

        // create task and store in array for return in json data
        $taskArray[] = $task->returnTaskAsArray();
      }
      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->addMessage("Task updated");
      $response->setData($returnData);
      $response->send();
      exit;
    } catch (TaskException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch (PDOException $ex) {
      error_log("Database Query Error: " . $ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to update task - check your data for errors");
      $response->send();
      exit;
    }
  }
  // if any other request method apart from GET, PATCH, DELETE is used then return 405 method not allowed
  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
  }
}
elseif(array_key_exists("page",$_GET)){

if($_SERVER('REQUEST_METHOD')==='GET'){

    $page = $_GET['page'];

    if($page == '' || !is_numeric($page)){

         $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Page can not be blank and must be numeric");
    $response->send();
    exit;
    }

    $limitperpage = 20;

    try{
       $query = $readDB->prepare('SELECT COUNT(id) as totalNoOfTasks from tbltasks');
       $query->execute();
       $row = $query->fetch(PDO::FETCH_ASSOC);

       $tasksCount= intval($row['totalNoOfTasks']);

       $numOfPages =ceil($tasksCount/$limitperpage);

       if($numOfPages == 0){
        $numOfPages =1;
       }

       if($page>$numOfPages || $page == 0){
       
       $response = new Response();
       $response->setHttpStatusCode(404);
       $response->setSuccess(false);
       $response->addMessage("page not found");
       $response->send();
       exit;
       }

       $offset = ($page == 1 ? 0:($limitPerPage*($page-1)));
       $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks limit :pglimit offset :offset');

       $query = $bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
       $query->bindParam(':offset',$offset,PDO::PARAM_INT);
       $query->execute();

       $rowCount = $query->rowCount();

       $taskArray = array();

       while($row=$query->fetch(PDO::FETCH_ASSOC)){

        $task= new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);

        $taskArray[] = $task->returnTaskAsArray();

       

        $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['total_rows'] = $tasksCount;
      $returnData['total_pages'] = $numOfPages;

       ($page< $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page']=false );

       ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page']=false );
       $returnData['tasks']=$taskArray;

       $response = new Response();
       $response->setHttpStatusCode(200);
       $response->setSuccess(true);
       $response->toCache(true);
       $response->setData($returnData);

       $response->send();
       exit;
       }



    }
   catch(TaskException $ex){
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage($ex->getMessage());
        $response->send();
        exit;
    }
    catch(PDOException $ex){
        error_log("Database error-".$ex,0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Page get no task");
    $response->send();
    exit;
    }

}
else{

     $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
}

}
elseif (empty($_GET)) {
  // if request is a GET e.g. get tasks
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // attempt to query the database
    try {
      // create db query
      $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks');
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // create task array to store returned tasks
      $taskArray = array();

      // for each row returned
      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object for each row
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

        // create task and store in array for return in json data
        $taskArray[] = $task->returnTaskAsArray();
      }

      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->toCache(true);
      $response->setData($returnData);
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch (TaskException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    } catch (PDOException $ex) {
      error_log("Database Query Error: " . $ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to get tasks");
      $response->send();
      exit;
    }
  }
  // else if request is a POST e.g. create task
  elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // create task
    try {
      // check request's content type header is JSON
      if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Content Type header not set to JSON");
        $response->send();
        exit;
      }

      // get POST request body as the POSTed data will be JSON format
      $rawPostData = file_get_contents('php://input');

      if (!$jsonData = json_decode($rawPostData)) {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit;
      }

      // check if post request contains title and completed data in body as these are mandatory
      if (!isset($jsonData->title) || !isset($jsonData->completed)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->title) ? $response->addMessage("Title field is mandatory and must be provided") : false);
        (!isset($jsonData->completed) ? $response->addMessage("Completed field is mandatory and must be provided") : false);
        $response->send();
        exit;
      }

      // create new task with data, if non mandatory fields not provided then set to null
      $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);
      // get title, description, deadline, completed and store them in variables
      $title = $newTask->getTitle();
      $description = $newTask->getDescription();
      $deadline = $newTask->getDeadline();
      $completed = $newTask->getCompleted();

      // create db query
      $query = $writeDB->prepare('insert into tbltasks (title, description, deadline, completed) values (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed)');
      $query->bindParam(':title', $title, PDO::PARAM_STR);
      $query->bindParam(':description', $description, PDO::PARAM_STR);
      $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
      $query->bindParam(':completed', $completed, PDO::PARAM_STR);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // check if row was actually inserted, PDO exception should have caught it if not.
      if ($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Failed to create task");
        $response->send();
        exit;
      }

      // get last task id so we can return the Task in the json
      $lastTaskID = $writeDB->lastInsertId();
      // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
      $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid');
      $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // make sure that the new task was returned
      if ($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Failed to retrieve task after creation");
        $response->send();
        exit;
      }

      // create empty array to store tasks
      $taskArray = array();

      // for each row returned - should be just one
      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

        // create task and store in array for return in json data
        $taskArray[] = $task->returnTaskAsArray();
      }
      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      //set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(201);
      $response->setSuccess(true);
      $response->addMessage("Task created");
      $response->setData($returnData);
      $response->send();
      exit;
    }
    // if task fails to create due to data types, missing fields or invalid data then send error json
    catch (TaskException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch (PDOException $ex) {
      error_log("Database Query Error: " . $ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to insert task into database - check submitted data for errors");
      $response->send();
      exit;
    }
  }
  // if any other request method apart from GET or POST is used then return 405 method not allowed
  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
  }
}
 	
else {
  $response = new Response();
  $response->setHttpStatusCode(404);
  $response->setSuccess(false);
  $response->addMessage("Endpoint not found");
  $response->send();
  exit;
}


 


?>

<!-- elseif($_SERVER['REQUEST_METHOD']=== 'POST'){

   try{
       
       if($_SERVER['CONTENT_TYPE'] !== 'application/json'){

        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Content Type header not sent to JSON");
        $response->send();
        exit;
       }

        $rawPostData = file_get_contents('php://input');
if(!$jsonData = json_decode($rawPostData)){
    $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit;
}

if (!isset($jsonData->title) || !isset($jsonData->completed)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->title) ? $response->addMessage("Title field is mandatory and must be provided") : false);
        (!isset($jsonData->completed) ? $response->addMessage("Completed field is mandatory and must be provided") : false);
        $response->send();
        exit;
      }

         $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);
      // get title, description, deadline, completed and store them in variables
      $title = $newTask->getTitle();
      $description = $newTask->getDescription();
      $deadline = $newTask->getDeadline();
      $completed = $newTask->getCompleted();

       $query = $writeDB->prepare('insert into tbltasks (title, description, deadline, completed) values (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed)');
      $query->bindParam(':title', $title, PDO::PARAM_STR);
      $query->bindParam(':description', $description, PDO::PARAM_STR);
      $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
      $query->bindParam(':completed', $completed, PDO::PARAM_STR);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // check if row was actually inserted, PDO exception should have caught it if not.
      if ($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Failed to create task");
        $response->send();
        exit;
      }

      $lastTaskID = $writeDB->lastInsertId();
      // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
      $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid');
      $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // make sure that the new task was returned
      if ($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Failed to retrieve task after creation");
        $response->send();
        exit;
      }

      // create empty array to store tasks
      $taskArray = array();

      // for each row returned - should be just one
      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

        // create task and store in array for return in json data
        $taskArray[] = $task->returnTaskAsArray();
      }
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      //set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(201);
      $response->setSuccess(true);
      $response->addMessage("Task created");
      $response->setData($returnData);
      $response->send();
      exit;
    }
     catch (TaskException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    }
     catch (PDOException $ex) {
      error_log("Database Query Error: " . $ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to insert task into database - check submitted data for errors");
      $response->send();
      exit;
    }
   

    } -->