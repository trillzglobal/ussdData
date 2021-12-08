<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'db_inc.php';
include "fun_inc_ussd.php";

//Get Value from USSD INPUT

$msisdn    = $_REQUEST['msisdn'];  //Phone Number
$sessionid = $_REQUEST['sessionid'];  //ID for session a user start
$input     = trim($_REQUEST['msg']); //Input from user during the session
$mno       = trim($_REQUEST['mno']); //Mobile Network Operator



//sendOutput($msisdn,$sessionid,$output,$end);  Response sent back


//Log Session to Database
logRequest($msisdn,$sessionid,$input,$mno,$mysqli);



// strip leading

	$input2 = '';
	if(substr($input,0,1) == '*') {
		$input2 = substr($input,1,strlen($input));
	} else {
		$input2 = $input;
	}
	

logUserSession($msisdn,$sessionid,$mysqli);



$menuid  = getCurrentMenu($msisdn,$sessionid,$mysqli);
//$s       = getUserSession($msisdn,$sessionid,$mysqli);
//$session = $s['sessionid'];



//Verify if MNO is  MTN.
if($mno != "MTN"){
	$ussdtext = "(tm)\n";
	$ussdtext .= "WRONG Code, You are Not Operating with MTN Number\n";
	$end = 1;
	sendOutput($msisdn,$sessionid,$ussdtext,$end);
	exit();
}


if($input == 9){
	//Message for New Session
	$session = '';
	$msisdn  = '';
	$input   = '';
	
	$ussdtext = "\n\n";
	$ussdtext .= "Thank you for Using Bolt\n";
	$end = 1;
	sendOutput($msisdn,$sessionid,$ussdtext,$end);
	exit();	
}

//If new Session input will be 0
/*
if($input == '') {	
	
	//unset VARS
	$session = '';
	$msisdn  = '';
	$input   = '';	
	
	$menuid = 0;
	setSessionData($sessionid,'menuid','0',$mysqli);
		
}
*/

//Menu ITEM = {0:New, 1:Load PIN, 2:Verify PIN, 3: Exit}

//MENU ID ={0: To start, 10: Verify PIN, 20:To Vend PIN}





if($menuid == 10){
	//Confirming PIN Lenght
	if(strlen($input) != 12){
		$ussdtext = "\n\n";
		$ussdtext .= "PIN Not in right Formart: Enter again\n";
		$ussdtext .= "9: To Exit\n";
		$end = 0;
		sendOutput($msisdn,$sessionid,$ussdtext,$end);
		exit();	
	}

	

	$verify = verifyPIN($input, $mysqli);

	//PIN DOES NOT EXIST
	if($verify == FALSE){
		$ussdtext = "(tm)\n";
		$ussdtext .= "You have input an Incorrect PIN\n";
		$end = 1;
		sendOutput($msisdn,$sessionid,$ussdtext,$end);
		exit();	
	}

	//PIN HAS BEEN USED
	if($verify['status'] == 0){
		$ussdtext = "\n\n";
		$ussdtext .= "The PIN you entered has been used\n";
		$end = 1;
		sendOutput($msisdn,$sessionid,$ussdtext,$end);
		exit();	
	}

	//PIN IS VALID
	if($verify['status'] == 1){
		$ussdtext = "\n\n";
		$ussdtext .= "PIN is Valid\n";
		$ussdtext .= "1: To Load\n";
		$ussdtext .= "9: To Exit\n";
		$end = 1;
		setSessionData($sessionid,'menuid','30',$mysqli);
		setSessionData($sessionid,'pinnumber',$input,$mysqli);
		sendOutput($msisdn,$sessionid,$ussdtext,$end);
		exit();	
	}

}

