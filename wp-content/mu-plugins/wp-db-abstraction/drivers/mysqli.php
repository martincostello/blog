<?php
/**
 * WordPress DB Class
 *
 * Original code from {@link http://php.justinvincent.com Justin Vincent (justin@visunet.ie)}
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 */

/**
 * WordPress Database Access Abstraction Object
 *
 * It is possible to replace this class with your own
 * by setting the $wpdb global variable in wp-content/db.php
 * file with your class. You can name it wpdb also, since
 * this file will not be included, if the other file is
 * available.
 *
 * @link http://codex.wordpress.org/Function_Reference/wpdb_Class
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 */
class mysqli_wpdb extends wpdb {

    /**
     * Connects to the database server and selects a database
     *
     * PHP5 style constructor for compatibility with PHP5. Does
     * the actual setting up of the class properties and connection
     * to the database.
     *
     * @link http://core.trac.wordpress.org/ticket/3354
     * @since 2.0.8
     *
     * @param string $dbuser MySQL database user
     * @param string $dbpassword MySQL database password
     * @param string $dbname MySQL database name
     * @param string $dbhost MySQL database host
     */
    function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
        if(!extension_loaded('mysqli')) {
            $this->bail('
<h1>Extension Not Loaded</h1>
<p>The mysqli PHP extension is not loaded properly or available for PHP to use.</p>
<ul>
<li>Check your phpinfo</li>
<li>Make sure it is loaded in your php ini file</li>
<li>Turn on display_errors and display_startup_errors so you can detect issues with loading the module.</li>
</ul>');
            return;
        }

        parent::__construct( $dbuser, $dbpassword, $dbname, $dbhost );
    }

    /**
     * Sets the connection's character set.
     *
     * @since 3.1.0
     *
     * @param resource $dbh     The resource given by mysql_connect
     * @param string   $charset The character set (optional)
     * @param string   $collate The collation (optional)
     */
    function set_charset($dbh, $charset = null, $collate = null) {
        if ( !isset($charset) )
            $charset = $this->charset;
        if ( !isset($collate) )
            $collate = $this->collate;
        if ( $this->has_cap( 'collation', $dbh ) && !empty( $charset ) ) {
            if ( function_exists( 'mysqli_set_charset' ) && $this->has_cap( 'set_charset', $dbh ) ) {
                mysqli_set_charset( $dbh, $charset );
                $this->real_escape = true;
            } else {
                $query = $this->prepare( 'SET NAMES %s', $charset );
                if ( ! empty( $collate ) )
                    $query .= $this->prepare( ' COLLATE %s', $collate );
                mysqli_query( $dbh, $query );
            }
        }
    }

    /**
     * Selects a database using the current database connection.
     *
     * The database name will be changed based on the current database
     * connection. On failure, the execution will bail and display an DB error.
     *
     * @since 0.71
     *
     * @param string $db MySQL database name
     * @param resource $dbh Optional link identifier.
     * @return null Always null.
     */
    function select( $db, $dbh = null) {
        if ( is_null($dbh) )
            $dbh = $this->dbh;

        if ( !@mysqli_select_db( $dbh, $db ) ) {
            $this->ready = false;
            $this->bail( sprintf( /*WP_I18N_DB_SELECT_DB*/'
<h1>Can&#8217;t select database</h1>
<p>We were able to connect to the database server (which means your username and password is okay) but not able to select the <code>%1$s</code> database.</p>
<ul>
<li>Are you sure it exists?</li>
<li>Does the user <code>%2$s</code> have permission to use the <code>%1$s</code> database?</li>
<li>On some systems the name of your database is prefixed with your username, so it would be like <code>username_%1$s</code>. Could that be the problem?</li>
</ul>
<p>If you don\'t know how to set up a database you should <strong>contact your host</strong>. If all else fails you may find help at the <a href="http://wordpress.org/support/">WordPress Support Forums</a>.</p>'/*/WP_I18N_DB_SELECT_DB*/, $db, $this->dbuser ), 'db_select_fail' );
            return;
        }
    }

    /**
     * Real escape, using mysql_real_escape_string() or addslashes()
     *
     * @see mysql_real_escape_string()
     * @see addslashes()
     * @since 2.8.0
     * @access private
     *
     * @param  string $string to escape
     * @return string escaped
     */
    function _real_escape( $string ) {
        if ( $this->dbh && $this->real_escape )
            return mysqli_real_escape_string( $this->dbh, $string );
        else
            return addslashes( $string );
    }

