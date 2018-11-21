<?php
    require_once("InstDownloader.php");
    $spider =new InstDownloader();
    $url = $_GET["url"];
    try
    {
        $spider->getReadyData($url);        
        echo json_encode(["data"=>$spider->getItemsInDetailPage()]);
    }
    catch(ValidatingException $ex)
    {
        echo json_encode(["error"=>$ex->getMessage()]);
    }
    catch(Exception $e)
    {
        echo json_encode(["error"=>$e->getMessage()]);
        $st = $url."\r\n";
        $st .= $e->getMessage()."\r\n";
        $st .=$e->getTraceAsString();
        $st .= "\r\n==========="."\r\n";
        $dir = "error";
        if(!is_dir($dir)) mkdir($dir);
        file_put_contents($dir."/err_".md5($url).".log",$st);
    }
    
?>