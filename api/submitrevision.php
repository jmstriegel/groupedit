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
    
    
    if ( $template['callback'] != "" && preg_match( '/^\w+$/', $template['callback']  ) ) {
        //use jsonp
        header('Content-type: text/javascript');
    } else {
        //standard json
        header('Content-type: application/json');
    }

    $todorevs = array();

    $doc_id = postVar('doc_id');
    $current_rev = postVar('rev_id');
    $loc = postVar('loc');
    $data = postVar('data');
    $op = postVar('op');


    if ( $doc_id != "" && $current_rev != "" && $loc != "" && $data != "" && $op != "" ) {
    
        $loc = clean_int( $loc );
        $rev = new DocRevision();
    
        $rev->groupdoc_id = $doc_id;
        $rev->operation = $op;
        $rev->data = $data;
        $rev->location = $loc;

        $todorevs[] = $rev;

        $updates = DocRevision::FetchByGroupDoc( $doc_id, $current_rev );


        if ( $op == 'add' ) {
            foreach( $updates as $update ) {
                if ( $update->operation == 'add' ) {
                    if ( $update->location < $todorevs[0]->location ) {
                        $todorevs[0]->location += strlen($update->data);
                    }
                } else if ( $update->operation == 'del' ) {

                    //data was deleted prior to us adding and the position was to the left of where we are adding
                    if ( $update->location < $todorevs[0]->location ) {
                        $minloc = $update->location;
                        $todorevs[0]->location -= strlen($update->data);
                        if ( $todorevs[0]->location < $minloc ) {
                            $todorevs[0]->location = $minloc;
                        }
                    }
                }
            }
        } else if ( $op == 'del' ) {
            foreach( $updates as $update ) {
                if ( $update->operation == 'add' ) {

                    $newtodos = array();
                    foreach( $todorevs as $todo ) {
                        if ( $update->location < $todo->location ) {
                            //data added before our deletion (reposition)
                            $todo->location += strlen($update->data);
                            $newtodos[] = $todo;
                        } else if ( $update->location < ($todo->location + strlen($todo->data) ) ) {
                            //data added between our deletion (split and reposition end)
                            
                            //left half
                            $todo1 = new DocRevision();
                            $todo1->groupdoc_id = $todo->groupdoc_id;
                            $todo1->operation = 'del';
                            $todo1->data = substr( $todo->data, 0, $todo->location + strlen($todo->data) - $update->location );
                            $todo1->location = $todo->location;
                            
                            //right half
                            $todo2 = new DocRevision();
                            $todo2->groupdoc_id = $todo->groupdoc_id;
                            $todo2->operation = 'del';
                            $todo2->data = substr( $todo->data, $todo->location + strlen($todo->data) - $update->location );
                            $todo2->location = $todo->location + strlen($update->data);

                            $newtodos[] = $todo1;
                            $newtodos[] = $todo2;
                        } else {
                            //data added after our deletion (no change)
                            $newtodos[] = $todo;
                        }
                    }
                    $todorevs = $newtodos;
                
                
                } else if ( $update->operation == 'del' ) {
                    
                    //data was deleted prior to us deleting

                    $newtodos = array();
                    foreach( $todorevs as $todo ) {
                        if ( $update->location + strlen($update->data) < $todo->location ) {
                            //and the span was to the left of where we are deleting (reposition)
                            $todo->location -= strlen($update->data);
                            $newtodos[] = $todo;
                        } else if ( $update->location <= $todo->location && ( $update->location + strlen($update->data) ) >= ( $todo->location + strlen($todo->data)  ) ) {
                            //the deleted span included our deletion (skip this todo)
                        
                        } else if ( $update->location <= $todo->location && ( $update->location + strlen($update->data) ) < ( $todo->location + strlen($todo->data)  ) ) {
                            //the deleted span included the front part of our deletion (keep the back part of this todo)
                            $todo->data = substr( $todo->data, ($todo->location + strlen($todo->data)) - ($update->location + strlen($update->data) ) );
                            $newtodos[] = $todo;
                        
                        } else if ( $update->location < ($todo->location + strlen($todo->data) ) ) {
                            //the deleted span includes the tail part of our deletion (keep the front part)
                            
                            //left half
                            $todo->data = substr( $todo->data, 0, $todo->location + strlen($todo->data) - $update->location );
                            $newtodos[] = $todo;
                        } else {
                            //data deleted to the right of our deletion (no change)
                            $newtodos[] = $todo;
                        }
                    }
                    $todorevs = $newtodos;


                }
            }
        }

        foreach ( $todorevs as $tr ) {
            $tr->insertIntoDB();
        }

    }



    //Send back updated list of revisions
    $rds = array();
    if ( $doc_id != "" && $current_rev != "" ) {
        $revs = DocRevision::FetchByGroupDoc( $doc_id, $current_rev );

        foreach ( $revs as $rev ) {
            $rd = array( 'rev' => $rev->docrevision_id,
                    'op' => $rev->operation,
                    'loc' => $rev->location,
                    'data' => $rev->data );
            $rds[] = $rd;
        }
    }
    $revdata = array( 'doc_id' => $doc_id, 'rev_id' => $current_rev, 'revisions' => $rds );

    $responsedata = json_encode( $revdata );


    
    //wrap jsonp if necessary
    if ( $template['callback'] != "" && preg_match( '/^\w+$/', $template['callback']  ) ) {
        $responsedata = $template['callback'] . '(' . $responsedata . ');';
    }

    echo $responsedata;
}
