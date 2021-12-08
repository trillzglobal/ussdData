 <?php

include "db_inc.php";
//PIN


function RandomToken(){
	$min=100000000000;
	$max = 999999999999;
    $pin = random_int($min,$max);

    return $pin;
}


//
function insertPIN($pin,$value,$recharge_code,$nework_id,$bundle_id,$mysqli){
	print_r($pin."\n");
	$sql= "INSERT IGNORE INTO pin_bucket(value,pin,network_id,bundle_id,created_date,recharge_code,status) VALUE('$value','$pin',$nework_id,$bundle_id,NOW(),'$recharge_code',1)";
	$query = mysqli_query($mysqli,$sql);
	if($query){
		return TRUE;
	}else{
		return FALSE;
	}

}



function generatePin($value, $recharge_code, $network_id, $bundle_id, $mysqli, $num=1){
	$i = 0;

   while($i<=abs($num)){
			$pin = RandomToken();
			$status = insertPIN($pin,$value,$recharge_code,$network_id,$bundle_id,$mysqli);
			if($status === TRUE){
				$i = $i + 1;
			}
	}
}



	//Generate 1GB
	generatePin('1GB', 'data_share_1gb',2, 101, $mysqli, $num=30);

	//Generate 2GB
	//generatePin('2GB', 'data_share_2gb',2, 102, $mysqli, $num=20);

	//Generate 5GB
	//generatePin('5GB', 'data_share_5gb',2,103, $mysqli, $num=5);

?>
