<?php
    ini_set('display_errors', TRUE);
    error_reporting(E_ALL);
    $dbhost = 'localhost';
    $dbuser = 'api';
    $dbpass = 'deltatech';
    $dbname = 'container-esp';
    $db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    // verify if post is set
    if ((isset($_POST['username']))&& isset($_POST['password'])) {
        $usernameProvided = htmlspecialchars($_POST['username']);
        $passwordProvided = htmlspecialchars($_POST['password']);
        if ((isset($_POST['start_newshiping']) ) && isset($_POST['containernumber'])) {
            $start_newshiping = htmlspecialchars($_POST['start_newshiping']); //start newshiping
            $containernumber = htmlspecialchars($_POST['containernumber']); //containernumber
            if ($start_newshiping == "true") {
                echo start_newshiping($usernameProvided, $passwordProvided, $containernumber);
            }
            else {
                echo json_encode("BAD REQUEST on start_newshiping");
                exit();
            }
        }
        else if (isset($_POST['get_password'])) {
            $get_password = htmlspecialchars($_POST['get_password']); //get password
            echo get_password($usernameProvided, $passwordProvided, $get_password);
            
        }
        else if ((isset($_POST['temp']) && isset($_POST['containernumber']))){ //End shiping
            $temp =  $_POST['temp']; //temp
            $containernumber = $_POST['containernumber']; //containernumber
            echo end_shiping($usernameProvided, $passwordProvided, $containernumber, $temp);
        }
        else {
            echo connectuser($usernameProvided, $passwordProvided);
        }
    }
    else {
        echo json_encode("BAD REQUEST");
        echo json_encode(" Are you trying to hack me?");
        exit();
    }
    function connectuser($username, $passwordProvided) {
        global $db;
        $query = "SELECT login,password,role FROM user WHERE login = '" . $username . "'";
        $result = mysqli_query($db, $query);
        if (!$result) {
            echo 'Could not run query: ';
            exit;
        }
        $result = mysqli_fetch_row($result);
        $login = $result[0];
        $password = $result[1];
        $role = $result[2];
        if (password_verify($passwordProvided, $password)) {
            $newcontainernumber = mt_rand(1111,9999);
            mysqli_query($db,"INSERT INTO container (Id, numContainer) VALUES (NULL,". $newcontainernumber.");");	// Créer un nouveau numéro de conteneur dans la table container
            return json_encode(array(
                'status' => 'Success',
                'name' => $login, 
                'role' => $role, 
                'containernumber' => $newcontainernumber
            ));
        }
        else {
            return json_encode(array(
                'status' => 'Error',));
        }
        
    }
    function start_newshiping($username, $passwordProvided, $containernumber) {
        global $db;
        $query = "SELECT password,role FROM user WHERE login = '" . $username . "'";
        $result = mysqli_query($db, $query);
        if (!$result) {
            echo 'Could not run query: ';
            exit;
        }
        $result = mysqli_fetch_row($result);
        $password = $result[0];
        $role = $result[1];
        if ((password_verify($passwordProvided, $password)) && ($role == "Hospitalier")) {
            $result = mysqli_query($db,"SELECT container.id FROM container WHERE container.numContainer=".$containernumber." ORDER BY id DESC LIMIT 1");
            if (!$result) {
                echo json_encode(array(
                    'status' => 'Error no container with this number',));
                exit;
            }
            $containerid = mysqli_fetch_row($result)[0];
            $shipingnumber = mt_rand(11111,99999);
            $shipingpassword = mt_rand(1111,9999);
            $shipingpassword = 'CH-Sang-'. $shipingpassword;
            $sql = "INSERT INTO transport (numTransport, password, dateDepart, idCont) VALUES (".$shipingnumber.", ".'"'.$shipingpassword.'"'.", current_timestamp(), ".$containerid.");";
            $result = mysqli_query($db,$sql);
            if (!$result) {
                echo json_encode(array(
                    'status' =>  'Error cant start shiping'));
                exit;
            }
            return json_encode(array(
                'status' => 'Success',
                'shipingnumber' => $shipingnumber, 
                'shipingpassword' => $shipingpassword));
        }
        else {
            return "error can't user unauthorized";
        }
    }
    function get_password($username, $passwordProvided, $containernumber) {
        global $db;
        $query = "SELECT password,role FROM user WHERE login = '" . $username . "'";
        $result = mysqli_query($db, $query);
        if (!$result) {
            echo json_encode(array(
                'status' =>  'Could not run query: '));
            exit;
        }
        $result = mysqli_fetch_row($result);
        $password = $result[0];
        $role = $result[1];
        if ((password_verify($passwordProvided, $password)) && ($role == "Laborantin")) {
            $result = mysqli_query($db, "SELECT transport.password FROM transport,container WHERE container.numContainer=".$containernumber." AND transport.idCont=container.id");
            if (!$result) {
                echo json_encode(array(
                    'status' =>   'Error no container with this number'));
                exit;
            }
            $shipingpassword = mysqli_fetch_row($result)[0];
            return json_encode(array(
                'status' => 'Success',
                'shipingpassword' => $shipingpassword));
        }
        else {
            return "error can't getpassword user unauthorized";
        }
    }
    function end_shiping($username, $passwordProvided, $containernumber, $temp) {
        global $db;
        $query = "SELECT password,role FROM user WHERE login = '" . $username . "'";
        $result = mysqli_query($db, $query);
        if (!$result) {
            echo json_encode(array(
                'status' =>  'Could not run query: '));
            exit;
        }
        $result = mysqli_fetch_row($result);
        $password = $result[0];
        $role = $result[1];
        if ((password_verify($passwordProvided, $password)) && ($role == "Laborantin")) {
            $result = mysqli_query($db,"SELECT id FROM container WHERE numContainer=".$containernumber." ORDER BY id DESC LIMIT 1");
            if (!$result) {
                echo json_encode(array(
                    'status' =>  'Error no container with this number'));
                exit;
            }
            $containerid = mysqli_fetch_row($result)[0];
            $sql = "UPDATE transport SET dateArrivee = current_timestamp() WHERE idCont = ".$containerid.""; // end shiping
            $result = mysqli_query($db,$sql);
            if (!$result) {
                echo json_encode(array(
                    'status' =>  'Error cant stop shiping'));
                exit;
            }
            // update temp
            $result = mysqli_query($db,"SELECT id FROM transport WHERE idCont = ".$containerid." ORDER BY id DESC LIMIT 1");
            if (!$result) {
                echo json_encode(array(
                    'status' =>  'Error Idtransport not found'));
                exit;
            }
            $idtransport = mysqli_fetch_row($result)[0];
            $tempobj = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $temp), true );  
            for ($i=0; $i < count($tempobj['temperatures']); $i++) { 
                $value = $tempobj['temperatures'][$i];
                $sql = "INSERT INTO temperature (IdTrans, temp) VALUES (".$idtransport.", ".$value.");";
                $result = mysqli_query($db,$sql);
                if (!$result) {
                    echo json_encode(array(
                        'status' =>  'Error cant insert temperature'));
                    exit;
                }
            }
            return json_encode("Success end shiping && insert temperature");
        }
        else {
            return "error can't user unauthorized";
        }
    }
?>