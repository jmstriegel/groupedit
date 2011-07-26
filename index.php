<?php
$templatesdir = 'templates/';
$includesdir = 'includes/';

require_once $includesdir . 'config.inc';
require_once $includesdir . 'common.inc';
require_once $includesdir . 'database.inc';

//libraries we're using (these are in /lib/)
require_once 'GroupDoc/GroupDoc.php';

//Sets up normal page variables, performs auth, runs PageMain()
require_once $includesdir . 'standard_page_logic.inc';

function PageMain() {
    global $template, $templatesdir, $includesdir, $config;

    //Do any page logic here.
    //If $using_db is set in standard_page_logic.inc the global $db
    //will be set. Most db queries should be done in a class library, though.

    $template['title'] = 'GroupDoc Demo';


    $docid = 1;

    $doc = new GroupDoc();
    $doc->loadByID( $docid );
    $newrevs = $doc->incorporateRevisions();
    if ( $newrevs > 0 ) {
        $doc->updateDB();
    }


    $template['doc'] = $doc;

    require_once $templatesdir . 'pages/index.inc';

}
