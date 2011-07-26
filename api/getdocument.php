<?php
$templatesdir = '../templates/';
$includesdir = '../includes/';

require_once $includesdir . 'config.inc';
require_once $includesdir . 'common.inc';
require_once $includesdir . 'database.inc';

//libraries we're using (these are in /lib/)
require_once 'GroupDoc/GroupDoc.php';
require_once 'GroupDoc/DocRevision.php';

//Sets up normal page variables, performs auth, runs PageMain()
require_once $includesdir . 'standard_page_logic.inc';

function PageMain() {
    global $template, $templatesdir, $includesdir, $config;

    //Do any page logic here.
    //If $using_db is set in standard_page_logic.inc the global $db
    //will be set. Most db queries should be done in a class library, though.

    //This simple example does stuff internally. For complicated json, you
    //might be better off making a json template in templates/js


    $template['callback'] = getVar('callback');
    $template['echo'] = getVar('echo');
    
    
    if ( $template['callback'] != "" && preg_match( '/^\w+$/', $template['callback']  )) {
        //use jsonp
        header('Content-type: text/javascript');
    } else {
        //standard json
        header('Content-type: application/json');
    }

    $doc = new GroupDoc();
    
    $docid = getVar('doc_id');
    if ( $docid != "" ) {
        $doc->loadById( $docid );
        $newrevs = $doc->incorporateRevisions();
        if ( $newrevs > 0 ) {
            $doc->updateDB();
        }
    }

    $responsedata = json_encode(
        array( 
            'doc_id' => $doc->groupdoc_id, 
            'rev' => $doc->cache_revision,
            'data' => $doc->cache_data
        )
    );
    
    //wrap jsonp if necessary
    if ( $template['callback'] != "" && preg_match( '/^\w+$/', $template['callback']  ) ) {
        $responsedata = $template['callback'] . '(' . $responsedata . ');';
    }

    echo $responsedata;
}
