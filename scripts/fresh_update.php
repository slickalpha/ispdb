<?php

ini_set('max_execution_time', 1200);

$ispdb_src = "https://autoconfig.thunderbird.net/v1.1/";

// Production - https://autoconfig.thunderbird.net/v1.1/
// Staging - https://autoconfig-stage.thunderbird.net/autoconfig/v1.1/
// About - https://github.com/thundernest/autoconfig

// TODO ; More options in supported filter
$supported = array("inauth" => array("password-cleartext"), "outauth" => array("password-cleartext"), "insock" => array("SSL", "STARTTLS"), "outsock" => array("SSL", "STARTTLS"));

$verbose = true;

if($verbose) {
	error_reporting(E_ALL & ~E_NOTICE);
	ini_set("display_errors", 1);
}

if(($ispdb_src!="") && ($data_arr = get_data($ispdb_src, $supported, $verbose))) {
	// TODO Output for MySQL, SQLlite, etc..; 
	$file = '../ispdb.json';
	$fp = fopen($file, 'w');
	fwrite($fp, json_encode($data_arr));
	fclose($fp);
	file_to_gz($file);
	//unlink($file); //remove source json
	if($verbose) { print 'File write complete'.PHP_EOL; }
}
else {
	if($verbose) { print 'Fetch failed'.PHP_EOL; }
}
die();

function get_data($url, $supported, $verbose) {
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
	curl_setopt($ch,CURLOPT_TIMEOUT, 9);
	curl_setopt($ch, CURLOPT_ENCODING , "");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'X-Forwarded-For: '.long2ip(mt_rand()+mt_rand()+mt_rand(0,1)),
	));
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:63.0) Gecko/20100101 Firefox/63.0");
	$resp = curl_exec($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close ($ch);

	if($httpcode==200) {
		if($verbose) { print 'Fetched domain list'.PHP_EOL; }
		
		$resp = preg_replace('/(?=<!--)([\s\S]*?-->)/i', '', $resp);
		preg_match_all('/a\s{0,}href\s{0,}=\s{0,}("|\')(.[^("|\')]*?)("|\')/i', $resp, $matches);
		if(!is_array($matches) || !isset($matches[2])) return false;
		$domains = array();
		foreach($matches[2] as $match) {
			if(preg_match('/^(?:[\w-]+\.)*([\w-]{1,63})(?:\.(?:\w{3,9}|\w{2}))$/i', $match)) {
				array_push($domains, $match);
			}
		}
		
		if($verbose) { print 'Total domains found - '.count($domains).PHP_EOL; }
		
		$data_arr = array();
		
		libxml_use_internal_errors(true);
		
		foreach($domains as $domain) {
			if($verbose) { print 'Fetching domain '.$domain.PHP_EOL; }
			
			try {
				$xml = new SimpleXMLElement($url.$domain, NULL, TRUE);
			} catch (Exception $e){
				if($verbose) { print 'Failed xml for domain '.$domain.PHP_EOL; }
				sleep(2);
				continue;
			} 
			
			if($xml!==false && isset($xml->emailProvider)) {
				$inhost = false; $outhost = false;
				foreach($xml->emailProvider->incomingServer as $data) {
					if((isset($data->attributes()->type) && $data->attributes()->type=="imap" && isset($data->hostname) && isset($data->port)) && (($inhost===false || $data->port==993) ? true : false) && in_array($data->authentication, $supported["inauth"]) && in_array($data->socketType, $supported["insock"])) {
						$inhost = (string) $data->hostname;
						$inport = (string) $data->port;
						$insocket = (string) $data->socketType;
					}
				}
				foreach($xml->emailProvider->outgoingServer as $data) {
					if((isset($data->attributes()->type) && $data->attributes()->type=="smtp" && isset($data->hostname) && isset($data->port)) && (($outhost===false || $data->port==587) ? true : false) && in_array($data->authentication, $supported["outauth"]) && in_array($data->socketType, $supported["outsock"])) {
						$outhost = (string) $data->hostname;
						$outport = (string) $data->port;
						$outsocket = (string) $data->socketType;
					}
				}
				if($inhost && $outhost) {
					if(count($xml->emailProvider->domain)>1) {
						foreach($xml->emailProvider->domain as $ndomain) {
							$data_arr[((string) $ndomain)] = array("IMAP" => array("h" => $inhost, "p" => $inport, "s" => $insocket), "SMTP" => array("h" => $outhost, "p" => $outport, "s" => $outsocket));
						}
					} 
					else {
						$data_arr[$domain] = array("IMAP" => array("h" => $inhost, "p" => $inport, "s" => $insocket), "SMTP" => array("h" => $outhost, "p" => $outport, "s" => $outsocket));
					}
				} else continue;
			} else continue;
			if($verbose) { print 'Parsed domain '.$domain.PHP_EOL; }
		}
		return $data_arr;
	} else return false;
}

function file_to_gz($source, $level = 9){ 
    $dest = $source.'.gz'; 
    $mode = 'wb'.$level; 
    $error = false; 
    if ($fp_out = gzopen($dest, $mode)) { 
        if ($fp_in = fopen($source,'rb')) { 
            while (!feof($fp_in)) 
                gzwrite($fp_out, fread($fp_in, 1024 * 512)); 
            fclose($fp_in); 
        } else {
            $error = true; 
        }
        gzclose($fp_out); 
    } else {
        $error = true; 
    }
    if ($error) {
        return false; 
	} else {
        return $dest; 
	}
} 	

?>
