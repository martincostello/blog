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

require_once dirname(dirname(__FILE__)) . 
        DIRECTORY_SEPARATOR . 'translations' . 
        DIRECTORY_SEPARATOR . 'sqlsrv' . 
        DIRECTORY_SEPARATOR . 'translations.php';

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
 * @final
 */
class mssql_wpdb extends SQL_Translations {

    /**
     * Saved result of the last translated query made
     *
     * @since 1.2.0
     * @access private
     * @var array
     */
    var $previous_query;

    /**
    * Database type
    *
    * @access public
    * @var string
    */
    var $db_type = 'mssql';

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
        if(!extension_loaded('mssql')) {
            $this->bail('
<h1>Extension Not Loaded</h1>
<p>The sqlsrv PHP extension is not loaded properly or available for PHP to use.</p>
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
        // Does nothing, this has no meaning
    }

    /**
     * Set $this->charset and $this->collate
     *
     * @since 3.1.0
     */
    function init_charset() {
            if ( function_exists('is_multisite') && is_multisite() ) {
                    $this->charset = 'utf8';
                    if ( defined( 'DB_COLLATE' ) && DB_COLLATE )
                            $this->collate = DB_COLLATE;
            } elseif ( defined( 'DB_COLLATE' ) ) {
                    $this->collate = DB_COLLATE;
            }

            if ( defined( 'DB_CHARSET' ) )
                    $this->charset = DB_CHARSET;
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
     * @return null Always null.
     */
    function select( $db, $dbh = null ) {
        if ( is_null($dbh) )
            $dbh = $this->dbh;

        if ( !@mssql_select_db($db, $dbh) ) {
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
     * Weak escape, using addslashes()
     *
     * @see addslashes()
     * @since 2.8.0
     * @access private
     *
     * @param string $string
     * @return string
     */
    function _weak_escape( $string ) {
        // sql server requires '' escaping
        return str_replace("'", "''", $string);
    }

    /**
     * Real escape, using mysql_real_escape_string() or addslashes()
     *
     * @see mysql_real_escape_string()
     * @see addslashes()
     * @since 2.8
     * @access private
     *
     * @param  string $string to escape
     * @return string escaped
     */
    function _real_escape( $string ) {
        // there is no built in escape method for sql server
        return str_replace("'", "''", $string);
    }

    /**
     * Prepares a SQL query for safe execution. Uses sprintf()-like syntax.
     *
     * The following directives can be used in the query format string:
     *   %d (decimal number)
     *   %s (string)
     *   %% (literal percentage sign - no argument needed)
     *
     * Both %d and %s are to be left unquoted in the query string and they need an argument passed for them.
     * Literals (%) as parts of the query must be properly written as %%.
     *
     * This function only supports a small subset of the sprintf syntax; it only supports %d (decimal number), %s (string).
     * Does not support sign, padding, alignment, width or precision specifiers.
     * Does not support argument numbering/swapping.
     *
     * May be called like {@link http://php.net/sprintf sprintf()} or like {@link http://php.net/vsprintf vsprintf()}.
     *
     * Both %d and %s should be left unquoted in the query string.
     *
     * <code>
     * wpdb::prepare( "SELECT * FROM `table` WHERE `column` = %s AND `field` = %d", 'foo', 1337 )
     * wpdb::prepare( "SELECT DATE_FORMAT(`field`, '%%c') FROM `table` WHERE `column` = %s", 'foo' );
     * </code>
     *
     * @link http://php.net/sprintf Description of syntax.
     * @since 2.3.0
     *
     * @param string $query Query statement with sprintf()-like placeholders
     * @param array|mixed $args The array of variables to substitute into the query's placeholders if being called like
     *      {@link http://php.net/vsprintf vsprintf()}, or the first variable to substitute into the query's placeholders if
     *      being called like {@link http://php.net/sprintf sprintf()}.
     * @param mixed $args,... further variables to substitute into the query's placeholders if being called like
     *      {@link http://php.net/sprintf sprintf()}.
     * @return null|false|string Sanitized query string, null if there is no query, false if there is an error and string
     *      if there was something to prepare
     */
    function prepare( $query = null ) { // ( $query, *$args )
        if ( is_null( $query ) ) {
                return;
        }
        $this->prepare_args = func_get_args();
        array_shift($this->prepare_args);
        // If args were passed as an array (as in vsprintf), move them up
        if ( isset($this->prepare_args[0]) && is_array($this->prepare_args[0]) ) {
                $this->prepare_args = $this->prepare_args[0];
        }
        $flag = '--PREPARE';
        foreach($this->prepare_args as $key => $arg){
                if (is_serialized($arg)) {
                        $flag = '--SERIALIZED';
                }
        }
        $query = str_replace("'%s'", '%s', $query); // in case someone mistakenly already singlequoted it
        $query = str_replace('"%s"', '%s', $query); // doublequote unquoting
        $query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s
        array_walk($this->prepare_args, array(&$this, 'escape_by_ref'));
        return @vsprintf($query, $this->prepare_args).$flag;
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
            $str = mssql_get_last_message();

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

        // Make sure the version is the same for your ntwdblib.dll.
        // The TDS library and the ntwdblib.dll can't be speaking two different protocols.
        // note we don't OVERWRITE an existing TDSVER environment
        // a properly set up environment might have this at 8.0 or higher
        if (false === getenv('TDSVER')) {
            putenv('TDSVER=70');
        }

        // Set text limit sizes to max BEFORE connection is made
        ini_set('mssql.textlimit', 2147483647);
        ini_set('mssql.textsize', 2147483647);

        if (get_magic_quotes_gpc()) {
            $this->dbhost = trim(str_replace("\\\\", "\\", $dbhost));
        }

        if ( WP_DEBUG ) {
                $this->dbh = mssql_connect( $this->dbhost, $this->dbuser, $this->dbpassword);
        } else {
                $this->dbh = @mssql_connect( $this->dbhost, $this->dbuser, $this->dbpassword);
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

        mssql_min_error_severity(0);
        mssql_min_message_severity(17);
        @mssql_query('SET TEXTSIZE 2147483647');

        $this->ready = true;

        $this->select( $this->dbname, $this->dbh );
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
    function query( $query, $translate = true ) {
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

        $dbh = $this->dbh;

        // Make Necessary Translations
        if ($translate === true) {
            $query = $this->translate($query);
            $this->previous_query = $query;
        }

        if ($this->preceeding_query !== false) {
            if (is_array($this->preceeding_query)) {
                foreach ($this->preceeding_query as $p_query) {
                    @mssql_query($sub_query, $dbh);
                }
            } else {
                @mssql_query($this->preceeding_query, $dbh);
            }
            $this->preceeding_query = false;
        }
        
        // Check if array of queries (this happens for INSERTS with multiple VALUES blocks)
        if (is_array($query)) {
            foreach ($query as $sub_query) {
                $this->_pre_query();
                $this->result = @mssql_query($sub_query, $dbh);
                $return_val = $this->_post_query($sub_query, $dbh);
            }
        } else {
            $this->_pre_query();
            $this->result = @mssql_query($query, $dbh);
            $return_val = $this->_post_query($query, $dbh);
        }
        
        if ($this->following_query !== false) {
            if (is_array($this->following_query)) {
                    foreach ($this->following_query as $f_query) {
                            @mssql_query($f_query, $dbh);
                    }
            } else {
                    @mssql_query($this->following_query, $dbh);
            }
            $this->following_query = false;
        }

        return $return_val;
    }

    function _pre_query() {
        if ( defined('SAVEQUERIES') && SAVEQUERIES ) {
                $this->timer_start();
        }
    }

    function _post_query($query, $dbh) {
        ++$this->num_queries;
        // If there is an error then take note of it..
        if ( $this->result == FALSE && $this->last_error = mssql_get_last_message() ) {
            $this->log_query($this->last_error);
            //var_dump($query);
            //var_dump($this->translation_changes);
            $this->print_error();
            return false;
        }

        if ( defined('SAVEQUERIES') && SAVEQUERIES ) {
            $this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );
        }

        if ( preg_match("/^\\s*(insert|delete|update|replace) /i",$query) ) {

            $this->rows_affected = mssql_rows_affected($dbh);
            // Take note of the insert_id
            if ( preg_match("/^\\s*(insert|replace) /i",$query) ) {
                $result = @mssql_fetch_object(@mssql_query("SELECT SCOPE_IDENTITY() AS ID"));
                $this->insert_id = $result->ID;
            }

            $return_val = $this->rows_affected;
        } else {

            $i = 0;
            while ($i < @mssql_num_fields($this->result)) {
                $field = @mssql_fetch_field($this->result, $i);
                $new_field = new stdClass();
                $new_field->name = $field->name;
                $new_field->table = $field->column_source;
                $new_field->def = null;
                $new_field->max_length = $field->max_length;
                $new_field->not_null = true;
                $new_field->primary_key = null;
                $new_field->unique_key = null;
                $new_field->multiple_key = null;
                $new_field->numeric = $field->numeric;
                $new_field->blob = null;
                $new_field->type = $field->type;
                if(isset($field->unsigned)) {
                        $new_field->unsigned = $field->unsigned;
                } else {
                        $new_field->unsigned = null;
                }
                $new_field->zerofill = null;
                $this->col_info[$i] = $new_field;
                $i++;
            }
            $num_rows = 0;
            while ( $row = @mssql_fetch_object($this->result) ) {
                $this->last_result[$num_rows] = $row;
                $num_rows++;
            }
            $this->last_result = $this->fix_results($this->last_result);
            // perform limit
            if (!empty($this->limit)) {
                $this->last_result = array_slice($this->last_result, $this->limit['from'], $this->limit['to']);
                $num_rows = count($this->last_result);
            }
    
            @mssql_free_result($this->result);
    
            // Log number of rows the query returned
            $this->num_rows = $num_rows;
    
            // Return number of rows selected
            $return_val = $this->num_rows;
        }

        $this->log_query();
        return $return_val;
    }

    function log_query($error = null)
    {
        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return; //bail
        }
    
        if (!defined('QUERY_LOG')) {
            $log = ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 'queries.log';
        } else {
            $log = QUERY_LOG;
        }
    
        if (!empty($this->queries)) {
            $last_query = end($this->queries);
            if (preg_match( "/^\\s*(insert|delete|update|replace|alter) /i", $last_query[0])) {
                $result = serialize($this->rows_affected);
            } else {
                $result = serialize($this->last_result);
            }
            if (is_array($error)) {
                $error = serialize($error);
            }
            $q = str_replace("\n", ' ', $last_query[0]);
            file_put_contents($log, $q . '|~|' . $result . '|~|' . $error . "\n", FILE_APPEND);
        }
    }

    /**
     * The database version number.
     *
     * @return false|string false on failure, version number on success
     */
    function db_version() {
        return '5.1';
    }
}
