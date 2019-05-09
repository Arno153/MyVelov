<html>
<head>
	<meta name="robots" content="noindex, follow">
</head>
<body>
<?php
// velov data collection -- cron once per min
include "./../inc/cacheMgt.inc.php";
include "./../inc/mysql.inc.php";

$debug = true;
$debugVerbose = false;
$debugvelovRawData= false;
$velovExit = 0;
$EvelovExit = 0;
$velovReturn = 0;
$EvelovReturn = 0;

echo date(DATE_RFC2822);
	echo "<br>";

// velov data collection
try
{	$SomevelovRawData = file_get_contents('https://download.data.grandlyon.com/ws/rdata/jcd_jcdecaux.jcdvelov/all.json');
	if($SomevelovRawData==false)
	{
		echo "ko"; 
		exit;
	}
}catch (Exception $e) {
		echo "ko: url is not reachable";
		exit;
}

//DB connect
$link = mysqlConnect();
if (!$link) {
    echo "Error: Unable to connect to MySQL." . PHP_EOL;
    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
    exit;
}

if($debugVerbose)
{
	echo "Success: A proper connection to MySQL was made! The my_db database is great." . PHP_EOL;
	echo "<br>";
	echo "Host information: " . mysqli_get_host_info($link) . PHP_EOL;
	echo "<br>";
}

// ---- nettoyage des données oscilatoire
$jsonMd5 = md5($SomevelovRawData); //on calc le md5 du flux courrant
//on purge des data de pplus de 12h dans le log des MD5 des flux
$r = " Delete from `velov_api_sanitize` WHERE `JsonDate` <= DATE_ADD(NOW(), INTERVAL - 12 HOUR)"; 
if($debugVerbose){ 	echo $r; echo "<br>";}							
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
}	

// on ajoute le md5 du flux courant dans la table de log des MD5 des flux
$r = "
		INSERT INTO `velov_api_sanitize` ( `JsonDate`, `JsonMD5`) 
		VALUES ( sysdate(), '$jsonMd5' )
	";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
}


// si le md5 du flux courant est sorti plus de 25 fois danns les dernières 12h on le blacklist
$r = "
		SELECT 
			`JsonMD5`,
			count(`JsonMD5`) c
		FROM `velov_api_sanitize`
		group by `JsonMD5`
		having count(`JsonMD5`)> 25
	";
$md5BlackListedArray = array();
//on recupère les valeurs black listées
if($result = mysqli_query($link, $r)) 
{
	//on construit un tableau des valeurs blacklistées
	if (mysqli_num_rows($result)>0)
	{						
		while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
		{
			$md5BlackListedArray[] = $row["JsonMD5"];
		}
	}
}
else						
{
	printf("Errormessage: %s\n", mysqli_error($link));
}

//si le md5 du flux courant est blacklisté on arrette recommance 1 fois puis on arrète là!
if(in_array($jsonMd5, $md5BlackListedArray, false))
{
	if($debugVerbose)
	{
		var_dump($md5BlackListedArray);
	}
	echo "<br> MD5 = ".$jsonMd5." On ignore automatiquement ce json suivant son MD5 <br>";
	echo "<br> retry in 10 sec <br>";
	sleep(10);
	
	// velov data collection (bis)
	try
	{	$SomevelovRawData = file_get_contents('https://download.data.grandlyon.com/ws/rdata/jcd_jcdecaux.jcdvelov/all.json');
		if($SomevelovRawData==false)
		{
			echo "ko"; 
			exit;
		}
	}catch (Exception $e) {
			echo "ko: url is not reachable";
			exit;
	}
	$jsonMd5 = md5($SomevelovRawData); //on calc le md5 du flux courrant (bis)
	// on ajoute le md5 du flux courant dans la table de log des MD5 des flux
	$r = "
			INSERT INTO `velov_api_sanitize` ( `JsonDate`, `JsonMD5`) 
			VALUES ( sysdate(), '$jsonMd5' )
		";
	if(!mysqli_query($link, $r))
	{
		printf("Errormessage: %s\n", mysqli_error($link));
	}	
	
	if(in_array($jsonMd5, $md5BlackListedArray, false))
	{
		if($debugVerbose)
		{
			var_dump($md5BlackListedArray);
		}
		echo "<br> MD5 = ".$jsonMd5." On ignore automatiquement ce json suivant son MD5 <br>";
		echo "<br> KO";
		exit;
	}
		
	
}	
//si le md5 du flux courant n'est pas black-listé par le log on poursuit avec la maj des données
// ---- nettoyage des données oscilatoire

error_log( date("Y-m-d H:i:s")." - Collecte des données velov");

if($debugvelovRawData)
{
	echo "vardump SomevelovRawData</br>";
	echo $SomevelovRawData;
	echo "</br>";
}

$velovDataArray = json_decode($SomevelovRawData, true);

if($debugvelovRawData)
{
	echo "vardump velovDataArray</br>";
	var_dump($velovDataArray);
	echo "</br>";
}

if(!is_array($velovDataArray))
{
	echo "<br> Retour inattendu de l'api velov";
	error_log( date("Y-m-d H:i:s")." - Retour inattendu de l'api velov");
	error_log(date("Y-m-d H:i:s")." - json decode error - ".json_last_error ().":".json_last_error_msg ());
	error_log(date("Y-m-d H:i:s").$SomevelovRawData);
	exit;
}

// update log
// 0 : 
$logstring = "";
$lofFile='./../log/updatelog.csv';
if(!file_exists ($lofFile) )
  $logstring = "date;requete;\r\n";


// 1 : on ouvre le fichier
if(!($openLogFile = fopen($lofFile, 'a+')))
  echo("log file error");