    /**
     * Print SQL/DB error.
     *
     * @since 0.71
     * @global array $EZSQL_ERROR Stores error information of query and error string
     *
     * @param string $str The error to display
     * @return bool False if the showing of errors is disabled.
     */
    function print_error( $str = '' ) {
        global $EZSQL_ERROR;

        if ( !$str )
            $str = mysqli_error( $this->dbh );
        $EZSQL_ERROR[] = array( 'query' => $this->last_query, 'error_str' => $str );

        if ( $this->suppress_errors )
            return false;

        if ( $caller = $this->get_caller() )
            $error_str = sprintf( /*WP_I18N_DB_QUERY_ERROR_FULL*/'WordPress database error %1$s for query %2$s made by %3$s'/*/WP_I18N_DB_QUERY_ERROR_FULL*/, $str, $this->last_query, $caller );
        else
            $error_str = sprintf( /*WP_I18N_DB_QUERY_ERROR*/'WordPress database error %1$s for query %2$s'/*/WP_I18N_DB_QUERY_ERROR*/, $str, $this->last_query );

        if ( function_exists( 'error_log' )
            && ( $log_file = @ini_get( 'error_log' ) )
            && ( 'syslog' == $log_file || @is_writable( $log_file ) )
            )
            @error_log( $error_str );

        // Are we showing errors?
        if ( ! $this->show_errors )
            return false;

        // If there is an error then take note of it
        if ( is_multisite() ) {
            $msg = "WordPress database error: [$str]\n{$this->last_query}\n";
            if ( defined( 'ERRORLOGFILE' ) )
                error_log( $msg, 3, ERRORLOGFILE );
            if ( defined( 'DIEONDBERROR' ) )
                wp_die( $msg );
        } else {
            $str   = htmlspecialchars( $str, ENT_QUOTES );
            $query = htmlspecialchars( $this->last_query, ENT_QUOTES );

            print "<div id='error'>
            <p class='wpdberror'><strong>WordPress database error:</strong> [$str]<br />
            <code>$query</code></p>
            </div>";
        }
    }

    /**
     * Connect to and select database
     *
     * @since 3.0.0
     */
    function db_connect() {
    
        if ( WP_DEBUG ) {
            $this->dbh = mysqli_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $this->dbname );
        } else {
            $this->dbh = @mysqli_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $this->dbname );
        }
    
        if ( !$this->dbh ) {
            $this->bail( sprintf( /*WP_I18N_DB_CONN_ERROR*/"
    <h1>Error establishing a database connection</h1>
    <p>This either means that the username and password information in your <code>wp-config.php</code> file is incorrect or we can't contact the database server at <code>%s</code>. This could mean your host's database server is down.</p>
    <ul>
    <li>Are you sure you have the correct username and password?</li>
    <li>Are you sure that you have typed the correct hostname?</li>
    <li>Are you sure that the database server is running?</li>
    </ul>
    <p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='http://wordpress.org/support/'>WordPress Support Forums</a>.</p>
    "/*/WP_I18N_DB_CONN_ERROR*/, $this->dbhost ), 'db_connect_fail' );
    
            return;
        }
    
        $this->set_charset( $this->dbh );
    
        $this->ready = true;
    }

    /**
     * Perform a MySQL database query, using current database connection.
     *
     * More information can be found on the codex page.
     *
     * @since 0.71
     *
     * @param string $query Database query
     * @return int|false Number of rows affected/selected or false on error
     */
    function query( $query ) {
        if ( ! $this->ready )
            return false;

        // some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
        if ( function_exists( 'apply_filters' ) )
            $query = apply_filters( 'query', $query );

        $return_val = 0;
        $this->flush();

        // Log how the function was called
        $this->func_call = "\$db->query(\"$query\")";

        // Keep track of the last query for debug..
        $this->last_query = $query;

        if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
            $this->timer_start();

        $this->result = @mysqli_query( $this->dbh, $query );
        $this->num_queries++;

        if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
            $this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );

        // If there is an error then take note of it..
        if ( $this->last_error = mysqli_error( $this->dbh ) ) {
            $this->print_error();
            return false;
        }

        if ( preg_match( "/^\\s*(insert|delete|update|replace|alter) /i", $query ) ) {
            $this->rows_affected = mysqli_affected_rows( $this->dbh );
            // Take note of the insert_id
            if ( preg_match( "/^\\s*(insert|replace) /i", $query ) ) {
                $this->insert_id = mysqli_insert_id($this->dbh);
            }
            // Return number of rows affected
            $return_val = $this->rows_affected;
        } else {
            $i = 0;
            while ( $i < @mysqli_num_fields( $this->result ) ) {
                $this->col_info[$i] = @mysqli_fetch_field( $this->result );
                $i++;
            }
            $num_rows = 0;
            while ( $row = @mysqli_fetch_object( $this->result ) ) {
                $this->last_result[$num_rows] = $row;
                $num_rows++;
            }

            @mysqli_free_result( $this->result );

            // Log number of rows the query returned
            // and return number of rows selected
            $this->num_rows = $num_rows;
            $return_val     = $num_rows;
        }

        return $return_val;
    }

    /**
     * The database version number.
     *
     * @since 2.7.0
     *
     * @return false|string false on failure, version number on success
     */
    function db_version() {
        return preg_replace( '/[^0-9.].*/', '', mysqli_get_server_info( $this->dbh ) );
    }
}

?>
