<?php


class DocRevision {

    //internal properties
    public $docrevision_id;
    public $groupdoc_id;
    public $operation;
    public $location;
    public $data;
    public $revision_dt;


    function __construct() {
        $this->cleardata();
    }

    private function cleardata() {
    
        $this->docrevision_id = 0;
        $this->groupdoc_id = 0;
        $this->operation = "";
        $this->location = 0;
        $this->data = "";
        $this->revision_dt = "";

    }

    private function loadFromRowResult( $row ) {
        $this->docrevision_id = $row['docrevision_id'];
        $this->groupdoc_id = $row['groupdoc_id'];
        $this->operation = $row['operation'];
        $this->location = $row['location'];
        $this->data = $row['data'];
        $this->revision_dt = $row['revision_dt'];
    }

    public function loadByID( $id ) {
        global $db;

        $this->cleardata();

        $query = sprintf("SELECT * FROM docrevision WHERE docrevision_id = %d", clean_int( $id ));
        $result = mysql_query( $query, $db );

        if ( $row = mysql_fetch_assoc( $result ) ) {
            $this->loadFromRowResult( $row );
        }

    }


    public function insertIntoDB() {
        global $db;
        
        if ( $this->docrevision_id <= 0 ) {
            $query = sprintf("INSERT INTO docrevision ( groupdoc_id, operation, location, data, revision_dt ) " .
                            " VALUES ( %d, '%s', %d, '%s', NOW() ) ",
                            clean_int( $this->groupdoc_id ),
                            clean_string( $this->operation ),
                            clean_int( $this->location ),
                            clean_string( $this->data )
                        );
            $result = mysql_query( $query, $db );
            if ( $result ) {
                $this->docrevision_id = mysql_insert_id( $db );
                
                //Optimization: For speed, we'll trust what we inserted made it.
                //$this->updateDB();
                //$this->loadByID( $this->groupdoc_id );
            }
        }
    }

    //TODO: should we ever use this method?
    public function updateDB() {
        global $db;

        if ( $this->docrevision_id > 0 ) {
        
            $query = sprintf("UPDATE docrevision SET " .
                            " groupdoc_id=%d, " .
                            " operation='%s', " .
                            " location=%d, " .
                            " data='%s', " .
                            " revision_dt = NOW() " .
                            " WHERE docrevision_id=%d ",
                            clean_int( $this->groupdoc_id ),
                            clean_string( $this->operation ),
                            clean_int( $this->location ),
                            clean_string( $this->data ),
                            clean_int( $this->docrevision_id ));
            $result = mysql_query( $query, $db );
        
        }
    }

    public function deleteFromDB() {
        global $db;

        if ( $this->docrevision_id > 0 ) {

            $query = sprintf("DELETE FROM docrevision WHERE docrevision_id = %d", clean_int( $this->docrevision_id ) );
            $result = mysql_query( $query, $db );
            if ( $result ) {
                $this->docrevision_id = 0;
            }
        }
    }

    public static function FetchByGroupDoc( $docid, $revision ) {
        global $db;
        
        if ( !isset( $revision ) ) {
            $revision = 0;
        }
        $revs = array();
        $query = sprintf( "SELECT * FROM docrevision WHERE groupdoc_id = %d and docrevision_id > %d ORDER BY docrevision_id ASC" , clean_int($docid), clean_int( $revision ) );
        $result = mysql_query( $query, $db );
        while ( $row = mysql_fetch_assoc( $result ) ) {
            $r = new DocRevision();
            $r->loadFromRowResult( $row );
            $revs[] = $r;
        }

        return $revs;

    }


}
