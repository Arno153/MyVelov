<!DOCTYPE html> 
<?php	
	include "./inc/mysql.inc.php";
	include "./inc/cacheMgt.inc.php";	
	
	if	( 
			isCacheValid('index.php.1') 
			and isCacheValid('index.php.2') 
			and isCacheValid('index.php.3') 
			and isCacheValid('index.php.4') 
			and isCacheValid('index.php.6') 
			and isCacheValid('lastUpdateText') 
		)
		$cacheValide = true;
	else
	{
		$cacheValide = False;
		//$link = mysqlConnect();
	}	
	
	if( isCacheValid1H('index.php.5'))
		$cacheValide1H = true;
	else
		$cacheValide1H = False;

	if( !($cacheValide and $cacheValide1H))
	{
		$link = mysqlConnect();
	}	
		
	
?>	
<html lang="fr">
  <head>
	<!-- Global site tag (gtag.js) - Google Analytics -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=UA-113973828-2"></script>
	<script>
	  window.dataLayer = window.dataLayer || [];
	  function gtag(){dataLayer.push(arguments);}
	  gtag('js', new Date());

	  gtag('config', 'UA-113973828-2');
	</script>
    <title>velov Lyon - Stations disponibles, ouvertures, nombre de velov... (site officieux)</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Deploiment du nouveau velov 2018, nouvelles stations, nouveaux velos et VAE, stats de fonctionnement..." />
	<meta name="keywords" content="velov, velov 2018, velov2018, nouveau velov, velov 2, cartes, station, vélo, Lyon, fonctionnent, HS, en panne" />
	<meta name="robots" content="index, follow">
	
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#00a300">
	<meta name="msapplication-TileImage" content="/mstile-144x144.png">
	<meta name="theme-color" content="#ffffff">
	
	<link rel="stylesheet" media="all" href="./css/joujouvelov.css?<?php echo filemtime('./css/joujouvelov.css');?>">	
	<script src="./inc/sorttable.js"></script>
	<!-- Plotly.js -->
	<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
  </head>
  <body>
	<?php
	$lofFile='./.maintenance';
	if(file_exists ($lofFile) )
	{
		echo 
			"
			<div class='maintenance'>
				<!-- !!! Mode maintenance actif !!! -->
					Mon processus de collecte des données velov est actuellement perturbé.</br>
					Les statistiques d'utilisation du 10 au 13 sont indisponible, depuis elles sont sous estimées par rapport au reste de la série. </br>
					Les autres données, moins sensible à la régularité de la collecte, sont affectées plus marginalement. 
			</div>	
			";
	}
	
	include "./inc/menu.inc.php";
	
	echo "<div class='nav-refresh'>(Dernière collecte: ";
	if($cacheValide == true)
		getPageFromCache('lastUpdateText');
	else
	{
		ob_start();
		echo getLastUpdate($link);
		$newPage = ob_get_contents();
		updatePageInCache('lastUpdateText', $newPage);
		ob_end_clean(); 
		echo $newPage;			
	}
	echo ")";			
