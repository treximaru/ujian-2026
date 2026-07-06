<?php
header('Cache-Control:no-store,no-cache,must-revalidate');
header('Pragma:no-cache');
header('Expires:0');

$root=realpath(__DIR__);
$uri=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if($uri==='')$uri='/';
$fp=$root.$uri;

// Directory: try index.php first, then index.html
if(is_dir($fp)){
  if(is_file($fp.'/index.php')){
    chdir($fp);
    $_SERVER['SCRIPT_FILENAME']=$fp.'/index.php';
    $_SERVER['SCRIPT_NAME']=$uri.'/index.php';
    return false;
  }
  if(is_file($fp.'/index.html')){
    header('Content-Type:text/html');
    readfile($fp.'/index.html');
    return true;
  }
  http_response_code(404);
  echo '404 Not Found';
  return true;
}

// PHP file: let built-in server execute it
if(is_file($fp)&&pathinfo($fp,PATHINFO_EXTENSION)==='php'){
  chdir(dirname($fp));
  $_SERVER['SCRIPT_FILENAME']=$fp;
  return false;
}

// Static file: serve with cache headers
if(is_file($fp)){
  $mimes=['html'=>'text/html','css'=>'text/css','js'=>'application/javascript','json'=>'application/json','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','mp3'=>'audio/mpeg','mp4'=>'video/mp4','webm'=>'video/webm','svg'=>'image/svg+xml'];
  $ext=pathinfo($fp,PATHINFO_EXTENSION);
  if(isset($mimes[$ext]))header('Content-Type:'.$mimes[$ext]);
  readfile($fp);
  return true;
}

return false;