function get_ip() {
	// IP si internet partagé
	if (isset($_SERVER['HTTP_CLIENT_IP'])) {
		return $_SERVER['HTTP_CLIENT_IP'];
	}
	// IP derrière un proxy
	elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	// Sinon : IP normale
	else {
		return (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
	}
}





// mise à jour des stations --> debut
echo "create and update stations from velov data flow";
if($debugVerbose)
	echo "</BR></BR>--- ---- array parsing --- --- </BR>";

foreach($velovDataArray as $keyL0 => $valueL0)
{
	if($keyL0 == 'values')			
		foreach($valueL0 as $keyL1 => $valueL1){
			if($debugVerbose)
			{
				echo "</br> --- --- ---This is a  station --- </br>";
				echo "<br>station data from velov flow :<br>";		
				//echo "station ".$keyL1."</br>";
				var_dump($valueL1) ;	
				echo "</br>";
			}
			$stationNbEDock=0;
			$stationNbDock=0;
			foreach($valueL1 as $keyL2 => $valueL2){
				if($keyL2 == "available_bikes"){ $stationNbBike  = $valueL2;} //
				$stationNbEBike  = 0; 
				if($keyL2 == "available_bike_stands"){ $stationNbFreeDock   = $valueL2;} 
				$stationNbFreeEDock   = 0;
				if($keyL2 == "bike_stands"){ $stationNbDock   = $valueL2;} //
				$stationNbEDock   = 0;	
				$stationNbBikeOverflow  = 0;
				$stationNbEBikeOverflow  = 0;
				$stationKioskState  = 0; 
										
				if($keyL2 == "status"){ $stationState = $valueL2;} //	
				if($keyL2 == "name"){ $stationName = $valueL2;}	//
				if($keyL2 == "number"){ $stationCode = ltrim($valueL2, '0');}	//
				if($keyL2 == "lat"){ $stationLat = $valueL2 + 0;} 
				if($keyL2 == "lng"){ $stationLon = $valueL2 + 0;}					
				//echo "</br>".$keyL2."  : ".$valueL2 ;	

			}	

			if($debugVerbose)
			{

				//echo "</br>stationName:".$stationName;
				//echo " - "."stationCode:".$stationCode;
				//echo "</br>"."stationState:".$stationState;
				//echo "</br>"."stationLat:".$stationLat;
				//echo "</br>"."stationLon:".$stationLon;
				echo "</br>"."stationNbEDock:".($stationNbEDock+$stationNbDock);
				echo "</br>"."stationNbBike:".$stationNbBike;
				echo "</br>"."stationNbEBike:".$stationNbEBike;
				echo "</br>"."nbFreeDock:".$stationNbFreeDock;
				echo "</br>"."nbFreeEDock:".$stationNbFreeEDock;
				echo "</br>"."stationNbBikeOverflow:".$stationNbBikeOverflow;
				echo "</br>"."stationNbEBikeOverflow:".$stationNbEBikeOverflow;	
			}
			
			if ($result = mysqli_query($link, "SELECT * FROM velov_station where stationCode = '$stationCode'")) {
				if (mysqli_num_rows($result)>0)
				{//la station existe
						
						$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
						if($debugVerbose)
						{
							echo "</br></br> Station already exist in db : here we will have to compare and update";
							echo "</br>station data from local db :";
							echo "</br>"."TechId:".$row["id"];
							echo "</br>"."stationName:".$row["stationName"];
							echo "</br>"."stationCode:".$row["stationCode"];
							echo "</br>"."stationState:".$row["stationState"];
							echo "</br>"."stationLat:".$row["stationLat"];
							echo "</br>"."stationLon:".$row["stationLon"];
							echo "</br>"."stationNbEDock:".$row["stationNbEDock"];
							echo "</br>"."stationNbBike:".$row["stationNbBike"];
							echo "</br>"."stationNbEBike:".$row["stationNbEBike"];
							echo "</br>"."nbFreeDock:".$row["nbFreeDock"];
							echo "</br>"."nbFreeEDock:".$row["nbFreeEDock"];
							echo "</br>"."stationNbBikeOverflow:".$row["stationNbBikeOverflow"];
							echo "</br>"."stationNbEBikeOverflow:".$row["stationNbEBikeOverflow"];	
						}
						
						if 
						(
							$stationState == $row["stationState"] and
							$stationNbEDock+$stationNbDock	== $row["stationNbEDock"] and
							$stationNbBike == $row["stationNbBike"] and
							$stationNbEBike == $row["stationNbEBike"] and
							$stationNbFreeDock == $row["nbFreeDock"] and
							$stationNbFreeEDock == $row["nbFreeEDock"] and 
							$stationNbBikeOverflow == $row["stationNbBikeOverflow"] and 
							$stationNbEBikeOverflow == $row["stationNbEBikeOverflow"]
						)
						{ // Pas de changement - update pour topper que la station est tjs là";
							
							if($debug)
							{
							echo "</br>stationName : ".$stationName;
							echo " - "."stationCode : ".$stationCode;
							echo " - "."stationState : ".$stationState;
							echo "</br>pas de changement -> La station est tjs là<br>";	
							}
							
							$r = "UPDATE `velov_station` 
							SET 
								`stationLastView`=now()
							WHERE `id`='$row[id]'";
							//echo $r;
							if(!mysqli_query($link, $r))
							{
								printf("Errormessage: %s\n", mysqli_error($link));
							}
						}
						else
						{ // quelque chose à changé
							echo "</br>stationName : ".$stationName;
							echo " - "."stationCode : ".$stationCode;
							echo " - "."stationState : ".$stationState;
							echo "</br>Les données ont changé";	
							$row["stationLat"] = $row["stationLat"] +0;
							$row["stationLon"] = $row["stationLon"] +0;
							
								/*
								echo "<br> cond1:".(round($stationLat - $row["stationLat"],5));
								echo "<br> cond2:".(round($stationLon - $row["stationLon"],5));
								
								echo "<br> Lat - Before:".$row["stationLat"]." - After:" . $stationLat;
								echo "<br> Lat - Before:".gettype($row["stationLat"])." - After:" . gettype($stationLat);
								echo "<br> Lon - Before:".$row["stationLon"]." - After:" . $stationLon;
								echo "<br> Lon - Before:".gettype($row["stationLon"])." - After:" . gettype($stationLon);
								*/
							
							// check lat/lon round à 10 décimale pour les stations avec doc et si changement mettre à jour aussi l'adresse via Google geocode API...
							if( ((round($stationLat - $row["stationLat"],5)) != 0) or ((round($stationLon - $row["stationLon"],5)) !=0 ))
							{//la position de la station a changé
								echo "<br> La position a changé";
								echo "<br> cond1:".$stationLat <> $row["stationLat"];
								echo "<br> cond1:".$stationLon <> $row["stationLon"];
								echo "<br> Lat - Before:".$row["stationLat"]." - After:" . $stationLat;
								echo "<br> Lat - Before:".gettype($row["stationLat"])." - After:" . gettype($stationLat);
								echo "<br> Lon - Before:".$row["stationLon"]." - After:" . $stationLon;
								echo "<br> Lon - Before:".gettype($row["stationLon"])." - After:" . gettype($stationLon);
								
								$stationLocationHasChanged  = 1;
								
								if(false)//désactivé
								{
									/// recupérer l'adresse --> google geocode API
										
									$wsUrl = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='.$stationLat.','.$stationLon.'&key=AIzaSyBhVM63uEbuaccNCZ687XuAMVavQK4o-VQ';
									if($debugVerbose)
										echo "<br>".$wsUrl."<br>";	
									$googleGeocodeAPIRawData = file_get_contents($wsUrl);
									$googleGeocodeAPIDataArray = json_decode($googleGeocodeAPIRawData, true);

									if($debugVerbose)
									{
										echo "vardump</br>";
										var_dump($googleGeocodeAPIDataArray);	
									}
									
									if(count($googleGeocodeAPIDataArray)>3) //parce que lorsque le quota est atteint la reponse est un array(3)
									{
										//echo "</br> --- --- ---dépiller le retour google  --- </br>";
										foreach($googleGeocodeAPIDataArray as $keyL1 => $valueL1)
										{			
											foreach($valueL1 as $keyL2 => $valueL2){
												if(is_array($valueL2))
												{
													foreach($valueL2 as $keyL3 => $valueL3){
														if(!is_array($valueL3))
														{
															if($keyL3 == 'formatted_address')
																{
																	$stationAdress = mysqli_real_escape_string($link, $valueL3); //ici on à l'adresse
																	$quitter = 1;
																	break;
																}
														}
													}
													if($quitter){
														break;
													}
												}
											}
											if($quitter){
												break;
											}				
										}
										echo "<br> Adresse - Before:".$row["stationAdress"]." - After:" . $stationAdress;	
									}						
									else
									{
									$stationAdress = mysqli_real_escape_string($link, $row["stationAdress"]);
									}
								}
							}
							else
							{//la position de la station n'a pas changé
								$stationAdress = mysqli_real_escape_string($link, $row["stationAdress"]);
								$stationLocationHasChanged = $row["stationLocationHasChanged"];
							}

							
							
							if($stationNbEDock+$stationNbDock + $stationNbFreeDock + $stationNbFreeEDock+$stationNbBike+$stationNbEBike !=0)
							{	
								// si la station est signalée HS, alors on recupère le max de  "delta(max-min) quotidien" de la station depuis le signalement
								// 
								
								$resetStationHS = 0;
								if($row["stationSignaleHS"]==1)
								{
									error_log( date("Y-m-d H:i:s")." - Changement dans la station ".$stationCode." signalée HS");
									$resultHSQ = "SELECT max(`stationvelovMaxvelov`-`stationvelovMinvelov`) as maxDelta FROM `velov_station_min_velov` where `stationCode` = '$stationCode' AND `stationStatDate` >= date('$row[stationSignaleHSDate]')";
									//error_log( date("Y-m-d H:i:s").$resultHSQ);
									
									if ($resultHS = mysqli_query($link, $resultHSQ)	) 
									{
										if (mysqli_num_rows($resultHS)>0)
										{
											//la station a un historique
											$rowHS = mysqli_fetch_array($resultHS, MYSQLI_ASSOC);
											if($rowHS["maxDelta"] > 3)
											{
												$resetStationHS = 1 ;
												error_log("- et le deltaMaxMin est supérieur à 3(".$rowHS["maxDelta"].") --> suppr de l'indicateur HS");
											}
										}
									}
								}

						
								// mise à jour de la station				
								$r = "UPDATE `velov_station` 
								SET 
									`stationState`='$stationState' ,
									`stationLat` = '$stationLat', 
									`stationLon` = '$stationLon', 
									`stationNbEDock`='$stationNbEDock'+'$stationNbDock',
									`stationNbBike`='$stationNbBike',
									`stationNbEBike`='$stationNbEBike',
									`nbFreeDock`='$stationNbFreeDock',
									`nbFreeEDock`='$stationNbFreeEDock',
									`stationNbBikeOverflow`='$stationNbBikeOverflow',
									`stationNbEBikeOverflow`='$stationNbEBikeOverflow',";
								

								$r = $r . "
									`stationLastChange`=now(),";

								$r = $r . 
									"`stationLastView`=now(),
									`stationKioskState` = '$stationKioskState',
									`stationOperativeDate` = 
										(
										case 
											WHEN '$stationState' = 'OPEN' and '$row[stationOperativeDate]'='' then now() 
											WHEN '$stationState' = 'OPEN' and '$row[stationOperativeDate]' <>'' then 	'$row[stationOperativeDate]'				
										end
										),
									`stationLastComeBack` = 
										(
										case 
											when ('$row[stationLastChange]' < DATE_ADD(NOW(), INTERVAL -24 HOUR)) then now() 
											when ('$row[stationLastChange]' > DATE_ADD(NOW(), INTERVAL -24 HOUR)) and '$row[stationLastComeBack]' <> '' then '$row[stationLastComeBack]'
										end 
										),
									`stationLastChangeAtComeBack` = 
										(
										case 
											when ('$row[stationLastChange]' < DATE_ADD(NOW(), INTERVAL -24 HOUR)) then '$row[stationLastChange]'
											when ('$row[stationLastChange]' > DATE_ADD(NOW(), INTERVAL -24 HOUR)) and '$row[stationLastChangeAtComeBack]' <> ''  then '$row[stationLastChangeAtComeBack]'
										end 
										)
									,	
									`stationLastExit` = 
										(
										case 
											when ('$row[nbFreeEDock]' < '$stationNbFreeEDock' or '$row[nbFreeDock]' < '$stationNbFreeDock' or '$row[stationNbBikeOverflow]' > '$stationNbBikeOverflow' or '$row[stationNbEBikeOverflow]' > '$stationNbEBikeOverflow' ) 
												then now() 
												else '$row[stationLastExit]'
										end 
										),
									`stationSignaleHS` = 								
										(
										case 
											when 
											(
												(
													'$row[nbFreeEDock]' < '$stationNbFreeEDock' or '$row[nbFreeDock]' < '$stationNbFreeDock' or '$row[stationNbBikeOverflow]' > '$stationNbBikeOverflow' or '$row[stationNbEBikeOverflow]' > '$stationNbEBikeOverflow'
												) 
												and 
												(
													'$row[stationSignaleHSCount]'=1 
													or
													(
													'$resetStationHS'=1
													and 
													'$row[stationSignaleHSCount]' < 6 
													)
												)
											) 
											then 0
											else '$row[stationSignaleHS]'
										end 
										), 
									`stationSignaleHSDate`  =
										(
										case 
											when 
											(
												(
													'$row[nbFreeEDock]' < '$stationNbFreeEDock' or '$row[nbFreeDock]' < '$stationNbFreeDock' or '$row[stationNbBikeOverflow]' > '$stationNbBikeOverflow' or '$row[stationNbEBikeOverflow]' > '$stationNbEBikeOverflow'
												) 
												and 
												(
													'$row[stationSignaleHSCount]'=1 
													or
													(
													'$resetStationHS'=1
													and 
													'$row[stationSignaleHSCount]' < 6 
													)
												)
											) 
											then NULL
											else 
												case when ( '$row[stationSignaleHSDate]' = '')
													then NULL
													else '$row[stationSignaleHSDate]'
												end
										end 
										),
									`stationSignaleHSCount` =
										(
										case 
											when ('$row[nbFreeEDock]' < '$stationNbFreeEDock' or '$row[nbFreeDock]' < '$stationNbFreeDock' or '$row[stationNbBikeOverflow]' > '$stationNbBikeOverflow' or '$row[stationNbEBikeOverflow]' > '$stationNbEBikeOverflow' ) 
											then greatest(0,'$row[stationSignaleHSCount]' -1)
											else '$row[stationSignaleHSCount]'
										end
										),
									`stationLocationHasChanged` = '$stationLocationHasChanged'
								WHERE `id`='$row[id]'";
															
								if($debugVerbose)
								{
									echo "<br>";
									echo $r;
								}
								
								if(!mysqli_query($link, $r))
								{
									error_log( date("Y-m-d H:i:s")." - erreur lors de la mise à jour de la station ".$stationCode);
									printf("Errormessage: %s\n", mysqli_error($link));
								}
								
								
								if($stationState == $row["stationState"])
								{
									if($debugVerbose) echo "<br> Le status de la station n'a pas changé";					
								}
								else
								{
									$r = 
										"
											INSERT INTO `velov_station_status`
											(
												`id`,
												`stationCode`,
												`stationState`,
												`stationStatusDate`
											)
											VALUES
											(
												'$row[id]',
												'$row[stationCode]' ,
												'$stationState' ,
												now()			
											)
										";		
											
									if(!mysqli_query($link, $r))
									{		
										echo "<br>CreateStatusRow error";
										printf("Errormessage: %s\n", mysqli_error($link));
										if($debugVerbose) echo $r;
									}	
									else
									{
										if($debugVerbose) echo "<br> CreateStatusRow ok";
									}							
								}
								
							
								// 2 : génération du log fichier
								//$logstring = $logstring.date('H:i:s j/m/y').";".rtrim($r).";\r";
								$nbdocktmp = $stationNbEDock+$stationNbDock;
								
								$logstring = $logstring.date('j/m/y').";".date('H').";".date('i').";".date('s').";".$stationCode.";".$stationName.";".$stationState.";".$stationKioskState.";".$nbdocktmp.";";
								$logstring = $logstring.$stationNbBike.";".$stationNbEBike.";".$stationNbFreeDock.";".$stationNbFreeEDock.";".$stationNbBikeOverflow.";".$stationNbEBikeOverflow.";";
								$logstring = $logstring.$row["stationInsertedInDb"].";";
								if($row["stationOperativeDate"]=="")
								{
									$logstring = $logstring.date('y-m-j H:i:s').";";
								}
								else{					
									$logstring = $logstring.$row["stationOperativeDate"].";";	
								}
								$logstring = $logstring."\n";
								$stationvelovExit = max(0, $row['stationNbEBike'] - $stationNbEBike) + 
										max(0, $row['stationNbBike'] - $stationNbBike) + 
										max(0, $row['stationNbBikeOverflow'] - $stationNbBikeOverflow) + 
										max(0, $row['stationNbEBikeOverflow'] - $stationNbEBikeOverflow);

								$stationEvelovExit = max(0, $row['stationNbEBike'] - $stationNbEBike) + 
										max(0, $row['stationNbEBikeOverflow'] - $stationNbEBikeOverflow);								
								
								if($debugVerbose) echo "<br> stationvelovExit : $stationvelovExit dont VAE : $stationEvelovExit ";
								
								// Alimentation statistiques mvt de la station
								$r = 
								"
								INSERT 
									INTO `velov_station_min_velov` 
										(
											`stationCode`, 
											`stationStatDate`, 
											`stationvelovMinvelov`, 
											`stationvelovMaxvelov`, 
											`stationvelovMinEvelov`, 
											`stationvelovMinvelovOverflow`, 
											`stationvelovMaxvelovOverflow`, 
											`stationvelovMinEvelovOverflow`, 
											`stationvelovExit`,
											`stationEvelovExit`,
											`updateDate`
										) 
									VALUES 
										(
											'$stationCode',
											now(),
											'$stationNbBike' + '$stationNbBikeOverflow' +'$stationNbEBike' + '$stationNbEBikeOverflow' ,
											'$stationNbBike' + '$stationNbBikeOverflow' +'$stationNbEBike' + '$stationNbEBikeOverflow' ,
											'$stationNbEBike' + '$stationNbEBikeOverflow' ,									
											'$stationNbBikeOverflow' + '$stationNbEBikeOverflow' ,
											'$stationNbBikeOverflow' + '$stationNbEBikeOverflow' ,	
											'$stationNbEBikeOverflow' ,
											'$stationvelovExit',
											'$stationEvelovExit',
											now()
										) 
									ON DUPLICATE KEY UPDATE 
										stationCode = '$stationCode', 
										stationStatDate = now(), 
										stationvelovMinvelov = LEAST(stationvelovMinvelov, '$stationNbBike' + '$stationNbBikeOverflow' +'$stationNbEBike' + '$stationNbEBikeOverflow' ),
										stationvelovMaxvelov = greatest(stationvelovMaxvelov, '$stationNbBike' + '$stationNbBikeOverflow' +'$stationNbEBike' + '$stationNbEBikeOverflow' ),
										stationvelovMinEvelov = LEAST(stationvelovMinEvelov, '$stationNbEBike' + '$stationNbEBikeOverflow' ),
										stationvelovMinvelovOverflow = LEAST(stationvelovMinvelovOverflow, '$stationNbBikeOverflow' + '$stationNbEBikeOverflow' ),
										stationvelovMaxvelovOverflow = greatest(stationvelovMaxvelovOverflow, '$stationNbBikeOverflow' + '$stationNbEBikeOverflow' ),	
										stationvelovMinvelovOverflow = LEAST(stationvelovMinvelovOverflow, '$stationNbEBikeOverflow' ),
										stationvelovExit = stationvelovExit + '$stationvelovExit',								
										stationEvelovExit = stationEvelovExit + '$stationEvelovExit',	
										updateDate = now()
								";
								if($debugVerbose)
								{
									echo "<br>";
									echo $r;
								}
								if(!mysqli_query($link, $r))
								{
									printf("Errormessage: %s\n", mysqli_error($link));
								}			
								// Calcul du nombre de retrait détécté à chaque exécution du parser							
								// si il y a eu un retrait alors on incrémente le compteur		
										
								
								if(
									$row['stationNbBike'] > $stationNbBike
									or $row['stationNbEBike'] > $stationNbEBike
									or $row['stationNbBikeOverflow'] > $stationNbBikeOverflow 
									or $row['stationNbEBikeOverflow'] > $stationNbEBikeOverflow 							
								) 						
								{
									if($debugVerbose)							
									{		
										echo "<br> retrait ici? OUI";						
										echo "</br> velovExit init value =".$velovExit."</br>";	
										echo $row['stationNbBike'] ."</br>";
										echo $stationNbBike ."</br>";
										echo $row['stationNbEBike'] ."</br>";
										echo $stationNbEBike."</br>"; 
										echo "</br> nombre de retrait ici ="; 
										echo max(0,  $row['stationNbEBike'] - $stationNbEBike) 
											+ max(0, $row['stationNbBike'] - $stationNbBike) 
											+ max(0, $row['stationNbBikeOverflow'] - $stationNbBikeOverflow) 
											+ max(0, $row['stationNbEBikeOverflow'] - $stationNbEBikeOverflow);							
									}				
									$velovExit = $velovExit + 
										max(0, $row['stationNbEBike'] - $stationNbEBike) + 
										max(0, $row['stationNbBike'] - $stationNbBike) + 
										max(0, $row['stationNbBikeOverflow'] - $stationNbBikeOverflow) + 
										max(0, $row['stationNbEBikeOverflow'] - $stationNbEBikeOverflow);
										
									$EvelovExit = $EvelovExit + 
										max(0, $row['stationNbEBike'] - $stationNbEBike) + 
										max(0, $row['stationNbEBikeOverflow'] - $stationNbEBikeOverflow);	
										
								}
								
								if(
									$row['stationNbBike'] < $stationNbBike
									or $row['stationNbEBike'] < $stationNbEBike
									or $row['stationNbBikeOverflow'] < $stationNbBikeOverflow 
									or $row['stationNbEBikeOverflow'] < $stationNbEBikeOverflow 							
								) 
								{							
									if($debugVerbose)							
									{	
									echo "<br> Retour ici? Oui"; 
									echo "</br> velovReturn init value =".$velovReturn."</br>";	
									}
									
									$velovReturn = $velovReturn +
										min(0, $row['stationNbEBike'] - $stationNbEBike) + 
										min(0, $row['stationNbBike'] - $stationNbBike) + 
										min(0, $row['stationNbBikeOverflow'] - $stationNbBikeOverflow) + 
										min(0, $row['stationNbEBikeOverflow'] - $stationNbEBikeOverflow);	

									$EvelovReturn = $EvelovReturn + 
										min(0, $row['stationNbEBike'] - $stationNbEBike) + 
										min(0, $row['stationNbEBikeOverflow'] - $stationNbEBikeOverflow);									

										
									if($debugVerbose)							
									{									
										echo $row['stationNbBike'] ."</br>";
										echo $stationNbBike ."</br>";
										echo $row['stationNbEBike'] ."</br>";
										echo $stationNbEBike."</br>"; 
										echo "</br> nombre de retour ici ="; 
										echo min(0,  $row['stationNbEBike'] - $stationNbEBike) 
											+ min(0, $row['stationNbBike'] - $stationNbBike) 
											+ min(0, $row['stationNbBikeOverflow'] - $stationNbBikeOverflow) 
											+ min(0, $row['stationNbEBikeOverflow'] - $stationNbEBikeOverflow);							
									}							
								
								}
								echo " <br>--> updated<br>";
							}
							else echo "<br>pas de diapason - update skipped<br>";
						}	
				}
				else
				{//la station n'existe pas
					if ($stationNbEDock+$stationNbDock + $stationNbFreeDock + $stationNbFreeEDock+$stationNbBike+$stationNbEBike  > 0)
					{	
						$stationName = mysqli_real_escape_string($link, $stationName);
						echo "</br>stationName : ".$stationName;
						echo " - "."stationCode : ".$stationCode;
						echo " - "."stationState : ".$stationState;						
						echo " - Lat :".$stationLat;			
						echo " - Lon : ".$stationLon;
						
						
						/// recupérer l'adresse --> adresse.data.gouv.fr					
						$wsUrl = 'https://api-adresse.data.gouv.fr/reverse/?lat='.$stationLat.'&lon='.$stationLon.'&type=housenumber';
						if($debugVerbose) echo $wsUrl;
						$stationAdress = "Not Available";
						
						$googleGeocodeAPIRawData = file_get_contents($wsUrl);
						$googleGeocodeAPIDataArray = json_decode($googleGeocodeAPIRawData, true);

						if($debugVerbose)
						{
							echo "vardump</br>";
							var_dump($googleGeocodeAPIDataArray);	
						}
						$quitter = 0;
					
			
						if($debugVerbose) echo "</br> --- --- ---dépiller le retour ws  --- </br>";
						foreach($googleGeocodeAPIDataArray as $keyL1 => $valueL1)
						{
							if($keyL1 == 'features')
							{
								if($debugVerbose) echo "<br> inside features ";
								foreach($valueL1 as $keyL2 => $valueL2)
								{
									if($keyL2 == '0')
									{
										if($debugVerbose) echo "<br> inside 0 ";
										foreach($valueL2 as $keyL3 => $valueL3)
										{
											if($keyL3 == 'properties')
											{			
												if($debugVerbose) echo "<br> inside properties ";
												if($debugVerbose) var_dump($valueL3);									
												
												if(is_array($valueL3))
												{
													if( isset($valueL3['housenumber']) && isset($valueL3['street']) && isset($valueL3['postcode']) && isset($valueL3['city']))
														$stationAdress = $valueL3['housenumber'].", ".$valueL3['street'].", ".$valueL3['postcode']." ".$valueL3['city'];
													else
														$stationAdress = $valueL3['label'];
													
													$stationAdress = mysqli_real_escape_string($link, $stationAdress); //ici on à l'adresse
													$quitter = 1;
													break;
													
												}
											}
											if($quitter){
												break;
											}
										}
									}
									if($quitter){
										break;
									}						
								}	
							}
							if($quitter){
								break;
							}				
						}
					
							echo "Station Adress: ".$stationAdress."<br>";	
						
						$r = "
						INSERT 
						INTO `velov_station`(
							`stationName`, 
							`stationCode`, 
							`stationState`, 
							`stationLat`, 
							`stationLon`, 
							`stationNbEDock`, 
							`stationNbBike`, 
							`stationNbEBike`, 
							`nbFreeDock`, 
							`nbFreeEDock`, 
							`stationNbBikeOverflow`, 
							`stationNbEBikeOverflow`, 
							`stationLastChange`, 
							`stationLastView`,
							`stationKioskState`,
							`stationAdress`, 
							`stationOperativeDate`, 
							`stationLastExit`,
							`stationLocationHasChanged` 
							) 
						VALUES (
							'$stationName', 
							'$stationCode', 
							'$stationState', 
							'$stationLat', 
							'$stationLon', 
							'$stationNbEDock'+'$stationNbDock',
							'$stationNbBike', 
							'$stationNbEBike', 
							'$stationNbFreeDock', 
							'$stationNbFreeEDock', 
							'$stationNbBikeOverflow', 
							'$stationNbEBikeOverflow', 
							now(), 
							now(), 
							'$stationKioskState', 
							left('$stationAdress',300),
							case WHEN '$stationState' = 'OPEN' then now() else null end,
							now(),
							1
							)";
						
						if(!mysqli_query($link, $r))
						{
							printf("Errormessage: %s\n", mysqli_error($link));
						}
						else
						{
						printf ("New Record has id %d.\n", mysqli_insert_id($link));
						}
						

						$r = 
							"
								INSERT INTO `velov_station_status`
								(
									`id`,
									`stationCode`,
									`stationState`,
									`stationStatusDate`
								)
								VALUES
								(
									LAST_INSERT_ID(),
									'$stationCode' ,
									'$stationState' ,
									now()			
								)
							";		
								
						if(!mysqli_query($link, $r))
						{		
							echo "<br>CreateStatusRow error";
							printf("Errormessage: %s\n", mysqli_error($link));
							if($debugVerbose) echo $r;
						}	
						else
						{
							if($debugVerbose) echo "<br> CreateStatusRow ok";
						}
						
					
						
						// 2 : génération du log
						//$logstring = $logstring.date('H:i:s j/m/y').";".rtrim($r).";\r";
						$tmpdock = $stationNbEDock+$stationNbDock;
						$logstring = $logstring.date('j/m/y').";".date('H').";".date('i').";".date('s').";".$stationCode.";".$stationName.";".$stationState.";".$stationKioskState.";".$tmpdock.";";
						$logstring = $logstring.$stationNbBike.";".$stationNbEBike.";".$stationNbFreeDock.";".$stationNbFreeEDock.";".$stationNbBikeOverflow.";".$stationNbEBikeOverflow.";";
						$logstring = $logstring.date('y-m-j H:i:s').";";
						if($stationState == "OPEN")
						{
							$logstring = $logstring.date('y-m-j H:i:s').";";
						}
						else{					
							$logstring = $logstring.";";	
						}
						$logstring = $logstring."\n";
						
						echo "</br> station not found in db --> Created";

					}	
					else
					{
						echo "</br>stationName : ".$stationName;
						echo " - "."stationCode : ".$stationCode;
						echo " - "."stationState : ".$stationState;			
						$stationName = mysqli_real_escape_string($link, $stationName);
						echo " - Lat :".$stationLat;			
						echo " - Lon : ".$stationLon;
						echo "</br> station not found in db --> station has no dock --> skip<br>";
					}
				/* free result set */
				mysqli_free_result($result);
				}
			}	
			else{
				printf("Errormessage: %s\n", mysqli_error($link));
			}	
			//echo "</br>";
		}

}
// mise à jour des stations --> Fin

// Mise à jour de la table de stats sur les stations actives
// Alimentation statistiques mvt stations
	$r = 
	"
		INSERT INTO `velov_activ_station_stat`
		   ( `date`                           ,
				  `heure`                     ,
				  `nbStationUpdatedInThisHour`,
				  `nbStationUpdatedLAst3Hour` ,
				  `nbStationUpdatedLAst6Hour` ,
				  `nbStationAtThisDate`,
				  `nbrvelovExit`,
				  `nbrEvelovExit`,
				  `networkNbBike`,
				  `networkNbBikeOverflow`,
				  `networkEstimatedNbBike`,
				  `networkEstimatedNbBikeOverflow`,
				  `networkNbEBike`,
				  `networkNbEBikeOverflow`,
				  `networkEstimatedNbEBike`,
				  `networkEstimatedNbEBikeOverflow`				  
		   )
		   values
		   ( 	
				  now()           ,
				  hour(now()),
				  (
						 SELECT
								count(`id`) as nbs
						 FROM
								`velov_station`
						 where
								stationLastChange     > DATE_ADD(NOW(), INTERVAL -1 HOUR)
								and stationHidden     = 0
								and stationState      = 'OPEN'
								and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
				  )
				  ,
				  (
						 SELECT
								count(`id`) as nbs
						 FROM
								`velov_station`
						 where
								stationLastChange     > DATE_ADD(NOW(), INTERVAL -3 HOUR)
								and stationHidden     = 0
								and stationState      = 'OPEN'
								and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
				  )
				  ,
				  (
						 SELECT
								count(`id`) as nbs
						 FROM
								`velov_station`
						 where
								stationLastChange     > DATE_ADD(NOW(), INTERVAL -6 HOUR)
								and stationHidden     = 0
								and stationState      = 'OPEN'
								and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
				  )
				  ,
				  (
						 SELECT
								count(distinct `id`) as nbs
						 FROM
								`velov_station`
						 where
								stationHidden         = 0
								and stationState      = 'OPEN'
								and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
				  ),
				  $velovExit,
				  $EvelovExit,
				  (select sum(stationNbBike)+sum(stationNbEBike) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
				  (select sum(stationNbBikeOverflow)+sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ),
				  (		
					SELECT
						sum(`stationNbBike`         + `stationNbEBike` -        stationMinvelovNDay) as estimatedvelovNumber
					FROM `velov_station`
						LEFT JOIN
							(
                             SELECT
                                      `stationCode`                                                                      ,
                                      MIN( `stationvelovMinvelov` - stationvelovMinvelovOverflow ) AS stationMinvelovNDay,
                                      MIN(stationvelovMinvelovOverflow)                            AS stationvelovMinvelovOverflow
                             FROM
                                      `velov_station_min_velov`
                             WHERE
                                      1
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -3 DAY)
                             GROUP BY
                                      `stationCode`
							) AS min_velov
						ON min_velov.`stationCode` = `velov_station`.`stationCode`
					WHERE
						`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
						and stationHidden = 0				  
				  ),
				 (		
					SELECT
						sum(`stationNbBikeOverflow` + `stationNbEBikeOverflow`- stationvelovMinvelovOverflow) as  estimatedvelovNumberOverflowr
					FROM `velov_station`
						LEFT JOIN
							(
                             SELECT
                                      `stationCode`                                                                      ,
                                      MIN( `stationvelovMinvelov` - stationvelovMinvelovOverflow ) AS stationMinvelovNDay,
                                      MIN(stationvelovMinvelovOverflow)                            AS stationvelovMinvelovOverflow
                             FROM
                                      `velov_station_min_velov`
                             WHERE
                                      1
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -3 DAY)
                             GROUP BY
                                      `stationCode`
							) AS min_velov
						ON min_velov.`stationCode` = `velov_station`.`stationCode`
					WHERE
						`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
						and stationHidden = 0			  
				  ),
				  (select sum(stationNbEBike) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
				  (select sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ),
				  (		
					SELECT
						sum( `stationNbEBike` - stationMinEvelovNDay) as estimatedEvelovNumber
					FROM `velov_station`
						LEFT JOIN
							(
                             SELECT
                                      `stationCode`                                                                      ,
                                      MIN( `stationvelovMinEvelov` - stationvelovMinEvelovOverflow ) AS stationMinEvelovNDay,
                                      MIN(stationvelovMinEvelovOverflow)                            AS stationvelovMinEvelovOverflow
                             FROM
                                      `velov_station_min_velov`
                             WHERE
                                      1
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -3 DAY)
                             GROUP BY
                                      `stationCode`
							) AS min_velov
						ON min_velov.`stationCode` = `velov_station`.`stationCode`
					WHERE
						`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
						and stationHidden = 0				  
				  ),
				 (		
					SELECT
						sum(`stationNbEBikeOverflow`- stationvelovMinEvelovOverflow) as  estimatedEvelovNumberOverflowr
					FROM `velov_station`
						LEFT JOIN
							(
                             SELECT
                                      `stationCode`                                                                      ,
                                      MIN( `stationvelovMinEvelov` - stationvelovMinEvelovOverflow ) AS stationMinEvelovNDay,
                                      MIN(stationvelovMinEvelovOverflow)                            AS stationvelovMinEvelovOverflow
                             FROM
                                      `velov_station_min_velov`
                             WHERE
                                      1
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -3 DAY)
                             GROUP BY
                                      `stationCode`
							) AS min_velov
						ON min_velov.`stationCode` = `velov_station`.`stationCode`
					WHERE
						`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
						and stationHidden = 0			  
				  )					  
		   )
		ON DUPLICATE KEY UPDATE
		   `date`  =`date`  ,
		   `heure` = `heure`,
		   `nbStationUpdatedInThisHour`=(
				  SELECT
						 count(`id`) as nbs
				  FROM
						 `velov_station`
				  where
						 stationLastChange     > DATE_ADD(NOW(), INTERVAL -1 HOUR)
						 and stationHidden     = 0
						 and stationState      = 'OPEN'
						 and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
		   )
		   ,
		   `nbStationUpdatedLAst3Hour`=(
				  SELECT
						 count(`id`) as nbs
				  FROM
						 `velov_station`
				  where
						 stationLastChange     > DATE_ADD(NOW(), INTERVAL -3 HOUR)
						 and stationHidden     = 0
						 and stationState      = 'OPEN'
						 and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
		   )
		   ,
		   `nbStationUpdatedLAst6Hour`=(
				  SELECT
						 count(`id`) as nbs
				  FROM
						 `velov_station`
				  where
						 stationLastChange     > DATE_ADD(NOW(), INTERVAL -6 HOUR)
						 and stationHidden     = 0
						 and stationState      = 'OPEN'
						 and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
		   )
		   ,
		   `nbStationAtThisDate`=(
				  SELECT
						 count(distinct `id`) as nbs
				  FROM
						 `velov_station`
				  where
						 stationHidden         = 0
						 and stationState      = 'OPEN'
						 and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
		   ),
			`nbrvelovExit` = `nbrvelovExit` + $velovExit,
			`nbrEvelovExit` = `nbrEvelovExit` + $EvelovExit,			
			`networkNbBike` = (select sum(stationNbBike)+sum(stationNbEBike) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
			`networkNbBikeOverflow` = (select sum(stationNbBikeOverflow)+sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ),
			`networkEstimatedNbBike`	 = (		
					SELECT
						sum(`stationNbBike`         + `stationNbEBike` -        stationMinvelovNDay) as estimatedvelovNumber
					FROM `velov_station`
						LEFT JOIN
							(
                             SELECT
                                      `stationCode`                                                                      ,
                                      MIN( `stationvelovMinvelov` - stationvelovMinvelovOverflow ) AS stationMinvelovNDay,
                                      MIN(stationvelovMinvelovOverflow)                            AS stationvelovMinvelovOverflow
                             FROM
                                      `velov_station_min_velov`
                             WHERE
                                      1
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -3 DAY)
                             GROUP BY
                                      `stationCode`
							) AS min_velov
						ON min_velov.`stationCode` = `velov_station`.`stationCode`
					WHERE
						`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
						and stationHidden = 0				  
				  ),
			`networkEstimatedNbBikeOverflow`= (		
					SELECT
						sum(`stationNbBikeOverflow` + `stationNbEBikeOverflow`- stationvelovMinvelovOverflow) as  estimatedvelovNumberOverflowr
					FROM `velov_station`
						LEFT JOIN
							(
                             SELECT
                                      `stationCode`                                                                      ,
                                      MIN( `stationvelovMinvelov` - stationvelovMinvelovOverflow ) AS stationMinvelovNDay,
                                      MIN(stationvelovMinvelovOverflow)                            AS stationvelovMinvelovOverflow
                             FROM
                                      `velov_station_min_velov`
                             WHERE
                                      1
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -3 DAY)
                             GROUP BY
                                      `stationCode`
							) AS min_velov
						ON min_velov.`stationCode` = `velov_station`.`stationCode`
					WHERE
						`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
						and stationHidden = 0			  
				  ),
			`networkNbEBike` = (select sum(stationNbEBike) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
			`networkNbEBikeOverflow` = (select sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ),
			`networkEstimatedNbEBike`	 = (		
					SELECT
						sum( `stationNbEBike` -        stationMinEvelovNDay) as estimatedEvelovNumber
					FROM `velov_station`
						LEFT JOIN
							(
                             SELECT
                                      `stationCode`                                                                      ,
                                      MIN( `stationvelovMinEvelov` - stationvelovMinEvelovOverflow ) AS stationMinEvelovNDay,
                                      MIN(stationvelovMinEvelovOverflow)                            AS stationvelovMinEvelovOverflow
                             FROM
                                      `velov_station_min_velov`
                             WHERE
                                      1
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -3 DAY)
                             GROUP BY
                                      `stationCode`
							) AS min_velov
						ON min_velov.`stationCode` = `velov_station`.`stationCode`
					WHERE
						`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
						and stationHidden = 0				  
				  ),
			`networkEstimatedNbEBikeOverflow`= (		
					SELECT
						sum( `stationNbEBikeOverflow`- stationvelovMinEvelovOverflow) as  estimatedEvelovNumberOverflowr
					FROM `velov_station`
						LEFT JOIN
							(
                             SELECT
                                      `stationCode`                                                                      ,
                                      MIN( `stationvelovMinEvelov` - stationvelovMinEvelovOverflow ) AS stationMinEvelovNDay,
                                      MIN(stationvelovMinEvelovOverflow)                            AS stationvelovMinEvelovOverflow
                             FROM
                                      `velov_station_min_velov`
                             WHERE
                                      1
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -3 DAY)
                             GROUP BY
                                      `stationCode`
							) AS min_velov
						ON min_velov.`stationCode` = `velov_station`.`stationCode`
					WHERE
						`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
						and stationHidden = 0			  
				  )					  
	";
	//echo $r;
	if(!mysqli_query($link, $r))
	{
		printf("Errormessage: %s\n", mysqli_error($link));
	}

// Mise à jour de la table de stats sur les stations actives --> Fin

// mise à jour des infos reseau velov
// dernière mise à jour
$r = " UPDATE `velov_network` SET `Current_Value`=now(),`Max_Value`=now() WHERE `network_key` = 'LastUpdate'";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
}	

