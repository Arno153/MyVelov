<!DOCTYPE html> 
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

	<title>velov Paris - Carte officieuse des stations et velov disponibles</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Carte officieuse des stations du nouveau velov 2018: stations qui fonctionnent ou peut être pas, nombre de velos et VAE disponibles..." />
	<meta name="keywords" content="velov, velov 2018, velov2018, velov 2, cartes, geolocalisation, gps, autour de moi, station, vélo, paris, fonctionnent, disponibles, HS, en panne" />
	<meta name="viewport" content="initial-scale=1.0, width=device-width" />
	<meta name="robots" content="index, follow">
	<link rel="canonical" href="https://velov.philibert.info/carte-des-stations.php" />
	
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#00a300">
	<meta name="msapplication-TileImage" content="/mstile-144x144.png">
	<meta name="theme-color" content="#ffffff">
	<link rel="stylesheet" media="all" href="./css/joujouvelov.css?<?php echo filemtime('./css/joujouvelov.css');?>">
	<script src="./inc/mapLeaflet.js?<?php echo filemtime('./inc/mapLeaflet.js');?>" type="text/javascript"></script>	
	
	
	<!-- Base MAP -->
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.4.0/dist/leaflet.css"
	   integrity="sha512-puBpdR0798OZvTTbP4A8Ix/l+A4dHDD0DGqYW6RQ+9jxkRFclaxxQb/SJAWZfWAkuyeQUytO7+7N4QKrDh+drA=="
	   crossorigin=""/>
	<!-- Make sure you put this AFTER Leaflet's CSS -->
	<script src="https://unpkg.com/leaflet@1.4.0/dist/leaflet.js"
	   integrity="sha512-QVftwZFqvtRNi0ZyCtsznlKSWOStnDORoefr1enyq5mVL4tmKB3S/EnC3rRJcxCPavG10IcrVGSmPh6Qw5lwrg=="
	   crossorigin=""></script>
	<!-- Base MAP END-->
	
	<!-- LOCATION CONTROLE -->   
	<!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"> -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.css" />

	<script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.js" charset="utf-8"></script>
	<!-- LOCATION CONTROLE END -->  

	<!-- geocoding  -->
	<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
	<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
	<!-- geocoding  END-->
	
	<!-- full screen-->
	<script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
	<link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
	<!-- full screen END-->
	
	<!-- custom controle -- refresh and toggle button -->
	<script src="./inc/Leaflet.Control.Custom.js"></script>
	<!-- custom controle -- END -->
	
  </head>
  <body>
	<?php	
	include "./inc/mysql.inc.php";

	$lofFile='./.maintenance';
	if(file_exists ($lofFile) )
	{
		echo 
			"
			<div class='maintenance'>
				<!-- !!! Mode maintenance actif !!! -->
					Mon processus de collecte des données velov est actuellement perturbé.</br>
					Les données affichées peuvent ponctuellement avoir quelques minutes; La précision des modes estimés pourrait être dégradée localement 
			</div>	
			";
	}
	include "./inc/menu.inc.php";	
	?>

    <div id="mapid"></div>
    <script type="text/javascript">		
		var locations;
		var marker, i, iconurl;
		var markers = [];
		var HS;	
			

		// lecture cookies
		function getCookie(cname) {
			var name = cname + "=";
			var decodedCookie = decodeURIComponent(document.cookie);
			var ca = decodedCookie.split(';');
			for(var i = 0; i <ca.length; i++) {
				var c = ca[i];
				while (c.charAt(0) == ' ') {
					c = c.substring(1);
				}
				if (c.indexOf(name) == 0) {
					return c.substring(name.length, c.length);
				}
			}
			return false;
		}
		
		// on recupère le choix du mode officiel ou estimé depuis un cookies
		var estimatedvelovNumber = 0;
		if(getCookie("estimatedvelovNumber")==3)
		{
			estimatedvelovNumber = 3;
		}
		else if(getCookie("estimatedvelovNumber")==2)
		{
			estimatedvelovNumber = 2;
		}

		// on recupéère le choix du mode Tout/velov/VAE depuis un cookies
		var VAEMode = 0;
		if(getCookie("VAEMode")==1)
		{
			VAEMode = 1;
		}
		else if(getCookie("VAEMode")==2)
		{
			VAEMode = 2;
		}	
		
		
		var zoomp = getUrlParam('zoom');
		if(zoomp == undefined){
				var zoomp = 13;
		} 
		
		var latp = getUrlParam('lat');
		if(latp == undefined){
				var latp = 45.76;
		}
		
		var lonp  = getUrlParam('lon');
		if(lonp == undefined){
				var lonp = 4.82;
		}
		
		
		// initiate leaflet map
		var mymap = L.map('mapid', {
			center: [latp, lonp],
			zoom: zoomp,
			zoomControl: false
		})
		// add zoomControl
		L.control.zoom({ position: 'topright' }).addTo(mymap);
		
		// create a cutom control to refresh data (display only in fullscreen mode)		
		var cc = L.control.custom({
							position: 'topleft',
							title: 'Rafraichir',
							content : '<a class="leaflet-bar-part leaflet-bar-part-single" id="ReloadData">'+
									  '    <i class="fa fa-refresh"></i> '+
									  '</a>',
							classes : 'leaflet-control-locate leaflet-bar leaflet-control',
							style   :
							{
								padding: '0px',
							},
							events:
								{
									click: function(data)
									{
										refresh(estimatedvelovNumber, 0, VAEMode);									
									},
								}
						})
						.addTo(mymap);
						
		
		
		// add full screen control
		mymap.addControl(new L.Control.Fullscreen());
		
		// `fullscreenchange` Event that's fired when entering or exiting fullscreen.
		mymap.on('fullscreenchange', function () {
			if (mymap.isFullscreen()) {
				refresh(estimatedvelovNumber,0,VAEMode);
			} else {
				refresh(estimatedvelovNumber,0,VAEMode);
			}
		});
		
		// set map area limits
		var southWest = L.latLng(45.7, 4.75),
		northEast = L.latLng( 45.82, 4.95),
		mybounds = L.latLngBounds(southWest, northEast);		
		mymap.setMaxBounds(mybounds);
		mymap.options.minZoom = 11;
		mymap.options.maxBoundsViscosity = 1.0;

		//Load tiles
		L.tileLayer('https://velib.philibert.info/tiles/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		}).addTo(mymap);
		
		//add geolocation control
		var lc = L.control.locate({
			position: 'topleft',
			strings: {
				title: "Geolocalisation!"
			},
			locateOptions: {
				maxZoom: 16,
				enableHighAccuracy: true
				
			}
		}).addTo(mymap);
		
		//load stations to the map
		getStations(estimatedvelovNumber,0,VAEMode);
		
		// add adress search control		
		L.Control.geocoder().addTo(mymap);

		// create a cutom control to switch between api velov nbr and estimated velov nbr
		var cc2 = L.control.custom({
							position: 'bottomleft',
							title: 'Mode d\'estimation',
							content : 
								//'<label class="switch switch-left-right"><input class="switch-input" type="checkbox" /><span class="switch-label" data-on="Estimé" data-off="Officiel"></span> <span class="switch-handle"></span></label>',
								//'<label class="switch switch-left-right"><input id="mySwitch" class="switch-input" type="checkbox" /><span class="switch-label" data-on="Estimé" data-off="Officiel"></span> <span class="switch-handle"></span></label>',
								'<div class="switch-field"><input type="radio" id="switch_3_left" name="switch_3" value="0" checked/><label for="switch_3_left">Officiel<br></label><input type="radio" id="switch_3_center" name="switch_3" value="3" /><label for="switch_3_center">Estimé<br>3J</label><input type="radio" id="switch_3_right" name="switch_3" value="2" /><label for="switch_3_right">Estimé<br>2J</label></div>',
							style   :
							{
								padding: '0px',
							},
							events:
								{
									click: function(data)
									{
										document.getElementById("switch_3_center").onclick = function() {									
											if(document.getElementById("switch_3_center").checked)
											{											
												//alert('3J');
												estimatedvelovNumber = 3;
												refresh(3,0,VAEMode);												
											}	
										}
										document.getElementById("switch_3_left").onclick = function() {									
											if(document.getElementById("switch_3_left").checked)
											{											
												//alert('Officiel');
												estimatedvelovNumber = 0;
												refresh(0,0,VAEMode);												
											}	
										}	
										document.getElementById("switch_3_right").onclick = function() {									
											if(document.getElementById("switch_3_right").checked)
											{											
												//alert('2J');
												estimatedvelovNumber = 2;
												refresh(2,0,VAEMode);												
											}	
										}										
										
										
										// on stoque le choix dans un cookies
										var d = new Date();
										d.setTime(d.getTime() + (30*24*60*60*1000));
										var expires = "expires="+ d.toUTCString();
										document.cookie = "estimatedvelovNumber" + "=" + estimatedvelovNumber + ";" + expires + ";path=/";
									},
								}
						})
						.addTo(mymap);
						
		if(estimatedvelovNumber == 2)
		{
			document.getElementById("switch_3_right").checked=true;
		}
		else if(estimatedvelovNumber == 3)
		{
			document.getElementById("switch_3_center").checked=true;
		}
		;						

		
		
    </script>
	
	<div class="disclaimer">
		* Stations velov suivant les derniers mouvements : <img src="./images/marker_green0.png" alt="Vert" width="12"><1h
		<<img src="./images/marker_yellow0.png" alt="Jaune" width="12"><3h
		<<img src="./images/marker_orange0.png" alt="Orange" width="12"><12h
		<<img src="./images/marker_red0.png" alt="Rouge" width="12"><24h
		<<img src="./images/marker_purple0.png" alt="Violet" width="12">  --
		<img src="./images/marker_grey0.png" alt="Gris" width="12"> : En travaux / Fermée -- NB: Malus pour les stations sans retraits
		<br>* Données communautaire: <img src="./images/marker_greenx10.png" alt="Croix" width="12"> Signalée HS
		<!-- *** Alimentation Electrique: 
					<img src="./images/marker_p_green0.png" alt="Enedis Powered" width="12"> Enedis 
					- <img src="./images/marker_green0.png" alt="batterie" width="12"> Sur batterie
					- <img src="./images/marker_u_green0.png" alt="inconnue" width="12"> Inconnue
		-->
		
		<br>* Le mode "Estimé" essaye d'évaluer le nombre réel de velov disponibles dans une station en soustrayant aux données officielles le nombre min de velov enregistré par la station sur les 2 ou 3 derniers jours
		<br>* L'absence de mouvement ne présume pas du dysfonctionnement d'une station, l'inverse est également vrai... 
		<br>* <b>Ce site n'est pas un site officiel de Velo'v.</b> Les données utilisées, produites en partenariat Métropole de Lyon / JC DECAUX, proviennent de <a href="https://data.grandlyon.com/equipements/stations-vflov-de-la-mftropole-de-lyon-disponibilitf-temps-rfel/" target="_blank">data.grandlyon.com</a> et appartiennent à leur propriétaire.<br>

		<a rel="license" href="http://creativecommons.org/licenses/by/4.0/"><img alt="Licence Creative Commons" style="border-width:0" src="https://i.creativecommons.org/l/by/4.0/80x15.png"/></a>		


		<?php /*
			$link = mysqlConnect();
			echo " (Dernière collecte: ".getLastUpdate($link).")</h3>";		
			mysqlClose($link);	*/
		?>		
	</div>	
	
	<div id="mypub">
		<iframe id="gads" src="./inc/ads.inc.html" width="100%" height="600px" />
	</div>
	
  </body>
</html>