<?php
#phpinfo();
?>
<?php
$serverName = "vuhhxypltc.database.windows.net,1433";
$connOptions = array("UID"=>"wordpress_user@vuhhxypltc", "PWD"=>"?Oe8QeqKj2UWVWS");
$conn = sqlsrv_connect( $serverName, $connOptions );

if( $conn === false ) {
    die( print_r( sqlsrv_errors(), true));
}

if( $client_info = sqlsrv_client_info( $conn)) {
    foreach( $client_info as $key => $value) {
        echo $key.": ".$value."<br />";
    }
} else {
    echo "Error in retrieving client info.<br />";
}
?>