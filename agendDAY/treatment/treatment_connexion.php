<?php
session_start();

require 'connexion_db.php';

try {
    $bdd = getDatabaseConnection();
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur de connexion : " . $e->getMessage();
    header("Location: ../connexion.php");
    exit();
}

if (isset($_POST['Connexion'])) {
    $email = htmlspecialchars($_POST["email"]);
    $motDePasse = $_POST["motDePasse"];

    $sql = "SELECT * FROM users WHERE email = :email";
    $requete = $bdd->prepare($sql);
    $requete->execute([':email' => $email]);

    if ($requete->rowCount() > 0) {
        $user = $requete->fetch(PDO::FETCH_ASSOC);

        if ($user['is_confirmed'] == 0) {
            $_SESSION['message'] = "Votre compte n'est pas confirmé. Veuillez vérifier votre email ou créer un nouveau compte.";
            header("Location: ../inscription.php");
            exit();
        }

        if (password_verify($motDePasse, $user['motDePasse'])) {
            $_SESSION['id'] = $user['id'];
            $_SESSION['message'] = "Connexion réussie !";
            header("Location: ../calendar.php");
            exit();
        } else {
            $_SESSION['message'] = "Email ou mot de passe incorrect.";
            header("Location: ../connexion.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Email ou mot de passe incorrect.";
        header("Location: ../connexion.php");
        exit();
    }
} else {
    $_SESSION['message'] = "Veuillez remplir tous les champs.";
    header("Location: ../connexion.php");
    exit();
}
?>