// maj nbr station active officiellement
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = (select count(id) from velov_station where `stationState` = 'OPEN' and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
	`Max_Value` = GREATEST(Max_Value,(select count(id) from velov_station where `stationState` = 'OPEN' and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ) ) 
WHERE `network_key` = 'operative_station_nbr' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

// maj nbr station inactive officiellement
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = (select count(id) from velov_station where `stationState` != 'OPEN' and `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0 ), 
	`Max_Value` = GREATEST(Max_Value,(select count(id) from velov_station where `stationState` != 'OPEN' and   `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  )),
	`Min_Value` = LEAST(Min_Value,(select count(id) from velov_station where  `stationState` != 'OPEN' and  `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)  and stationHidden = 0  )	) 
WHERE `network_key` = 'inactive_station_nbr' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

// maj nbr velov en stations
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = (select sum(stationNbBike)+sum(stationNbBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
	`Max_Value` = GREATEST(Max_Value,(select sum(stationNbBike)+sum(stationNbBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ) ) 
WHERE `network_key` = 'velov_nbr' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

// maj nbr velov en stations depuis le 01/07
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = (select sum(stationNbBike)+sum(stationNbBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
	`Max_Value` = GREATEST(Max_Value,(select sum(stationNbBike)+sum(stationNbBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ) ) 
WHERE `network_key` = 'velov_nbr2' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

// maj nbr velov VAE en stations
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = (select sum(stationNbEBike)+sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
	`Max_Value` = GREATEST(Max_Value,(select sum(stationNbEBike)+sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ) ) 
WHERE `network_key` = 'evelov_nbr' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

// maj nbr velov VAE en stations depuis le 01/07
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = (select sum(stationNbEBike)+sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ), 
	`Max_Value` = GREATEST(Max_Value,(select sum(stationNbEBike)+sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  ) ) 
WHERE `network_key` = 'evelov_nbr2' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

// maj nbr velov en overflow
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = (select sum(stationNbBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  )
WHERE `network_key` = 'velov_nbr_overflow' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

// maj nbr velov VAE en overflow
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = (select sum(stationNbEBikeOverflow) from velov_station where `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR) and stationHidden = 0  )
WHERE `network_key` = 'evelov_nbr_overflow' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