if($menuid == 30){

	if($input == 1){

		$pinnumber = getPinNumber($sessionid,$msisdn,$mysqli);
		$verify = verifyPIN($pinnumber, $mysqli);

		if(strlen($pinnumber) != 12){
		$ussdtext = "\n\n";
		$ussdtext .= "PIN Not in right Format: Enter again\n";
		$ussdtext .= "9: To Exit\n";
		$end = 0;
		sendOutput($msisdn,$sessionid,$ussdtext,$end);
		exit();	
		}
		

		//PIN DOES NOT EXIST
		if($verify == FALSE){
			$ussdtext = "\n\n";
			$ussdtext .= "You have input an Incorrect PIN\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
			}
			//PIN HAS BEEN USED
		if($verify['status'] == 0){
			$ussdtext = "\n\n";
			$ussdtext .= "The PIN you entered has been used\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}
		if(empty($pinnumber)){
			$ussdtext = "\n\n";
			$ussdtext .= "The PIN does not exist\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}

		if($verify['processing'] != 0){
                        $ussdtext = "\n\n";
			$ussdtext .= "Initiated by another customer, try again\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
                }
                
               $check = checkPINProcessing($pinnumber,1,$mysqli);
                
                if($check == FALSE){
                        $ussdtext = "\n\n";
			$ussdtext .= "Initiated by another customer, try again\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
                }
                
		//Call API

		$product_code = $verify['product_code'];
		$pin_value = $verify['pin_value'];
                //$pinnumber = $verify['pin'];

		$response = vendSMEPayant($msisdn,$product_code,"MTN",$ref);

       	$data_value = json_decode($response,true);
                error_log($response."\n",3,"error.log");

		$response_code = $data_value['status'];
		$response_status = $data_value['status'];
			

       	if($response_code == 'success'){
       	$status_code = "0";
       }
       else{
       	$status_code = "999999";
       }


		if($status_code == "0"){
			//Record Pin has been used
			$uDatePin = updatePinTable($msisdn,$sessionid,$pinnumber,$response_status,2,$mysqli);
			if($uDatePin == FALSE){
				$uDatePin = updatePinTable($msisdn,$sessionid,$pinnumber,$response_status,2,$mysqli);

				//SEND MAIL OF UNDOCUMENTED RECHARGE
			}
			//Record Transaction on USSD transaction log
		
                        recordTransaction($msisdn,$sessionid,$response,$response_code,$status_code,$product_code,$pin_value,$mysqli);

			$ussdtext = "\n\n";
			$ussdtext .= "Successful Recharge of {$pin_value} to {$msisdn}\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}

		
		else{
                        updatePinTableProcessing($pinnumber,0,$mysqli);
			recordTransaction($msisdn,$sessionid,$response,$response_code,$status_code,$product_code,$pin_value,$mysqli);
			

			//Set UP Mail to Notify if USSD Transaction failed

			$ussdtext = "\n\n";
			$ussdtext .= "Transaction Failed, Kindly try again Later\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}

	}
	else{
		$ussdtext = "\n\n";
		$ussdtext .= "Wrong Selection Try Again Later\n";
		$end = 1;
		sendOutput($msisdn,$sessionid,$ussdtext,$end);
		exit();
	}
}

if($menuid == 20){
	//$pinnumber = getPinNumber($sessionid,$msisdn,$mysqli);
		$verify = verifyPIN($input, $mysqli);

		if(strlen($input) != 12){
			$ussdtext = "\n\n";
			$ussdtext .= "PIN Not in right Format: Enter again\n";
			$ussdtext .= "9: To Exit\n";
			$end = 0;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}
		//PIN DOES NOT EXIST
		if($verify == FALSE){
			$ussdtext = "\n\n";
			$ussdtext .= "You have input an Incorrect PIN\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
			}
			//PIN HAS BEEN USED
		if($verify['status'] == 0){
			$ussdtext = "\n\n";
			$ussdtext .= "The PIN you entered has been used\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}
		if(empty($input)){
			$ussdtext = "\n\n";
			$ussdtext .= "The PIN does not exist\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}
                if($verify['processing'] == 1){
                        $ussdtext = "\n\n";
			$ussdtext .= "Initiated by another customer, try again\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
                }
                
                $product_code = $verify['product_code'];
		$pin_value = $verify['pin_value'];
                $pinnumber = $verify['pin'];
                
                $check = checkPINProcessing($pinnumber,1,$mysqli);
                
                if($check == FALSE){
                        $ussdtext = "\n\n";
			$ussdtext .= "Initiated by another customer, try again\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
                }
                
                
		//Call API

		

		
		

		/*$response = apiCall($msisdn,$product_code);
               // $response = '{"server_message":"Transaction Pending","status":true,"error_code":1981,"data":{"status":true,"server_message":"Transaction Submitted 08136740318 will be recharged shortly, Cashback(If Applicable) will be allocated within 5 Minutes After successful recharge","recharge_id":319227,"error_code":1981,"amount_charged":"600.00","after_balance":"5707.42","bonus_earned":"165.00","text_status":"PENDING"},"text_status":"PENDING"}';

		$data_value = json_decode($response,true);
                error_log($response."\n",3,"error.log");

		$response_code = $data_value['error_code'];
		$response_status = $data_value['text_status'];
			

       if($response_code == "1986" || $response_code == "1981"){

       	*/

       	$response = vendSMEPayant($msisdn,$product_code,"MTN",$ref);

       	$data_value = json_decode($response,true);
                error_log($response."\n",3,"error.log");

		$response_code = $data_value['status'];
		$response_status = $data_value['status'];
			

       	if($response_code == 'success'){
       	$status_code = "0";
       }
       else{
       	$status_code = "999999";
       }

       
		if($status_code == "0"){
			//Record Pin has been used
			$uDatePin = updatePinTable($msisdn,$sessionid,$pinnumber,$response_status,2,$mysqli);
                       
			if($uDatePin == FALSE){
				$uDatePin = updatePinTable($msisdn,$sessionid,$pinnumber,$response_status,2,$mysqli);

				//SEND MAIL OF UNDOCUMENTED RECHARGE
			}
			//Record Transaction on USSD transaction log
			recordTransaction($msisdn,$sessionid,$response,$response_code,$status_code,$product_code,$pin_value,$mysqli);

			$ussdtext = "\n\n";
			$ussdtext .= "Successful Recharge of {$pin_value} to {$msisdn}\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}

		
		else{
                        updatePinTableProcessing($pinnumber,0,$mysqli);
			recordTransaction($msisdn,$sessionid,$response,$response_code,$status_code,$product_code,$pin_value,$mysqli);
			

			//Set UP Mail to Notify if USSD Transaction failed

			$ussdtext = "\n\n";
			$ussdtext .= "Transaction Failed, Kindly try again Later\n";
			$end = 1;
			sendOutput($msisdn,$sessionid,$ussdtext,$end);
			exit();	
		}
}




if($input == ''){

	//Message for New Session
	$ussdtext = "\n\n";
	$ussdtext .= "1: To Load PIN\n";
	$ussdtext .= "2: To Verify PIN\n";
	$ussdtext .= "9: To Exit\n";
	$end = 0;
	sendOutput($msisdn,$sessionid,$ussdtext,$end);
	exit();	
}

if($input == 1){
	//Vend PIN
	$ussdtext = "\n\n";
	$ussdtext .= "Input PIN \n";
	$ussdtext .= "9: To Exit\n";
	$end = 0;
	setSessionData($sessionid,'menuid','20',$mysqli);
	sendOutput($msisdn,$sessionid,$ussdtext,$end);
	exit();	

}

if($input == 2){
	//Verify PIN
	$ussdtext = "\n\n";
	$ussdtext .= "Input PIN to verify\n";
	$ussdtext .= "9: To Exit\n";
	$end = 0;
	setSessionData($sessionid,'menuid','10',$mysqli);
	sendOutput($msisdn,$sessionid,$ussdtext,$end);
	exit();	

}



?>
