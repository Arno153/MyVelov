<?php

function getapiQuery_web($dureeEstimation)
{
	return 
		"
			SELECT 
				concat(`stationName`, '-', `velov_station`.`stationCode`) as station,					
				
				`stationLat`,
				`stationLon`, 
				`stationNbBike`, 					
				`stationNbBikeOverflow`,	
				`stationNbEBike`,
				`stationNbEBikeOverflow`, 
				`nbFreeEDock`,
				`nbFreeDock`,
				timediff(now() , `stationLastChange`)             as timediff                           ,					
				hour(timediff(now() , `stationLastChange`))       as hourdiff ,						
				`stationState`,
				stationAdress,						
				timediff(sysdate() , `stationLastExit`)                        as lastExistDiff         ,					
				hour(timediff(now() , `stationLastExit`))                      as hourLastExistDiff     ,
				`velov_station`.`stationCode`,
				stationSignaleHS,
				DATE_FORMAT(`stationSignaleHSDate`,'%d/%m/%Y') as stationSignaleHSDate                  ,
				DATE_FORMAT(`stationSignaleHSDate`,'%H:%i')    as stationSignaleHSHeure                 ,	
				(case when stationSignaleHS = 1
					then 10 - stationSignaleHSCount
					else 0
				end) as nrRetraitDepuisSignalement,
				`stationSignaledElectrified` as stationConnected, 
				`stationSignaledElectrifiedDate` as stationConnectionDate,					
				stationNbBike + stationNbEBike + stationNbBikeOverflow + stationNbEBikeOverflow  as tot_station_nb_bike,
				(case when stationMinvelovNDay IS NULL
					then stationNbBike+ stationNbEBike
					else stationMinvelovNDay
				end) as stationMinvelovNDay
				,
				(case when stationMinEvelovNDay IS NULL
					then stationNbEBike
					else stationMinEvelovNDay
				end) as stationMinEvelovNDay
				,				
				(case when stationvelovMinvelovOverflow IS NULL
					then stationNbBikeOverflow+ stationNbEBikeOverflow
					else stationvelovMinvelovOverflow
				end) as stationvelovMinvelovOverflow,	
				(case when stationvelovMinEvelovOverflow IS NULL
					then stationNbEBikeOverflow
					else stationvelovMinEvelovOverflow
				end) as stationvelovMinEvelovOverflow				
			FROM 
				`velov_station` LEFT JOIN 
				   (
							SELECT
									`stationCode`,
									MIN(`stationvelovMinvelov` - stationvelovMinvelovOverflow) AS stationMinvelovNDay,
									MIN(`stationvelovMinEvelov` - stationvelovMinEvelovOverflow) AS stationMinEvelovNDay,
									MIN( stationvelovMinvelovOverflow ) AS stationvelovMinvelovOverflow,
									MIN( stationvelovMinEvelovOverflow ) AS stationvelovMinEvelovOverflow
							FROM
									 `velov_station_min_velov`
							wHERE
									 1
									 and `stationStatDate` > DATE_ADD(NOW(), INTERVAL -'$dureeEstimation' DAY)
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
				and stationHidden = 0
		";
}

function getapiQuery_heatmap($dureeEstimation)
{
	return
		"
			SELECT
			  vs.`stationCode`,
			  `stationStatDate`,
			  (case when `stationvelovExit` is not null then `stationvelovExit` else 0 end ) as stationvelovExit ,
			  `stationLat`,
			  `stationLon`,
			  `stationState`
			FROM
			  `velov_station` vs 
			  left join 
			  (
				  select * 
				  from `velov_station_min_velov`  
				  where `stationStatDate` between DATE_ADD(NOW(), INTERVAL -'$dureeEstimation'-1 DAY) and DATE_ADD(NOW(), INTERVAL -'$dureeEstimation' DAY)
			   ) vm
				on vs.`stationCode` = vm.`stationCode` 
			WHERE  
			  `stationHidden` = 0  
			order by 1, 2 asc
		";
	
}

?>