error_log( date("Y-m-d H:i:s")." - exit: ".$velovExit."(".$EvelovExit.") - return: ".-$velovReturn."(".-$EvelovReturn.")");

// maj nbr velov utilisés
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = GREATEST(0,`Current_Value` + $velovExit - $EvelovExit + $velovReturn - $EvelovReturn),
	`Max_Value` = GREATEST(Max_Value,GREATEST(0,`Current_Value` + $velovExit - $EvelovExit + $velovReturn - $EvelovReturn)),
	`Min_Value` = LEAST(Min_Value,GREATEST(0,`Current_Value` + $velovExit - $EvelovExit + $velovReturn - $EvelovReturn))
WHERE `network_key` = 'nbrvelovUtilises' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}

// maj nbr velov VAE utilisés
$r = 
"UPDATE `velov_network` 
SET 
	`Current_Value` = GREATEST(0,`Current_Value` + $EvelovExit + $EvelovReturn),
	`Max_Value` = GREATEST(Max_Value,GREATEST(0,`Current_Value` + $EvelovExit + $EvelovReturn)),
	`Min_Value` = LEAST(Min_Value,GREATEST(0,`Current_Value` + $EvelovExit + $EvelovReturn))
WHERE `network_key` = 'nbrEvelovUtilises' ";
if(!mysqli_query($link, $r))
{
	printf("Errormessage: %s\n", mysqli_error($link));
	//echo $r;
}



