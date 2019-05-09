<?php
	function mysqlConnect()
	{
		include "config.inc.php";		
		//DB connect
		@$link = mysqli_connect($server, $user, $password, $db);
		if (!$link) {
			error_log(date("Y-m-d H:i:s")." - Unable to connect mysql :".mysqli_connect_errno());
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 10');//300 seconds
			include 'maintenance.html';			
			exit;
		}
		else return $link;
	}

	function mysqlClose($link)
	{
		mysqli_close($link);
	}
	
	function getLastUpdate($link)
	{	
		if ($result = mysqli_query($link, "SELECT DATE_FORMAT(`Current_Value`,'%d/%m/%Y %H:%i' ) as Current_Value FROM `velov_network` WHERE `network_key` = 'LastUpdate'")) 
		{
			if (mysqli_num_rows($result)>0)
			{
				$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
				return $row["Current_Value"];
			}
		} 
	}
	
	function getStationCount($link)
	{
		$query = 
		"
		select 
			(SELECT current_value FROM `velov_network` WHERE `network_key` = 'inactive_station_nbr') stations,
			(SELECT max_value FROM `velov_network` WHERE `network_key` = 'inactive_station_nbr') stations_max,
			(SELECT current_value FROM `velov_network` WHERE `network_key` = 'operative_station_nbr') stations_active,
			(SELECT max_value FROM `velov_network` WHERE `network_key` = 'operative_station_nbr') stations_active_max
		from `velov_network`
		LIMIT 1
		";
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;
	}

	function getStationCountByOperativeDate($link)
	{

		/*$query = 
		"
			SELECT 
				count(`id`) as nbStationWeek, 
				date_format(`stationOperativeDate`, '%Y-%v') as week
			FROM `velov_station` 
			where stationState = 'Operative' 
				and stationHidden = 0
			group by date_format(`stationOperativeDate`, '%Y-%v')		
		";*/
		
		
		$query = 
		"
			SELECT
				COUNT(vss.id) AS nbStationWeek,
				STR_TO_DATE(
					CONCAT(vss.week, ' Monday'),
					'%x%v %W'
				) AS week
			FROM
				(
				SELECT
					velov_station_status.`id`,
					DATE_FORMAT(MIN(`stationStatusDate`),
					'%Y%v') AS WEEK
				FROM
					`velov_station_status`
				WHERE
					`velov_station_status`.stationState = 'OPEN'
				GROUP BY
					`velov_station_status`.id
			) vss
			INNER JOIN velov_station vs ON
				vs.id = vss.id
			WHERE
				vs.stationHidden = 0 AND vs.stationState = 'OPEN' AND vs.`stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
			GROUP BY
				STR_TO_DATE(
					CONCAT(vss.week, ' Monday'),
					'%x%v %W'
				)
		";
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;

		
	}
	
	function getRentalByDate($link)
	{

		$query = 
		"
			SELECT
					 `date`,
					 SUM(`nbrvelovExit`) nbLocation,
					 SUM(`nbrEvelovExit`) nbLocationVAE,
					 SUM(`nbrvelovExit`) - SUM(`nbrEvelovExit`) nbLocationMeca
			FROM
					 `velov_activ_station_stat`
			WHERE
					 `date` NOT IN
					 (
							SELECT DISTINCT
								   `date`
							FROM
								   `velov_activ_station_stat`
							WHERE
								   `nbrvelovExit` > 5000
								   AND DATE       < '2018-08-01'
					 )
					 AND DATE < DATE(NOW() )
			GROUP BY
					 `date`
			ORDER BY
					 `date` 	
		";
		
		/* La clause ci dessous de la requette permet d'éliminer de la série les incidents du printemps qui par leurs oscillation donnait des chiffres abérant
							 `date` NOT IN
					 (
							SELECT DISTINCT
								   `date`
							FROM
								   `velov_activ_station_stat`
							WHERE
								   `nbrvelovExit` > 5000
								   AND DATE       < '2018-08-01'
					 )
		*/
		
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;

		
	}
	
	function getStationCountByLastEvent($link, $event) //$event : any date : `stationLastChange`, `stationLastExit`, .... 
	{
		$query =  
		"
		SELECT count(`id`) as nbs, `stationState`, 'moins d\'une heure' as periode
		FROM `velov_station` 
		where ".$event." > DATE_ADD(NOW(), INTERVAL -1 HOUR) 
				and stationHidden = 0 
		group by `stationState`
		union
		SELECT count(`id`) as nbs, `stationState`, '1 à 3 heure' as periode 
		FROM `velov_station` 
		where ".$event." between DATE_ADD(NOW(), INTERVAL -3 HOUR) and DATE_ADD(NOW(), INTERVAL -1 HOUR)  
				and stationHidden = 0 
		group by `stationState`
		union
		SELECT count(`id`) as nbs, `stationState`, '3 à 12 heure' as periode
		FROM `velov_station` 
		where ".$event." between DATE_ADD(NOW(), INTERVAL -12 HOUR) and DATE_ADD(NOW(), INTERVAL -3 HOUR)  
				and stationHidden = 0 		
		group by `stationState`
		union
		SELECT count(`id`) as nbs, `stationState`, 'plus de 12 heure' as periode
		FROM `velov_station` 
		where ".$event." < DATE_ADD(NOW(), INTERVAL -12 HOUR)  
				and stationHidden = 0 
		group by `stationState`			
		";		
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
		{
			error_log(date("Y-m-d H:i:s")." - invalid request :".mysqli_error( $link ));
			return False;			
		}
	}
	
	function getActivStationPercentage($link)
	{
		// where `date` > '2018-02-13' --> pour ne pas prendre la première journée de stat qui est incomplète !!!
		$query =  
		"
			SELECT 
				`date` statDate,
				round(avg(`nbStationUpdatedInThisHour`/`nbStationAtThisDate`*100),1) as activePercent,
				round(avg(`nbStationUpdatedLAst3Hour`/`nbStationAtThisDate`*100),1) as activePercent3H,
				round(avg(`nbStationUpdatedLAst6Hour`/`nbStationAtThisDate`*100),1) as activePercent6H
			FROM `velov_activ_station_stat` 
			where `date` > '2018-02-13' 
				and `date` <
						case
							when DATE_FORMAT(now(), '%H') < 16 then DATE_ADD(NOW(), INTERVAL -1 DAY) 
							else now()
						end	
			group by `date`				
		";	

		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;		
	}
	
	function getActivStationPercentage2($link)
	{
		// where `date` > '2018-02-13' --> pour ne pas prendre la première journée de stat qui est incomplète !!!
		$query =  
		"
			SELECT 
				`date` statDate,
				round(avg(`nbStationUpdatedInThisHour`/`nbStationAtThisDate`*100),1) as activePercent,
				round(avg(`nbStationUpdatedLAst3Hour`/`nbStationAtThisDate`*100),1) as activePercent3H,
				round(avg(`nbStationUpdatedLAst6Hour`/`nbStationAtThisDate`*100),1) as activePercent6H
			FROM `velov_activ_station_stat` 
			where `date` > '2018-02-13' 
				and `date` <
						case
							when DATE_FORMAT(now(), '%H') < 18 then DATE_ADD(NOW(), INTERVAL -1 DAY) 
							else now()
						end			
			group by `date`				
		";	

		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;		
	}
	
	function getActivStationPercentageH($link)
	{
		// where `date` > '2018-02-13' --> pour ne pas prendre la première journée de stat qui est incomplète !!!
		$query =  
		"
			SELECT 
				str_to_date(concat(DATE_FORMAT(`date`,'%d/%m/%Y' ), ' ', `heure`, ':00'), '%d/%m/%Y %H:%i')
					as statDate,
				round(avg(`nbStationUpdatedInThisHour`/`nbStationAtThisDate`*100),1) as activePercent,
				round(avg(`nbStationUpdatedLAst3Hour`/`nbStationAtThisDate`*100),1) as activePercent3H,
				round(avg(`nbStationUpdatedLAst6Hour`/`nbStationAtThisDate`*100),1) as activePercent6H
			FROM `velov_activ_station_stat` 
			where `date` > '2018-03-13' 
				and `date` <
						case
							when DATE_FORMAT(now(), '%H') < 18 then DATE_ADD(NOW(), INTERVAL -1 DAY) 
							else now()
						end
			group by str_to_date(concat(DATE_FORMAT(`date`,'%d/%m/%Y' ), ' ', `heure`, ':00'), '%d/%m/%Y %H:%i')			
		";	

		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;		
	}
	
	
	function getvelovCount($link)
	{
		$query = 
		"select 
			(SELECT current_value FROM `velov_network` WHERE `network_key` = 'velov_nbr') velovs,
			(SELECT max_value FROM `velov_network` WHERE `network_key` = 'velov_nbr') velovs_max,
			(SELECT max_value FROM `velov_network` WHERE `network_key` = 'velov_nbr2') velovs_max_072018,
			(SELECT current_value FROM `velov_network` WHERE `network_key` = 'evelov_nbr') VAE,
			(SELECT max_value FROM `velov_network` WHERE `network_key` = 'evelov_nbr') VAE_Max,
			(SELECT max_value FROM `velov_network` WHERE `network_key` = 'evelov_nbr2') VAE_Max_072018,
			(SELECT current_value FROM `velov_network` WHERE `network_key` = 'velov_nbr_overflow') velovs_overflow,
			(SELECT current_value FROM `velov_network` WHERE `network_key` = 'evelov_nbr_overflow') VAE_overflow			
		from `velov_network`
		LIMIT 1
		";
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;
	}
	
	function getEstimatedvelovCount($link)
	{
		$query = 
		"
		SELECT
          sum(`stationNbBike`         + `stationNbEBike` -        stationMinvelovNDay) as estimatedvelovNumber,
          sum(`stationNbBikeOverflow` + `stationNbEBikeOverflow`- stationvelovMinvelovOverflow) as  estimatedvelovNumberOverflow
		FROM
          `velov_station`
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
                    )
                    AS min_velov
                    ON
                              min_velov.`stationCode` = `velov_station`.`stationCode`
		WHERE
          `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
          and stationHidden = 0
		";
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;
	}
	
	function getEstimatedvelovCount2D($link)
	{
		$query = 
		"
		SELECT
          sum(`stationNbBike`         + `stationNbEBike` -        stationMinvelovNDay) as estimatedvelovNumber,
          sum(`stationNbBikeOverflow` + `stationNbEBikeOverflow`- stationvelovMinvelovOverflow) as  estimatedvelovNumberOverflow
		FROM
          `velov_station`
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
                                      AND `stationStatDate` > DATE_ADD(NOW(), INTERVAL -2 DAY)
                             GROUP BY
                                      `stationCode`
                    )
                    AS min_velov
                    ON
                              min_velov.`stationCode` = `velov_station`.`stationCode`
		WHERE
          `stationLastView` > DATE_ADD(NOW(), INTERVAL -48 HOUR)
          and stationHidden = 0
		";
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;
	}
	
	function getEstimatedvelovInUse($link)
	{
		$query = 
		"select 
			(SELECT current_value FROM `velov_network` WHERE `network_key` = 'nbrvelovUtilises') velovInUse,
			(SELECT max_value FROM `velov_network` WHERE `network_key` = 'nbrvelovUtilises') maxvelovInUse,			
			(SELECT current_value FROM `velov_network` WHERE `network_key` = 'nbrEvelovUtilises') evelovInUse,
			(SELECT max_value FROM `velov_network` WHERE `network_key` = 'nbrEvelovUtilises') maxEvelovInUse
		from `velov_network`
		LIMIT 1
		";
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;
	}	
	
	function getvelovNbrStats($link){
	
		$query = 
			"
			SELECT 
				`date`,
				min(`networkNbBike`) minvelov,
				max(`networkNbBike`) maxvelov,
				round(avg(`networkNbBike`)) avgvelov,
				min(`networkNbBikeOverflow`) minvelovOverflow,
				max(`networkNbBikeOverflow`) maxvelovOverflow,
				round(avg(`networkNbBikeOverflow`)) avgvelovOverflow,    
				min(`networkEstimatedNbBike`) minEstimatedvelov,
				max(`networkEstimatedNbBike`) maxEstimatedvelov,
				round(avg(`networkEstimatedNbBike`)) avgEstimatedvelov,    
				min(`networkEstimatedNbBikeOverflow`) minEstimatedvelovOverflow,
				max(`networkEstimatedNbBikeOverflow`) maxEstimatedvelovOverflow,
				round(avg(`networkEstimatedNbBikeOverflow`)) avgEstimatedvelovOverflow,
				min(`networkNbBike` - `networkEstimatedNbBike`) minEstimatedUnavailablevelov,
				max(`networkNbBike` - `networkEstimatedNbBike`) maxEstimatedUnavailablevelov,
				round(avg(`networkNbBike`-`networkEstimatedNbBike`)) avgEstimatedUnavailablevelov				
			FROM `velov_activ_station_stat` 
			WHERE 
				`date` > '2018-02-13'
			group by `date`
			order by `date`
			";
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;
	}
	
	function getEvelovNbrStats($link){
	
		$query = 
			"
			SELECT 
				`date`,
				min(`networkNbEBike`) minvelov,
				max(`networkNbEBike`) maxvelov,
				round(avg(`networkNbEBike`)) avgvelov,
				min(`networkNbEBikeOverflow`) minvelovOverflow,
				max(`networkNbEBikeOverflow`) maxvelovOverflow,
				round(avg(`networkNbEBikeOverflow`)) avgvelovOverflow,    
				min(`networkEstimatedNbEBike`) minEstimatedvelov,
				max(`networkEstimatedNbEBike`) maxEstimatedvelov,
				round(avg(`networkEstimatedNbEBike`)) avgEstimatedvelov,    
				min(`networkEstimatedNbEBikeOverflow`) minEstimatedvelovOverflow,
				max(`networkEstimatedNbEBikeOverflow`) maxEstimatedvelovOverflow,
				round(avg(`networkEstimatedNbEBikeOverflow`)) avgEstimatedvelovOverflow,
				min(`networkNbEBike` - `networkEstimatedNbEBike`) minEstimatedUnavailablevelov,
				max(`networkNbEBike` - `networkEstimatedNbEBike`) maxEstimatedUnavailablevelov,
				round(avg(`networkNbEBike`-`networkEstimatedNbEBike`)) avgEstimatedUnavailablevelov				
			FROM `velov_activ_station_stat` 
			WHERE 
				`date` > '2018-02-13'
			group by `date`
			order by `date`
			";
		
		if ($result = mysqli_query($link, $query)) 
			return $result;
		else	
			return False;
	}
	
	function getMovedStationList($link){
		return getStationList($link, "`stationLocationHasChanged` = 1" , "order by `stationInsertedInDb` desc");
	}		
	
	function getAllStationList($link){
		return getStationList($link, "" , "order by `stationLastChange` desc");
	}
	
	function getNewStationList($link){
		return getStationList($link, "`stationInsertedInDb` > DATE_ADD(NOW(), INTERVAL -5 DAY)" , "order by `stationInsertedInDb` desc");
	}	
	
	//private
	function getStationList($link, $filter, $sort)
	{
		
		$query = 	
		"
			SELECT
				   `id`                                                                                    ,
				   `stationName`                                                                           ,
				   `velov_station`.`stationCode`                                                           ,
				   `stationState`                                                                          ,
				   `stationAdress`                                                                         ,
				   `stationLat`                                                                            ,
				   `stationLon`                                                                            ,
				   `stationNbEDock`                                                                        ,
				   `stationNbBike`                                                                         ,
				   `stationNbEBike`                                                                        ,
				   `nbFreeDock`                                                                            ,
				   `nbFreeEDock`                                                                           ,
				   `stationNbBikeOverflow`                                                                 ,
				   `stationNbEBikeOverflow`                                                                ,
				   DATE_FORMAT(`stationLastChange`,'%d/%m/%Y %H:%i') as stationLastChange                  ,
				   DATE_FORMAT(`stationInsertedInDb`,'%d/%m/%Y' )    as stationInsertedInDb                ,
				   timediff(now() , `stationLastChange`)             as timediff                           ,
				   hour(timediff(now() , `stationLastChange`))       as hourdiff                           ,
				   `stationKioskState`                                                                     ,
				   DATE_FORMAT(`stationOperativeDate`,'%d/%m/%Y' ) as stationOperativeDate                 ,
				   timediff(now() , `stationLastComeBack`)         as timeSinceLastComeBack                ,
				   `stationLastChangeAtComeBack`                                                           ,
				   timediff(`stationLastComeBack` , `stationLastChangeAtComeBack`)as stationUnavailableFor ,
				   DATE_FORMAT(stationLastExit,'%d/%m/%Y %H:%i')                  as stationLastExit       ,
				   timediff(sysdate() , `stationLastExit`)                        as lastExistDiff         ,
				   hour(timediff(now() , `stationLastExit`))                      as hourLastExistDiff     ,
				   stationAvgHourBetweenExit                                                               ,
				   stationAvgHourBetweenComeBack                                                           ,
				   stationSignaleHS                                                                        ,
				   DATE_FORMAT(`stationSignaleHSDate`,'%d/%m/%Y') as stationSignaleHSDate                  ,
				   DATE_FORMAT(`stationSignaleHSDate`,'%H:%i')    as stationSignaleHSHeure                 ,
				   4 - stationSignaleHSCount                      as nrRetraitDepuisSignalement            ,
				   `stationSignaledElectrified`                                                            ,
				   `stationSignaledElectrifiedDate`                                                        ,
				   stationNbBike + stationNbEBike + stationNbBikeOverflow + stationNbEBikeOverflow  as station_nb_bike,
				   stationMinvelovNDay
			FROM       
				`velov_station` LEFT JOIN 
				   (
							SELECT
									 `stationCode`,
									 min(`stationvelovMinvelov`) as stationMinvelovNDay
							FROM
									 `velov_station_min_velov`
							wHERE
									 1
									 and `stationStatDate` > DATE_ADD(NOW(), INTERVAL -4 DAY)
							group by
									 `stationCode`
				   ) as min_velov 
				   ON min_velov.`stationCode`  = `velov_station`.`stationCode`
			where
					`stationNbEDock`+
					  `stationNbBike`+
					  `stationNbEBike`+
					  `nbFreeDock`+
					  `nbFreeEDock` > 0 
				   and stationHidden           = 0
		";
		
		if($filter!="")
			$query= $query." and ".$filter;		
		
		if($sort!="")
			$query= $query." ".$sort;
		
		return $mysqliResult = mysqli_query($link, $query);		
	}



?>