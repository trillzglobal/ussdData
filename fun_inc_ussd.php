<?php

define("EMAIL", "trillzglobal@gmail.com"); //Email after Calling Registration Endpoint
define("PASSWORD", "P4y4n7@SM3"); //Password after Calling Registration Endpoint

function sendOutput($msisdn,$sessionid,$ussdtext,$end=0) {

// Send output as XML

//header('Content-Type: text/xml');
	$output ='<?xml version="1.0" encoding="UTF-8"?>';
	$output .='<output>';
	$output .='<msisdn>'.$msisdn.'</msisdn>';
	$output .='<sess>'.$sessionid.'</sess>';			
	$output .='<text>'.$ussdtext.'</text>';
	$output .='<msgid>'.rand(1000000,9999999).'</msgid>'; 
	$output .='<endsess>'.$end.'</endsess>';
	$output .='</output>';
echo $output;	
	
}

function logRequest($msisdn,$sessionid,$ussd,$mno,$mysqli) {
	
	$sql = "INSERT INTO ussd_request(msisdn,sessionid,mno,ussd_data,ts) VALUES('$msisdn','$sessionid','$mno','$ussd',NOW())";
	$res = mysqli_query($mysqli,$sql);	
	
}


function logUserSession($msisdn,$sessionid,$mysqli) {
	
	$sql = "INSERT into ussd_usersession(msisdn,sessionid,ts_start,ts_last) values('$msisdn','$sessionid',NOW(),NOW())
	ON DUPLICATE KEY update ts_last = NOW()";
	$res = mysqli_query($mysqli,$sql);
	//echo mysqli_error($mysqli);
}


function getCurrentMenu($msisdn,$sessionid,$mysqli) {
	
	// Declare
	$menuid = '';
	
	$sql = "SELECT menuid from ussd_usersession where msisdn = '$msisdn' and sessionid = '$sessionid'";
	$res = mysqli_query($mysqli,$sql);
	
	$menuid = mysqli_fetch_assoc($res);
	
	return $menuid['menuid'];
	
}

function verifyPIN($input,$mysqli) {
	
	// Declare
	
	$sql = "SELECT * FROM pin_data_info where pin = '$input'";
	$res = mysqli_query($mysqli,$sql);
	
	 if(mysqli_num_rows($res)== 0){
	 	
	 	return FALSE;
	 }
	 else{
	 	$verifyPIN = mysqli_fetch_assoc($res);
	 	return $verifyPIN;
	 }


	
}

function getPinNumber($sessionid,$msisdn,$mysqli) {
	
	
	$sql = "SELECT pinnumber from ussd_usersession where msisdn = '$msisdn' and sessionid = '$sessionid'";
	$res = mysqli_query($mysqli,$sql);
	
	$menuid = mysqli_fetch_assoc($res);
	
	return $menuid['pinnumber'];
	
}

function updatePinTableProcessing($pinnumber,$processing,$mysqli){
	$sql = "UPDATE pin_data_info SET processing = '$processing'  WHERE pin = '$pinnumber'";
	$res = mysqli_query($mysqli,$sql);
	if($res){
		return TRUE;
	}
	else{
		return FALSE;
	}
}

function checkPINProcessing($pinnumber,$processing,$mysqli){
    //Function to UPDATE PIN and prevent double processing
	$sql = "UPDATE pin_data_info SET processing = '$processing'  WHERE pin = '$pinnumber' AND processing = 0";
	$res = mysqli_query($mysqli,$sql);
        
        
	if($res){
		return TRUE;
	}
	else{
		return FALSE;
	}
}

function updatePinTable($msisdn,$sessionid,$pinnumber,$response_status,$processing,$mysqli){
	$sql = "UPDATE pin_data_info SET used_by = '$msisdn', processing = '$processing', status = '0', time_used = NOW(), sessionid='$sessionid', remark = '$response_status', updated_at=NOW()  WHERE pin = '$pinnumber'";
	$res = mysqli_query($mysqli,$sql);
       
                
	if($res){
		return TRUE;
	}
	else{
		return FALSE;
	}
}

function recordTransaction($msisdn,$sessionid,$response,$response_code,$status,$product_code,$product_value,$mysqli){
	$sql = "INSERT INTO ussd_transactions(msisdn,sessionid,product_code, product_value, ".print_r(response,1)." ,response_code,status,time_date) VALUES('$msisdn','$sessionid','$product_code','$product_value','$response','$response_code','$status',NOW()) ";
	$res = mysqli_query($mysqli,$sql);
       
}


function apiCall($msisdn,$product_code){
	$api_key = "34e62a0f";
	$curl = curl_init();
	$msisdn = substr($msisdn,3);
	$msisdn = '0'.$msisdn;

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://smartrecharge.ng/api/v2/datashare/?api_key={$api_key}&product_code={$product_code}&phone={$msisdn}",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
));

$response = curl_exec($curl);

curl_close($curl);
/*
$response ='{"server_message":"Transaction Pending","status":true,"error_code":1981,"data":{"status":true,"server_message":"Transaction Submitted 08136740318 will be recharged shortly, Cashback(If Applicable) will be allocated within 5 Minutes After successful recharge","recharge_id":319227,"error_code":1981,"amount_charged":"600.00","after_balance":"5707.42","bonus_earned":"165.00","text_status":"PENDING"},"text_status":"PENDING"}';

 * 
 */
 return $response;
}

function setSessionData($sessionid, $col, $data, $mysqli) {
	$allowed_col = array('network','amount','menuid','destination','pinnumber','product_type');
	if(!in_array($col,$allowed_col)) {
		return FALSE;
	}

	$sql = "UPDATE ussd_usersession set $col = '$data' where sessionid = '$sessionid'";
	$res = mysqli_query($mysqli,$sql);	

	}




function login(){

		$content = array("email"=>EMAIL, "password"=>PASSWORD);
		$content = json_encode($content);
		$url = "https://zealvend.com/api/login";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_POSTFIELDS,$content);
		curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);

		$response = curl_exec($ch);
		curl_close($ch);
		$res = json_decode($response,1);
		$res = $res['token'];

		return $res;
}


function vendSMEPayant($number,$typeid,$network,$ref=""){
	 $number = substr($number,3);
        $number = '0'.$number;
if($typeid == "data_share_1gb"){$bundle = 'MTN-1GB';}
	if($typeid == "data_share_2gb"){$bundle = 'MTN-2GB';}
	if($typeid == "data_share_5gb"){$bundle = 'MTN-5GB';}
	if(empty($ref)){$ref = "REF".uniqid();}
	if(empty($network)){$network = 'MTN';}
	if(empty($number) || empty($network)){
		return "Number and typeId cant be Empty";
	}

	$content = array("number"=>$number, "bundle"=>$bundle,"referrence"=>$ref,"network"=>"MTN");

	$content = json_encode($content);

	$url ="https://zealvend.com/api/data/vend";
	$token = array("Content-Type: application/json","Authorization: Bearer ".login());

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER,$token);
		curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$content);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);

		$response = curl_exec($ch);
		curl_close($ch);
		

		return $response;

	
}
