<link rel="stylesheet" media="all" href="./css/newMenu.css?<?php echo filemtime('./css/newMenu.css');?>">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

<div id="cssmenu"><div id="menu-button">Menu</div>
  <ul>
     <li><a href="./"><i class="fa fa-fw fa-bicycle"></i> Accueil</a></li>
     <li><a href="./carte-des-stations.php"><i class="fa fa-fw fa-leanpub"></i> Carte</a></li>
     <li class="has-sub"><span class="submenu-button"></span><a href="#">Plus de cartes</a>
        <ul>
           <li><a href="./liste-des-stations.php"><i class="fa fa-fw fa-bars"></i> Liste des stations</a></li>
           <li><a href="./carte-des-mouvements.php"><i class="fa fa-fw fa-map-o"></i> Carte des mouvements</a></li>
           <li><a href="./carte-heatmap.php"><i class="fa fa-fw fa-map-o"></i> Heat Map</a></li>
		   <li><a href="./carte-des-velov-bloques.php"><i class="fa fa-fw fa-map-o"></i> Carte des velov bloqués</a></li>
        </ul>
     </li>
     <li class="has-sub"><span class="submenu-button"></span><a href="#">Contact et autres</a>
        <ul>
           <li><a href="https://twitter.com/arno152153"><img alt="Twitter" src="https://abs.twimg.com/favicons/favicon.ico" width="12px" height="12px" border="0"> Me contacter </a></li>           
           <li><a href="https://github.com/Arno153/MyVelov"><img alt="Twitter" src="https://github.githubassets.com/favicon.ico" width="12px" height="12px" border="0"> Sources du projet</a></li>
        </ul>
     </li>	 
  </ul>
</div>
