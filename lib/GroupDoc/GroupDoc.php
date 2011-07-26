<?php

require_once 'DocRevision.php';


class GroupDoc {

    //internal properties
    public $groupdoc_id;
    public $cache_revision;
    public $cache_data;
    public $created_dt;
    public $modified_dt;


    function __construct() {
        $this->cleardata();
    }

    private function cleardata() {
    
        $this->groupdoc_id = 0;
        $this->cache_revision = 0;
        $this->cache_data = "";
        $this->created_dt = "";
        $this->modified_dt = "";

    }

    private function loadFromRowResult( $row ) {
        $this->groupdoc_id = $row['groupdoc_id'];
        $this->cache_revision = $row['cache_revision'];
        $this->cache_data = $row['cache_data'];
        $this->created_dt = $row['created_dt'];
        $this->modified_dt = $row['modified_dt'];
    }

    public function loadByID( $id ) {
        global $db;

        $this->cleardata();

        $query = sprintf("SELECT * FROM groupdoc WHERE groupdoc_id = %d", clean_int( $id ));
        $result = mysql_query( $query, $db );

        if ( $row = mysql_fetch_assoc( $result ) ) {
            $this->loadFromRowResult( $row );
        }

    }


    public function insertIntoDB() {
        global $db;
        
        if ( $this->project_id <= 0 ) {
            $query = sprintf("INSERT INTO groupdoc ( cache_revision, cache_data, created_dt, modified_dt ) " .
                            " VALUES ( 0, '', NOW(), NOW() ) ");
            $result = mysql_query( $query, $db );
            if ( $result ) {
                $this->groupdoc_id = mysql_insert_id( $db );
                $this->updateDB();
                $this->loadByID( $this->groupdoc_id );
            }
        }
    }

    public function updateDB() {
        global $db;

        if ( $this->groupdoc_id > 0 ) {
        
            $query = sprintf("UPDATE groupdoc SET " .
                            " cache_revision=%d, " .
                            " cache_data='%s', " .
                            " modified_dt = NOW() " .
                            " WHERE groupdoc_id=%d ",
                            clean_int( $this->cache_revision ),
                            clean_string( $this->cache_data ),
                            clean_int( $this->groupdoc_id ) );
            $result = mysql_query( $query, $db );
        
        }
    }

    public function deleteFromDB() {
        global $db;

        if ( $this->groupdoc_id > 0 ) {

            //DELETE ALL SUB-CONTENT
            $revs =  DocRevision::FetchByGroupDoc( $this->groupdoc_id );
            foreach ( $revs as $rev ) {
                $rev->deleteFromDB();
            }

            $query = sprintf("DELETE FROM groupdoc WHERE groupdoc_id = %d", clean_int( $this->groupdoc_id ) );
            $result = mysql_query( $query, $db );
            if ( $result ) {
                $this->groupdoc_id = 0;
            }
        }
    }

    public function incorporateRevisions() {
        
        if ( $this->groupdoc_id > 0 ) {
            //DELETE ALL SUB-CONTENT
            $revs =  DocRevision::FetchByGroupDoc( $this->groupdoc_id, $this->cache_revision );
            foreach ( $revs as $rev ) {

                if ( $rev->operation == 'add' ) {
                    $this->cache_data = substr( $this->cache_data, 0, $rev->location ) . $rev->data . substr( $this->cache_data, $rev->location );
                } else if ( $rev->operation == 'del' ) {
                    $this->cache_data = substr( $this->cache_data, 0, $rev->location ) . substr( $this->cache_data, ( $rev->location + strlen( $rev->data ) ) );
                }

                $this->cache_revision = $rev->docrevision_id;
            }

            return count( $revs );

        }

        return 0;

    }

}
