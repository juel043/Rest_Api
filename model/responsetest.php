<?php 

require_once('Response.php');

$response = new Response();
$response->setSuccess(true);
$response->setHttpStatusCode(200);
$response->addMessage("Test Msg 1");
$response->addMessage("Test Msg 2");
$response->send();

 ?>