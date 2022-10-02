<?php  
require_once('db.php');
require_once('../model/Response.php');
try {

	$writeDB =DB::connectionDB();
	$readDB = DB::connectReadDB();
	
} catch (PDOException $e) {
	
$response = new Response();
$response->setSuccess(true);
$response->setHttpStatusCode(200);
$response->addMessage("Test Msg 1");
$response->addMessage("Test Msg 2");
$response->send();
exit;
}
?>