<?php
# phpinfo();
?>
<?php
/**
$serverName = "CHANGEME.database.windows.net,1433";
$connOptions = array("UID"=>"CHANGEME@CHANGEME", "PWD"=>"CHANGEME", "Database"=>"CHANGEME");
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
*/
?>