?>	
	</div>


	<div class="left-widget left200">	
		<h1 class="widget-title">Nombre de stations</h1>
		<TABLE class="table-compact">
			<TR>
			<TH>Fermées & En travaux</TH>
			<TH>Actives</TH>
			</TR>	
			<?php
				if($cacheValide == true)
					getPageFromCache('index.php.1');
				else
				{
					ob_start();			
					// debut mise en cache
					if ($result = getStationCount($link)) 
					{
						if (mysqli_num_rows($result)>0)
						{
							while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
								{
									echo "<TR>";
									echo "<TD>".$row["stations"]." ( Max: ".$row["stations_max"].")</TD>";	
									echo "<TD>".$row["stations_active"]." ( Max: ".$row["stations_active_max"].")</TD>"; 
										echo "</TR>";	
								}				
						}
					}
					//fin mise en cache
					$newPage = ob_get_contents(); //recup contenu à cacher
					updatePageInCache('index.php.1', $newPage); //mise en cache
					ob_end_clean(); //ménage cache memoire
					echo $newPage;	//affichage live		
				}

			?>
		</TABLE>
		<p class="notes">* status officiel</p>
	</div>
	<div class="left-widget left200 col3">	
	
		<?php
			if($cacheValide == true)
				getPageFromCache('index.php.2');
			else
			{
				ob_start();			
				// debut mise en cache	
		?>
		<h1 class="widget-title">Nombre de velov</h1>
		<TABLE class="table-compact">
		<TR>
		<TH>Nbr de velov en station</TH>
		<TH>Nbr de velov Elec. en station</TH>
		</TR>	
		<?php
				if ($result = getvelovCount($link)) 
				{
						if (mysqli_num_rows($result)>0)
						{
							while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
								{
									echo "<TR>";	
									echo "<TD>".($row["velovs"]-$row["velovs_overflow"])." ( + Park+: ".$row["velovs_overflow"].")<br> ( Max: ".$row["velovs_max"].")</TD>";	
									echo "<TD>".($row["VAE"]-$row["VAE_overflow"])." ( + Park+: ".$row["VAE_overflow"].")<br> ( Max: ".$row["VAE_Max"].")</TD>"; 
									echo "</TR>";	
								}				
						}
				}
		?>
		</TABLE>
		<br>

		<TABLE class="table-compact">
		<TR>
		<TH>Nombre estimé de velov disponibles en station</TH>
		</TR>	
		<?php
				if ($result = getEstimatedvelovCount($link)) 
				{
						if (mysqli_num_rows($result)>0)
						{
							while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
								{									
									$estimatedvelovNumber3D =$row["estimatedvelovNumber"];
									$estimatedvelovNumberOverflow3D = $row["estimatedvelovNumberOverflow"];
								}				
						}
				}
				
				if ($result = getEstimatedvelovCount2D($link)) 
				{
						if (mysqli_num_rows($result)>0)
						{
							while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
								{									
									$estimatedvelovNumber2D =$row["estimatedvelovNumber"];
									$estimatedvelovNumberOverflow2D = $row["estimatedvelovNumberOverflow"];
								}				
						}
				}				
				
				echo "<TR>";	
				echo "
						<TD>
							Estimation sur 3J: ".$estimatedvelovNumber3D." ( + Park+: ".$estimatedvelovNumberOverflow3D.")<br>
							Estimation sur 2J: ".$estimatedvelovNumber2D." ( + Park+: ".$estimatedvelovNumberOverflow2D.")
						</TD>";	
				echo "</TR>";	
				
		?>		
		</TABLE>
		<p class="notes">* les velov en cours d'utilisation ne sont pas comptés</p>		
		<p class="notes">* le nombre estimé de velov en station est obtenu en soustrayant le nombre min de velov enregistré par chaque station sur les 2 / 3 derniers jours</p>	
		<br>		
		<TABLE class="table-compact" >
		<TR>
		<TH>Nombre estimé de velov en cours d'utilisation</TH>
		</TR>	
		<?php
				if ($result = getEstimatedvelovInUse($link)) 
				{
					if (mysqli_num_rows($result)>0)
					{
						$row = mysqli_fetch_array($result, MYSQLI_ASSOC);	
					}
				}
				
				echo "<TR>";	
				echo "
						<TD>
							velov loués: ".$row["velovInUse"]."  -  VAE loués: ".$row["evelovInUse"]."
						</TD>";	
				echo "</TR>";	
				
		?>		
		</TABLE>		
				
		<?php
				//fin mise en cache
				$newPage = ob_get_contents(); //recup contenu à cacher
				updatePageInCache('index.php.2', $newPage); //mise en cache
				ob_end_clean(); //ménage cache memoire
				echo $newPage;	//affichage live		
			}				
		?>

	</div>
	
	<div class="left-widget left360 col2">
		<h1 class="widget-title">Stations par dernier mouvement</h1>
		<TABLE class="table-compact">
			<TR>
				<TH>Status officiel</TH>
				<TH>Stations</TH>
				<TH>Dernier mouvement</TH>
			</TR>
			<?php 
				if($cacheValide == true)
					getPageFromCache('index.php.3');
				else
				{
					ob_start();			
					// debut mise en cache				
					if ($result = getStationCountByLastEvent($link, "`stationLastChange`" ))
					{
						if (mysqli_num_rows($result)>0)
						{
							while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
							{					
								echo "<TR>";
								echo "<TD>";
								if($row["stationState"]=="Operative")
								{
									echo "Actives";
								}
								Elseif ($row["stationState"]=="Work in progress")
								{
									echo "En Travaux";
								}
								Elseif ($row["stationState"]=="Close")
								{
									echo "Fermées";
								}								
								else
								{
									echo $row["stationState"];
								}								
								echo "</TD><TD>";
								echo $row["nbs"];
								echo "</TD><TD>";
								echo $row["periode"];					
								echo "</TD>";
								echo "</TR>";	
							}				
						}
					}
					//fin mise en cache
					$newPage = ob_get_contents(); //recup contenu à cacher
					updatePageInCache('index.php.3', $newPage); //mise en cache
					ob_end_clean(); //ménage cache memoire
					echo $newPage;	//affichage live		
				}					
			?>
		</TABLE>
		<p class="notes">*l'absence de mouvement ne présume pas du dysfonctionnement d'une station</p>
	</div>
	
		<div class="left-widget left360 col2">
		<h1 class="widget-title">Stations par dernier retrait</h1>
		<TABLE class="table-compact">
			<TR>
				<TH>Status officiel</TH>
				<TH>Stations</TH>
				<TH>Dernier retrait</TH>
			</TR>
			<?php 
				if($cacheValide == true)
					getPageFromCache('index.php.4');
				else
				{
					ob_start();			
					// debut mise en cache					
					if ($result = getStationCountByLastEvent($link, "`stationLastExit`" ))
					{
						if (mysqli_num_rows($result)>0)
						{
							while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
							{					
								echo "<TR>";
								echo "<TD>";
								if($row["stationState"]=="Operative")
								{
									echo "Actives";
								}
								Elseif ($row["stationState"]=="Work in progress")
								{
									echo "En Travaux";
								}
								Elseif ($row["stationState"]=="Close")
								{
									echo "Fermées";
								}								
								else
								{
									echo $row["stationState"];
								}								
								echo "</TD><TD>";
								echo $row["nbs"];
								echo "</TD><TD>";
								echo $row["periode"];					
								echo "</TD>";
								echo "</TR>";	
							}				
						}
					}
					//fin mise en cache
					$newPage = ob_get_contents(); //recup contenu à cacher
					updatePageInCache('index.php.4', $newPage); //mise en cache
					ob_end_clean(); //ménage cache memoire
					echo $newPage;	//affichage live		
				}					
		?>
		</TABLE>
		<p class="notes">*l'absence de mouvement ne présume pas du dysfonctionnement d'une station</p>
	</div>
	
		
		<?php
			if($cacheValide1H == true)
				getPageFromCache('index.php.5');
			else
			{
				ob_start();			
				// debut mise en cache				
				if ($result = getStationCountByOperativeDate($link)) 
				{
					if (mysqli_num_rows($result)>0)
					{
						//détermine le nombre de colonnes
						$nbcol=mysqli_num_rows($result);				
						while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
						{
							$tablo[] = $row;
						}				
					}
				}
				
				echo "<div id='GraphOuvStationSemaine' class='left-widget widgetGraph'> <button id='button_GraphOuvStationSemaine' class='graphFullScreenButton'>+</button>";
				echo "<script>";
				echo "var data = [{";
				
				$nb=count($tablo);
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['week'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					echo '"'.$tablo[$i]['nbStationWeek'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				echo "  type: 'bar'},";
				
				//
				
				$nb=count($tablo);
				$nbstation = 0;
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo '{x: [';
					
					echo '"'.$tablo[$i]['week'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					$nbstation = $tablo[$i]['nbStationWeek'] + $nbstation;
					echo '"'.$nbstation.'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				echo " yaxis: 'y2', type: 'scatter'}];";
				
				
				//
				
				echo "
					var layout = 
						{ 
							title: 'Ouvertures de stations (par semaine)', 
							xaxis:
							{
								tickformat: '%Y %W',
							},
							yaxis: {
									tickfont: {color: 'rgb(31, 119, 180)'},									
									showgrid: false
								  }, 
							yaxis2: {						
								overlaying: 'y', 
								tickfont: {color: 'rgb(255, 127, 14)'}, 
								side: 'right',
								showgrid: false
									},					
							paper_bgcolor: '#f8f9fa', 
							plot_bgcolor: '#f8f9fa',
							showlegend: false,
							margin: {
										l: 20,
										r: 40,
										b: 30,
										t: 30,
										pad: 4
									  }
						};
				";
				
				echo "Plotly.newPlot('GraphOuvStationSemaine', data, layout,{displayModeBar: false});";
				echo '</script>';
				echo "<p class='notes'>* Stations opérationnelles, suivant la date de première ouverture</p>";
				echo "</div>";
				
				//
				$tablo=[];
				if ($result = getRentalByDate($link)) 
				{
					if (mysqli_num_rows($result)>0)
					{
						//détermine le nombre de colonnes
						$nbcol=mysqli_num_rows($result);				
						while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
						{
							$tablo[] = $row;
						}				
					}
				}
				
				//
				
				echo "<div id='GraphEvolutionUtilisation' class='left-widget widgetGraph'><button id='button_GraphEvolutionUtilisation' class='graphFullScreenButton'>+</button>";
				echo "<script>";
				echo "var data = [{";
				
				$nb=count($tablo);
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					 ;
					echo '"'.$tablo[$i]['nbLocation'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				echo "  type: 'scatter', name: 'Tous'},{";

				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					 ;
					echo '"'.$tablo[$i]['nbLocationMeca'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				echo "  type: 'scatter', visible: 'legendonly', name: 'velov'}, {";	
				
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					 ;
					echo '"'.$tablo[$i]['nbLocationVAE'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				echo "  type: 'scatter', name: 'VAE'}];";				

	
				echo "
				var layout = 
				{ 
					title: 'Nombre estimé d\'utilisations', 
					paper_bgcolor: '#f8f9fa', 
					plot_bgcolor: '#f8f9fa',					
					showlegend: true,
					margin: {
								l: 30,
								r: 20,
								b: 30,
								t: 30,
								pad: 4
							  }
				};";
				
				echo "Plotly.newPlot('GraphEvolutionUtilisation', data, layout,{displayModeBar: false});";
				echo '</script>';
				echo "<p class='notes'>* Nombre de retraits enregistrés. Les chiffres officiels de locations sont généralement supérieurs de +/- 10%</p>";
				echo "</div>";		

				
				if ($result = getActivStationPercentage($link)) 
				{
					if (mysqli_num_rows($result)>0)
					{
						//détermine le nombre de colonnes
						$nbcol=mysqli_num_rows($result);				
						while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
						{
							$tablo2[] = $row;
						}				
					}
				}
				//else echo mysqli_error($link);
	
					
				echo "<div id='GraphStationActives' class='left-widget widgetGraph'><button id='button_GraphStationActives' class='graphFullScreenButton'>+</button>";
				//var_dump($tablo2);
				
				echo "<script>";
				echo "var data = [{";
				
				$nb=count($tablo2);
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo2[$i]['statDate'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					echo '"'.$tablo2[$i]['activePercent'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				echo "  type: 'scatter', name: '1 Heure'},{";
				
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo2[$i]['statDate'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					echo '"'.$tablo2[$i]['activePercent3H'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				echo "  type: 'scatter', name: '3 Heure'},{";
				
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo2[$i]['statDate'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					echo '"'.$tablo2[$i]['activePercent6H'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				echo "  type: 'scatter', name: '6 Heure'}";
				
				echo "];";
				
				echo "
				var layout = 
				{ 
					title: 'Stations avec mouvements',
					paper_bgcolor: '#f8f9fa', 
					plot_bgcolor: '#f8f9fa',					
					showlegend: true,					
					margin: {
								l: 40,
								r: 20,
								b: 30,
								t: 30,
								pad: 4
							  },
					  yaxis: {
						title: '%',
						range: [15, 100]
					  },
					  xaxis: {
						showline: true,
						showgrid: false,
						showticklabels: true,
						linecolor: 'rgb(204,204,204)',
						linewidth: 2,
						autotick: true,
						ticks: 'outside',
						tickcolor: 'rgb(204,204,204)',
						tickwidth: 2,
						ticklen: 5,
						tickfont: {
						  family: 'Arial',
						  size: 12,
						  color: 'rgb(82, 82, 82)'
						}
					  }
				};";
				
				echo "Plotly.newPlot('GraphStationActives', data, layout,{displayModeBar: false});";
				echo '</script>';
				echo "<p class='notes'>* Pourcentage moyen de stations ayant enregistré au moins un mouvement toutes les 1 / 3 /6 h</p>";
				echo "</div>";				
				
				// graph nb velov
				$tablo=[];
				if ($result = getvelovNbrStats($link)) 
				{
					if (mysqli_num_rows($result)>0)
					{
						//détermine le nombre de colonnes
						$nbcol=mysqli_num_rows($result);				
						while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
						{
							$tablo[] = $row;
						}				
					}
				}
				
				//
				
				echo "<div id='GraphEvolutionNombrevelov' class='widgetGraph2' > <button id='button_GraphEvolutionNombrevelov' class='graphFullScreenButton'>+</button>";
				echo "<script>";
				echo "var data = [{";
				
				$nb=count($tablo);
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					 ;
					echo '"'.$tablo[$i]['avgvelov'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}
				
				echo ",
						error_y: {
						  type: 'data',
						  symmetric: false,
						  ";
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'array: [';
				
					 ;
					echo '"'.($tablo[$i]['maxvelov']-$tablo[$i]['avgvelov']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'arrayminus: [';
				
					 ;
					echo '"'.($tablo[$i]['avgvelov']-$tablo[$i]['minvelov']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}				
				echo " }, type: 'scatter', name : 'Officiel'},{";
				
				//serie 2
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					 ;
					echo '"'.$tablo[$i]['avgvelovOverflow'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}
				
				echo ",
						error_y: {
						  type: 'data',
						  symmetric: false,
						  ";
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'array: [';
				
					 ;
					echo '"'.($tablo[$i]['maxvelovOverflow']-$tablo[$i]['avgvelovOverflow']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'arrayminus: [';
				
					 ;
					echo '"'.($tablo[$i]['avgvelovOverflow']-$tablo[$i]['minvelovOverflow']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}				
				echo " }, type: 'scatter', visible: 'legendonly', name : 'Officiel,<br>En Overflow'},{";				
				//serie 3
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					 ;
					echo '"'.$tablo[$i]['avgEstimatedvelov'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}
				
				echo ",
						error_y: {
						  type: 'data',
						  symmetric: false,
						  ";
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'array: [';
				
					 ;
					echo '"'.($tablo[$i]['maxEstimatedvelov']-$tablo[$i]['avgEstimatedvelov']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'arrayminus: [';
				
					 ;
					echo '"'.($tablo[$i]['avgEstimatedvelov']-$tablo[$i]['minEstimatedvelov']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}				
				echo " }, type: 'scatter', name : 'Estimé'},{";	
				//serie 4
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					 ;
					echo '"'.$tablo[$i]['avgEstimatedvelovOverflow'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}
				
				echo ",
						error_y: {
						  type: 'data',
						  symmetric: false,
						  ";
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'array: [';
				
					 ;
					echo '"'.($tablo[$i]['maxEstimatedvelovOverflow']-$tablo[$i]['avgEstimatedvelovOverflow']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'arrayminus: [';
				
					 ;
					echo '"'.($tablo[$i]['avgEstimatedvelovOverflow']-$tablo[$i]['minEstimatedvelovOverflow']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}				
				echo " }, type: 'scatter', visible: 'legendonly', name : 'Estimé,<br>en Overflow '},{";				
				
				//serie 5
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
				
					 ;
					echo '"'.$tablo[$i]['avgEstimatedUnavailablevelov'].'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}
				
				echo ",
						error_y: {
						  type: 'data',
						  symmetric: false,
						  ";
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'array: [';
				
					 ;
					echo '"'.($tablo[$i]['maxEstimatedUnavailablevelov']-$tablo[$i]['avgEstimatedUnavailablevelov']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo '],';
				}
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'arrayminus: [';
				
					 ;
					echo '"'.($tablo[$i]['avgEstimatedUnavailablevelov']-$tablo[$i]['minEstimatedUnavailablevelov']).'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}				
				echo " }, type: 'scatter',  name : 'Estimé,<br>Indisponible '},{"; 

				//serie 6
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
						echo 'x: [';
					
					echo '"'.$tablo[$i]['date'].'", ';

					if($i%$nbcol==($nbcol-1))
					echo '],';

				}		
				for($i=0;$i<$nb;$i++)
				{
					if($i%$nbcol==0)
					echo 'y: [';
					
					$percEstimvelov = $tablo[$i]['avgEstimatedvelov']/$tablo[$i]['avgvelov'] *100;

					echo '"'.$percEstimvelov.'", ';

					if($i%$nbcol==($nbcol-1))
						echo ']';
				}				
				echo " , type: 'scatter', visible: 'legendonly', yaxis: 'y2', name : 'Estimé,<br>% dispo '}";
				
				echo "];
				var layout = 
				{ 
					title: 'Nombre de velov (Electrique et mécanique)', 
					paper_bgcolor: '#f8f9fa', 
					plot_bgcolor: '#f8f9fa',
					yaxis: {
							tickfont: {},
						  }, 
					yaxis2: {						
								overlaying: 'y', 
								tickfont: {color: 'rgb(55, 34, 29)'}, 
								side: 'right',
								showgrid: false,
								range: [0, 100]
							},						
					showlegend: true,
					margin: {
								l: 40,
								r: 20,
								b: 40,
								t: 30,
								pad: 4
							  }
				};";
				
				echo "Plotly.newPlot('GraphEvolutionNombrevelov', data, layout,{displayModeBar: false});";
				echo '</script>';
				echo "
				<p class='notes'>
					Ce graphique propose une représentation du nombre moyen, minimum et maximum de velov présents en station. <br>
					Les courbes officielles reprènent les données brutes de l'API velov. Les courbes estimées essayent d'évaluer le nombre de velov réellements disponibles/utilisables en soustrayant aux données officielles le nombre minimum de velov enregitré pour chaque station au cours des 3 derniers jours.<br>
					Ces courbes ne prennent pas en compte le nombre de velov en cours d'utilisation et/ou de déplacement par les équipes de régulation.
				</p>";				
				echo "</div>";

		
				

				//fin mise en cache
				$newPage = ob_get_contents(); //recup contenu à cacher
				updatePageInCache('index.php.5', $newPage); //mise en cache
				ob_end_clean(); //ménage cache memoire
				echo $newPage;	//affichage live	
				
			}						
		?>
		
	
	
	<div class="widget-short">	
		<h1 class="searchable-widget-title">Nouvelles stations velov</h1>
		<input type="text" id="newStationsSearchInput" onkeyup="newStationsSearch()" placeholder="Search for names..">
		<TABLE id='newStations' class='table-compact sortable'>
			<TR>
			<TH>Code</TH>
			<TH>Nom</TH>
			<TH class='adapativeHide'>Adresse</TH>
			<TH class='thdate'>Date d'ajout</TH>
			<TH class='thstatus'>Status</TH>			
			<TH class='thdate'>Date d'activation</TH>				
			</TR>	
			<?php
				if($cacheValide == true)
					getPageFromCache('index.php.6');
				else
				{
					ob_start();			
					// debut mise en cache				
					if ($result = getNewStationList($link)) 
					{
						if (mysqli_num_rows($result)>0)
						{
							while($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
							{
								echo "<TR>";
								echo "<TD>".$row["stationCode"]."</TD>";	
								echo "<TD><a href='carte-des-stations.php?lat=".$row["stationLat"]."&lon=".$row["stationLon"]."&zoom=17'>".$row["stationName"]."</a></TD>";											
								echo "<TD class='adapativeHide'>".$row["stationAdress"]."</TD>";	
								echo "<TD>".$row["stationInsertedInDb"]."</TD>";
								if($row["stationState"]=="Operative")
								{
									echo "<TD>"."En service"."</TD>";
								}
								elseif ($row["stationState"]=="Work in progress")
								{
								echo "<TD>"."En travaux"."</TD>";
								}
								else
								{
									echo "<TD>".$row["stationState"]."</TD>";
								}									
								echo "<TD>".$row["stationOperativeDate"]."</TD>";									
								echo "</TR>";	
							}				
						}
					}
					echo " ";
					//fin mise en cache
					$newPage = ob_get_contents(); //recup contenu à cacher
					updatePageInCache('index.php.6', $newPage); //mise en cache
					ob_end_clean(); //ménage cache memoire
					echo $newPage;	//affichage live		
					mysqlClose($link);
				}
			?>
		</TABLE>
	</div>    
	<div class="disclaimer">
		<br>
		Vous l'aurez sans doute deviné, <b>ce site n'est pas un site officiel de Velo'v.</b> 
		Les données utilisées, produites en partenariat Métropole de Lyon / JC DECAUX, proviennent de <a href="https://data.grandlyon.com/equipements/stations-vflov-de-la-mftropole-de-lyon-disponibilitf-temps-rfel/" target="_blank">data.grandlyon.com</a> et appartiennent à leur propriétaire.<br>
		<br>
		Ces tableaux et cartes sont une interprétation des données proposées par Lyon métropole sur sa plateforme open data en espérant ne pas les avoir trop déformées. 
		<a rel="license" href="http://creativecommons.org/licenses/by/4.0/"><img alt="Licence Creative Commons" style="border-width:0" src="https://i.creativecommons.org/l/by/4.0/80x15.png"/></a>		
		<a href="/cron/velovAPIParser.php" style="color:#f8f9fa">velovAPIParser (script de chargement)</a>
	</div>


	<script>
		function newStationsSearch() {
		  // Declare variables
		  var input, filter, table, tr, td, td1, td2, td3, i;
		  input = document.getElementById("newStationsSearchInput");
		  filter = input.value.toUpperCase();
		  table = document.getElementById("newStations");
		  tr = table.getElementsByTagName("tr");

		  // Loop through all table rows, and hide those who don't match the search query
		  for (i = 0; i < tr.length; i++) {
			td = tr[i].getElementsByTagName("td")[0];
			td1 = tr[i].getElementsByTagName("td")[1];
			td2 = tr[i].getElementsByTagName("td")[2];
			td3 = tr[i].getElementsByTagName("td")[3];
			if (td) {
			  if (td.innerHTML.toUpperCase().indexOf(filter) > -1 || td1.innerHTML.toUpperCase().indexOf(filter) > -1 || td2.innerHTML.toUpperCase().indexOf(filter) > -1|| td3.innerHTML.toUpperCase().indexOf(filter) > -1) {
				tr[i].style.display = "";
			  } else {
				tr[i].style.display = "none";
			  }
			}
		  }
		}
	</script>

	<!-- graph to full screen -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
	
	<script>

		var button_GraphEvolutionUtilisation = 1;
		$('#button_GraphEvolutionUtilisation').click
			(
				function(e)
				{				
					$('#GraphEvolutionUtilisation').toggleClass('fullscreen'); 
					var update = 
					{
						width: $('#GraphEvolutionUtilisation').width(), 
						height: $('#GraphEvolutionUtilisation').height()  
					};
					
					Plotly.relayout('GraphEvolutionUtilisation', update)
					
					if( button_GraphEvolutionUtilisation ==0)
					{
						$("#button_GraphEvolutionUtilisation").text('+');
						button_GraphEvolutionUtilisation=1;
						$("#fullscreenhider").hide();
						$('body').css('overflow', 'auto');
						
					}
					else
					{					
						$("#button_GraphEvolutionUtilisation").text('x');
						button_GraphEvolutionUtilisation=0;
						$("#fullscreenhider").fadeIn("slow");
						$('body').css('overflow', 'hidden');
					}
				}
			);		
			
			
			
		var button_GraphOuvStationSemaine = 1;
		$('#button_GraphOuvStationSemaine').click
			(
				function(e)
				{				
					$('#GraphOuvStationSemaine').toggleClass('fullscreen'); 
					var update = 
					{
						width: $('#GraphOuvStationSemaine').width(), 
						height: $('#GraphOuvStationSemaine').height() 
					};
					
					Plotly.relayout('GraphOuvStationSemaine', update)
					
					if( button_GraphOuvStationSemaine ==0)
					{
						$("#button_GraphOuvStationSemaine").text('+');
						button_GraphOuvStationSemaine=1;
						$("#fullscreenhider").hide();
						$('body').css('overflow', 'auto');
					}
					else
					{					
						$("#button_GraphOuvStationSemaine").text('x');
						button_GraphOuvStationSemaine=0;
						$("#fullscreenhider").fadeIn("slow");
						$('body').css('overflow', 'hidden');
					}
				}
			);	
			
		
		var button_GraphStationActives = 1;
		$('#button_GraphStationActives').click
			(
				function(e)
				{				
					$('#GraphStationActives').toggleClass('fullscreen'); 
					var update = 
					{
						width: $('#GraphStationActives').width(), 
						height: $('#GraphStationActives').height() 
					};
					
					Plotly.relayout('GraphStationActives', update)
					
					if( button_GraphStationActives ==0)
					{
						$("#button_GraphStationActives").text('+');
						button_GraphStationActives=1;
						$("#fullscreenhider").hide();
						$('body').css('overflow', 'auto');
					}
					else
					{					
						$("#button_GraphStationActives").text('x');
						button_GraphStationActives=0;
						$("#fullscreenhider").fadeIn("slow");
						$('body').css('overflow', 'hidden');
					}
				}
			);		
			
			
		var button_GraphEvolutionNombrevelov = 1;
		$('#button_GraphEvolutionNombrevelov').click
			(
				function(e)
				{				
					$('#GraphEvolutionNombrevelov').toggleClass('fullscreen'); 
					var update = 
					{
						width: $('#GraphEvolutionNombrevelov').width(), 
						height: $('#GraphEvolutionNombrevelov').height()  
					};
					
					Plotly.relayout('GraphEvolutionNombrevelov', update)
					
					if( button_GraphEvolutionNombrevelov ==0)
					{
						$("#button_GraphEvolutionNombrevelov").text('+');
						button_GraphEvolutionNombrevelov=1;
						$("#fullscreenhider").hide();
						$('body').css('overflow', 'auto');
					}
					else
					{					
						$("#button_GraphEvolutionNombrevelov").text('x');
						button_GraphEvolutionNombrevelov=0;
						$("#fullscreenhider").fadeIn("slow");
						$('body').css('overflow', 'hidden');
					}
				}
			);

		var button_GraphEvolutionNombreEvelov = 1;
		$('#button_GraphEvolutionNombreEvelov').click
			(
				function(e)
				{				
					$('#GraphEvolutionNombreEvelov').toggleClass('fullscreen'); 
					var update = 
					{
						width: $('#GraphEvolutionNombreEvelov').width(), 
						height: $('#GraphEvolutionNombreEvelov').height()  
					};
					
					Plotly.relayout('GraphEvolutionNombreEvelov', update)
					
					if( button_GraphEvolutionNombrevelov ==0)
					{
						$("#button_GraphEvolutionNombreEvelov").text('+');
						button_GraphEvolutionNombrevelov=1;
						$("#fullscreenhider").hide();
						$('body').css('overflow', 'auto');
					}
					else
					{					
						$("#button_GraphEvolutionNombreEvelov").text('x');
						button_GraphEvolutionNombrevelov=0;
						$("#fullscreenhider").fadeIn("slow");
						$('body').css('overflow', 'hidden');
					}
				}
			);			
			
	</script>
	<div id="fullscreenhider" style="display: none;"></div>
	<!-- graph to full screen END-->
	
	<div id="mypub">
		<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
		
		<!-- velov3 -->
		<ins class="adsbygoogle"
			 style="display:inline-block;width:300px;height:600px"
			 data-ad-client="ca-pub-4705968908052303"
			 data-ad-slot="8893891084"></ins>
		<script>
		(adsbygoogle = window.adsbygoogle || []).push({});
		</script>
		
		<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
		<!-- velov -->
		<ins class="adsbygoogle"
			 style="display:inline-block;width:970px;height:250px"
			 data-ad-client="ca-pub-4705968908052303"
			 data-ad-slot="5109142449"></ins>
		<script>
		(adsbygoogle = window.adsbygoogle || []).push({});
		</script>
		
		<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
		<!-- velov4 -->
		<ins class="adsbygoogle"
			 style="display:inline-block;width:336px;height:280px"
			 data-ad-client="ca-pub-4705968908052303"
			 data-ad-slot="9256444048"></ins>
		<script>
		(adsbygoogle = window.adsbygoogle || []).push({});
		</script>

		<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
		<!-- velov4 -->
		<ins class="adsbygoogle"
			 style="display:inline-block;width:336px;height:280px"
			 data-ad-client="ca-pub-4705968908052303"
			 data-ad-slot="9256444048"></ins>
		<script>
		(adsbygoogle = window.adsbygoogle || []).push({});
		</script>
	</div>
	
	</body>
</html>