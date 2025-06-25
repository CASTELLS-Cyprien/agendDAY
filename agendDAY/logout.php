<?php
session_start();

// Détruire toutes les données de la session
session_unset();
session_destroy();

// Rediriger immédiatement vers la page de connexion
header("Location: connexion.php");
exit();
?>