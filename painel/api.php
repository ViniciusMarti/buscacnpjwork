<?php

header('Content-Type: application/json');

$statusFile = "../import/status.json";

if (!file_exists($statusFile)) {
    echo json_encode(["error" => "Status file not found"]);
    exit;
}

$status=json_decode(file_get_contents($statusFile),true);

foreach($status["db"] as $db=>$dados){
 // Note: Connecting to 32 DBs per request might be slow.
 // In a real scenario, we might want to cache this or update it in the worker.
 try {
     $conn=@new mysqli("localhost",$db,"qPMwBp#WW*BN6k",$db);

     if ($conn->connect_error) {
         $status["db"][$db]["size"] = "Error";
         continue;
     }

     $q=$conn->query("
     SELECT
     ROUND(SUM(data_length+index_length)/1024/1024,2) size
     FROM information_schema.tables
     WHERE table_schema='$db'
     ");

     if ($q) {
         $r=$q->fetch_assoc();
         $status["db"][$db]["size"]=$r["size"] ?? 0;
     } else {
         $status["db"][$db]["size"] = 0;
     }
     
     $conn->close();
 } catch (Exception $e) {
     $status["db"][$db]["size"] = 0;
 }
}

echo json_encode($status);
