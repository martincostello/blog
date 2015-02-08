<?php
// Necessary SQL Dialect Translations
class SQL_Translations extends wpdb
{
    // No Translation Necessary
    function translate($query) {
        return $query;
    }
}
