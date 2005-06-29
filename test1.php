<?php
$fp = fsockopen("192.168.211.2", 80, $errno, $errstr, 30);
if (!$fp) {
   echo "$errstr ($errno)<br />\n";
} else {
   $out = "GET /cgi-bin/rgw-debug/rbexamples/EXMOD:Employee?redbeans=1 HTTP/1.1\r\n";
	 $out .= "User-agent: redbeans\r\n";
   $out .= "Host: 192.168.211.2\r\n";
   $out .= "Connection: Close\r\n\r\n";

   fwrite($fp, $out);
	 $s = '';
   while (!feof($fp)) {
       $s .= fgets($fp, 128);
   }
   fclose($fp);
	 var_dump($out);
	 var_dump($s);
}
?> 