echo "</br>data updated";

// mise en cache des données pour l'api dans la session sql du parser pour reduire le nbr de connexions sql
include "./../inc/sqlQuery.inc.php";
$query = getapiQuery_web(3);	
if ($result = mysqli_query($link, $query)) 
{
	if (mysqli_num_rows($result)>0)
	{
		$n = 1;
		$size = mysqli_num_rows($result);
		$resultArray;

		while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
		{
			$resultArray[]=$row;
			$n = $n+1;			
		}	

		ob_start();
		echo json_encode($resultArray, JSON_HEX_APOS);
		$newPage = ob_get_contents();
		updatePageInCache("stationList.api."."web"."-"."3".".json", $newPage);
		ob_end_clean();
		
		//error_log( date("Y-m-d H:i:s")." - données d'api mise en cache par le parser - v="."web"." d=3");
	}
}

$query = getapiQuery_web(2);	
if ($result = mysqli_query($link, $query)) 
{
	if (mysqli_num_rows($result)>0)
	{
		$n = 1;
		$size = mysqli_num_rows($result);
		$resultArray;

		while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
		{
			$resultArray[]=$row;
			$n = $n+1;			
		}	

		ob_start();
		echo json_encode($resultArray, JSON_HEX_APOS);
		$newPage = ob_get_contents();
		updatePageInCache("stationList.api."."web"."-"."2".".json", $newPage);
		ob_end_clean();
		
		//error_log( date("Y-m-d H:i:s")." - données d'api mise en cache par le parser - v="."web"." d=2");
	}
}


mysqlClose($link);
InvalidCache();

// 3 : opérations sur le fichier...
if(fputs($openLogFile, $logstring)===FALSE)
echo("write log error");

// 4 : quand on a fini de l'utiliser, on ferme le fichier
fclose($openLogFile);


?>

</body>
</html>