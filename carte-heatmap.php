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

	<title>Velib Paris - Carte officieuse - Heat Map</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Carte officieuse des stations du nouveau velov 2018: stations qui fonctionnent ou peut être pas, nombre de velos et VAE disponibles..." />
	<meta name="keywords" content="velov, velov 2018, velov2018, velov 2, cartes, geolocalisation, gps, autour de moi, station, vélo, paris, fonctionnent, disponibles, HS, en panne" />
	<meta name="viewport" content="initial-scale=1.0, width=device-width" />
	<meta name="robots" content="index, follow">
	<link rel="canonical" href="https://velov.philibert.info/carte-heatmap.php" />
	
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
	

	
	<!-- full screen-->
	<script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
	<link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css' rel='stylesheet' />
	<!-- full screen END-->
	
	<!-- custom controle -- refresh and toggle button -->
	<script src="./inc/Leaflet.Control.Custom.js"></script>
	<!-- custom controle -- END -->
	
	<!-- heatmap -->
	<script src="./inc/leaflet-heat.js"></script>
	<!-- heatmap -- END -->
	
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
					Mon processus de collecte des données Velib est actuellement perturbé.</br>
					Les statistiques d'utilisation affichées pour les 10, 11, 12 et 13 septembre sont erronées. </br>
					Les statistiques d'utilisation affichées depuis le 13 septembre sont plus ou moins lourdement sous estimées. </br>
			</div>	
			";
	}
	
	include "./inc/menu.inc.php";	
	?>
	
    <div id="mapid"></div>
    <script type="text/javascript">		
		var locations = [];
		var marker, i, iconurl;
		var markers = [];
		var HS;		

		var mvtDate = 0;		

		var zoomp = 13;
		var latp = 45.76;
		var lonp = 4.82;

		
		// initiate leaflet map
		var mymap = L.map('mapid', {
			center: [latp, lonp],
			zoom: zoomp,
			zoomControl: false
		})
		// add zoomControl
		L.control.zoom({ position: 'topright' }).addTo(mymap);
		
		// add full screen control
		mymap.addControl(new L.Control.Fullscreen());
		
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

		
		//load stations to the map
		getHeatmapData(mvtDate);
		
		// slider management
		var cc2 = L.control.custom({
							position: 'bottomleft',
							title: 'switch',
							content : 
								'<div class="value">Aujourd\'hui</div><input type="range" min="0" max="10" step="1" value="0">',
							style   :
							{
								padding: '0px',
							}
						})
						.addTo(mymap);		
				
		var elem = document.querySelector('input[type="range"]');

		var rangeValue = debounce(function(){
		  var newValue = elem.value;
		  mvtDate = newValue;
		  getHeatmapData(mvtDate);
		  if(elem.value==0)
			  newValue = "Aujourd'hui";
		  else if(elem.value==1)
			  newValue = "Hier";
		  else newValue = "J-"+newValue;
		  var target = document.querySelector('.value');
		  target.innerHTML = newValue;
		},300);
				
		elem.addEventListener("input", rangeValue);	
    </script>
	
	<div class="disclaimer">
		Estimation de la densité de mouvements des velovs basée sur le nombre de retraits identifiés pour chaque station. 
		<br><b> Donnée de la journée en cours quelque soit l'heure à laquelle vous consultez cette page!!!</b>
	
		<br>* <b>Ce site n'est pas un site officiel de Velo'v.</b> Les données utilisées, produites en partenariat Métropole de Lyon / JC DECAUX, proviennent de <a href="https://data.grandlyon.com/equipements/stations-vflov-de-la-mftropole-de-lyon-disponibilitf-temps-rfel/" target="_blank">data.grandlyon.com</a> et appartiennent à leur propriétaire.<br>

		<a rel="license" href="http://creativecommons.org/licenses/by/4.0/"><img alt="Licence Creative Commons" style="border-width:0" src="https://i.creativecommons.org/l/by/4.0/80x15.png"/></a>		
	</div>	
	
	<div id="mypub">
		<iframe id="gads" src="./inc/ads.inc.html" width="100%" height="600px" />
	</div>
	
  </body>
</html>