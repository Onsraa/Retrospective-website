<!DOCTYPE html>
<html>
<head>
    <?php
    $title = "Token verification";
    include("../includes/head.php");
    ?>
    <link rel="stylesheet" href="../css/styles.css">
</head>

<body>
    <main>
        <?php

        if (isset($_POST['email']) && !empty($_POST['email'])) {
            require_once('create_account_verification.php'); //Vérification des conditions de création de compte au préalable.
        }

        require('../includes/servers/db.php');

        if (isset($_GET['resend']) && !empty($_GET['resend']) && $_GET['resend'] == 1 && isset($_GET['email']) && !empty($_GET['email'])) {

            $q = 'SELECT id, nickname FROM USERS WHERE email = ? AND token IS NOT NULL';
            $req = $bdd->prepare($q);
            $req->execute([htmlspecialchars($_GET['email'])]);
            $verify = $req->fetch(PDO::FETCH_ASSOC);

            $token = (string)(rand(222222, 999999));

            if (!empty($verify)) {

                $q = 'UPDATE USERS SET token = ? WHERE email = ?';
                $req = $bdd->prepare($q);
                $result = $req->execute([$token, htmlspecialchars($_GET['email'])]);

                if ($result) {

                    require_once('gmail.php');
                    
                    $subject = 'Retrospective verification code.';
                    $type = 'verification';
                    $to = $_GET['email'];

                    echo '<div class="token-el">';
                    sendMail($subject, $type, $to, $verify['nickname'], $token);
                } else {
                    echo '<p>Le renvoi du code a échoué</p>';
                }
            }
        }

        if (isset($_GET['token'])) { //Si le token a été envoyé en paramètre GET

            $q = 'SELECT id FROM USERS WHERE email = ? AND is_banned = ?'; //Pour éviter qu'une personne change la valeur d'is_banned dans la BDD en insérant dans l'URL son email et un token NULL en paramètre GET.
            $req = $bdd->prepare($q);
            $req->execute([htmlspecialchars($_GET['email']), 1]);
            $result = $req->fetch(PDO::FETCH_ASSOC);

            if (empty($result)) {

                $q = 'SELECT id FROM USERS WHERE email = ? AND token = ?'; //On regarde dans la BDD si l'email et le token correspondent aux valeurs dans la BDD.
                $req = $bdd->prepare($q);
                $req -> execute([htmlspecialchars($_GET['email']), htmlspecialchars($_GET['token'])]);
                $result = $req -> fetch(PDO::FETCH_ASSOC);

                if (empty($result)) {
                    echo '<p>Le code est incorrect.</p>';
                } else {
                    $q = 'UPDATE USERS SET status = "user", token = NULL, creation_date = NOW(), background_profile = "default.png" WHERE email = ?'; //Si elles correspondent alors on change le status de l'utilisateur pour qu'il puisse avoir accès aux fonctionnalités.
                    $req = $bdd->prepare($q);
                    $verify = $req->execute([htmlspecialchars($_GET['email'])]);

                    if ($verify) {

                        session_start();
                        $_SESSION['email'] = $_GET['email'];
                        $_SESSION['id'] = $result['id'];

                        echo '<div class="tokenIsValid-el">';
                        echo '<p class="tokenSuccess-el">Compte vérifié avec succès</p>';
                        echo '<button class="tokenSuccessButton-el"><a href="../index.php">Retourner à la page d\'accueil</a></button>';
                        echo '</div>';
                    } else {
                        echo '<p>La vérification a rencontré un problème.</p>';
                    }
                }
            }
        } else {
            if (isset($_POST['email'])) {
                $q = 'SELECT id FROM USERS WHERE email = ?';
                $req = $bdd->prepare($q);
                $req->execute([$_POST['email']]);
                $result = $req->fetchAll(PDO::FETCH_ASSOC);

                $token = (string)(rand(222222, 999999));

                $salt = '$c53.*?é';
                $salted_password = hash('sha256', $_POST['password'] . $salt);

                if (empty($result)) {   

                    $q = 'INSERT INTO USERS(nickname, email, password, token) VALUES(:nickname, :email, :password, :token)';
                    $req = $bdd->prepare($q);
                    $result = $req->execute([
                        'nickname' => $_POST['nickname'],
                        'email' => $_POST['email'],
                        'password' => $salted_password,
                        'token' => $token
                    ]);

                    $stat = 'USERS';
                    include("../includes/stats.php");
                    
                    if (!$result) {
                        header('location: ../index.php?message=La création du compte a échoué.');
                        exit;
                    } else {
                        $q = 'SELECT id FROM USERS WHERE email = ?';
                        $req = $bdd->prepare($q);
                        $req->execute([$_POST['email']]);
                        $result = $req->fetch(PDO::FETCH_ASSOC);
    

                        if (!empty($result)) {
                            $q = 'INSERT into USER_AVATAR(users, avatar_assets) VALUES (:id, :asset)';
                            $req = $bdd->prepare($q);
                            $result = $req->execute(['id' => $result['id'], 'asset' => 1]);

                            if (!$result) {
                                header('location: ../index.php?message=La création du compte a échoué.');
                                exit;
                            }
                        }
                    };
                }

                require_once('gmail.php');

                $subject = 'Retrospective verification code.';
                $type = 'verification';
                $to = $_POST['email'];
                echo '<div class="token-el">';

                sendMail($subject, $type, $to, $_POST['nickname'], $token);
            }

            if (!isset($_POST['email']) && !isset($_GET['resend'])) {
                echo '<div class="token-el">';
            }

            echo '<h2>Saisissez votre code de vérification</h2>';
            echo '<form method="get">';
            echo    '<input type="hidden" name="email" value="' . (isset($_POST['email']) ? $_POST['email'] : htmlspecialchars($_GET['email'])) . '" />';
            echo    '<input class="token-codeEl" type="text" name="token" placeholder="Entrez le code de vérification !">';
            echo    '<button class="token-buttonEl" type="submit">Vérifier le token</button>';
            echo '</form>';
            echo '<form method="get">';
            echo    '<input type="hidden" name="email" value="' . (isset($_POST['email']) ? $_POST['email'] : htmlspecialchars($_GET['email'])) . '" />';
            echo    '<input type="hidden" name="resend" value="1">';
            echo    '<button class="token-buttonEl" type="submit">Renvoyer un code</button>';
            echo '</form>';
            echo '</div>';
        }

        ?>
    </main>
</body>

</html>