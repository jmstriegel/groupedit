<?php include $templatesdir . 'shared/header.inc' ?>

<h1 style="font-family:georgia,serif;font-size:18px;">GroupDoc Demo</h1>
<p  style="font-family:georgia,serif;font-size:12px;line-height:14px;width:600px;">The textarea below supports semi-realtime group editing by multiple parties. Content edits are diffed and merged on the fly in order to keep all views of the document consistent.<br /><br />Anything you type here will be seen by everyone else, so please be nice.<br />Jason</p>
<form id="doceditor">
<input type="hidden" id="doc_id" value="<?= $template['doc']->groupdoc_id ?>" />
<input type="hidden" id="rev_id" value="<?= $template['doc']->cache_revision ?>" />
<input type="hidden" id="rev_data" value="<?= htmlentities( $template['doc']->cache_data ) ?>" />
<textarea id="editor" style="width:800px;height:300px;font-family:courier;font-size:12px;line-height:14px"></textarea>
</form>
<div id="message"></div>

<script>
$(document).ready( function() {

    $('#editor').val( $('#rev_data').val() );
    updateFromServer();

    $('#editor').keypress( function(e) {
        
        if ( inupdate ) {
            //e.preventDefault();
        } else {
            queueUpdate( 300 );
        }
    });

    $('#editor').keyup( function(e) {

        if ( inupdate ) {
            //e.preventDefault();
        }
    });

});


var lastrev = "";
var submittedversion = "";
var updatesaftersubmission = "";
var selection = getSelection( $('#editor') );
var diffqueue = new Array();
var lastdiffs = new Array();

var updateinterval = 1000;

function findDiffs( origrev, updaterev ) {

    var diff = new diff_match_patch();
    var diffs = diff.diff_main( origrev, updaterev );
    var diffpos=0;
    var diffarr = new Array();
    for ( var i in diffs ) {
        var diffpart = diffs[i];
        if ( diffpart[0] == DIFF_INSERT ) {

            diffarr.push({
                    'doc_id': $('#doc_id').val(),
                    'rev_id': $('#rev_id').val(),
                    'loc': diffpos,
                    'op': 'add',
                    'data': diffpart[1]
                    });

        } else if ( diffpart[0] == DIFF_DELETE ) {
            
            diffarr.push({
                    'doc_id': $('#doc_id').val(),
                    'rev_id': $('#rev_id').val(),
                    'loc': diffpos,
                    'op': 'del',
                    'data': diffpart[1]
                    });
            
            diffpos += diffpart[1].length;
        } else {
            diffpos += diffpart[1].length;
        }
    } 

    return diffarr;

}

function handleUpdates() {


    if ( diffqueue.length > 0 ) {
    //we already have an update queue - submit all these
        var diffdata = diffqueue.shift();
        $.ajax({
            'url': 'api/submitrevision.php', 
            'data': diffdata,
            'success': function( data ) { 
                    handleUpdates( ); //burn through these updates but don't seek new diffs
                },
            'dataType': 'json',
            'type': 'POST',
            'async': true
        });
        return;
    }

    
    //we only get here if there were no updates to be made.
    updateFromServer();
}

function updateFromServer() {
    $.ajax({
        'url': 'api/getupdates.php',
        'data': { 
            'doc_id': $('#doc_id').val(),
            'rev_id': $('#rev_id').val() 
            },
        'dataType': 'json',
        'async': true,
        'success': function( data ) {

            //What's happened since we submitted updates or this response was in flight
            updatesaftersubmission = $('#editor').val();
            var postsubmitdiffs = findDiffs( submittedversion, updatesaftersubmission );
            selection = getSelection( $('#editor') );

            if ( data['rev_id'] == $('#rev_id').val() && data['revisions'].length > 0 ) {
                
                var lastscrolltop = $('#editor').scrollTop();



                //apply revisions to original data
                //we'll compare this later to what was submitted and make necessary cursor adjustments
                var cache = $('#rev_data').val();
                for( var r in data['revisions'] ) {
                    var rev = data['revisions'][r];
                    rev['loc'] = parseInt( rev['loc'] );
                    if ( rev['op'] == 'add' ) {
                        cache = cache.substr( 0, rev['loc'] ) + rev['data'] + cache.substr( rev['loc'] );
                    } else if ( rev['op'] == 'del' ) {
                        cache = cache.substr( 0, rev['loc'] ) + cache.substr( rev['loc'] + rev['data'].length );
                    }
                    $('#rev_data').val( cache );
                    $('#rev_id').val( rev['rev'] );
                }


                //compare the new revision against what we submitted
                var newdiffs = findDiffs( submittedversion, cache );
                for ( var r in newdiffs ) {
                    var rev = newdiffs[r];

                    if ( rev['op'] == 'add' ) {
                        
                        //adjust cursor position if text added before cursor
                        if ( rev['loc'] <= selection['start'] ) {
                            selection['start'] += rev['data'].length;
                            selection['end'] += rev['data'].length;
                        }

                        //adjust post-submission updates if text added before update
                        for ( var u in postsubmitdiffs ) {
                            if ( rev['loc'] <= postsubmitdiffs[u]['loc'] ) {
                                postsubmitdiffs[u]['loc'] += rev['data'].length;
                            }
                        }
                    
                    } else if ( rev['op'] == 'del' ) {
                        //adjust cursor position if text deleted before cursor
                        //this is a little wonky if they deleted over the user-added span
                        if ( rev['loc'] < selection['start'] ) {
                            var minloc = rev['loc'];
                            selection['start'] -= rev['data'].length;
                            selection['end'] -= rev['data'].length;
                            if ( selection['start'] < minloc ) selection['start'] = minloc;
                            if ( selection['end'] < minloc ) selection['end'] = minloc;
                        }
                        
                        //adjust post-submission updates if text deleted before update
                        for ( var u in postsubmitdiffs ) {
                            if ( rev['loc'] < postsubmitdiffs[u]['loc'] ) {
                                var minloc = rev['loc'];
                                postsubmitdiffs[u]['loc'] -= rev['data'].length;
                                if ( postsubmitdiffs[u]['loc'] < minloc ) postsubmitdiffs[u]['loc'] = minloc;
                            }
                        }

                    }
                }

                
                //editor is now up to date with latest official revision.
                $('#editor').val( $('#rev_data').val() );


                //let's apply any of the updates that happened in-flight
                for( var r in postsubmitdiffs ) {
                    var rev = postsubmitdiffs[r];
                    rev['loc'] = parseInt( rev['loc'] );
                    if ( rev['op'] == 'add' ) {
                        var cache = $('#editor').val();
                        $('#editor').val( cache.substr( 0, rev['loc'] ) + rev['data'] + cache.substr( rev['loc'] ) );

                    } else if ( rev['op'] == 'del' ) {
                        var cache = $('#editor').val();
                        $('#editor').val( cache.substr( 0, rev['loc'] ) + cache.substr( rev['loc'] + rev['data'].length ) );
                    }
                }

                setSelection( $('#editor'), selection );


                $('#editor').scrollTop(lastscrolltop);


            }

            inupdate = false;
            if ( data['revisions'].length > 0 ) {
                updateinterval = 1000;
            } else {
                updateinterval += 2000;
                if ( updateinterval > 20000 )
                    updateinterval = 20000;
            }
            queueUpdate( updateinterval );
        }
    });

}


var updatetimer = null;
var inupdate = false;
function queueUpdate( timeout ) {

    if ( inupdate ) {
        setTimeout( 
            function() { 
                queueUpdate( timeout );
            },100 );
        return;
    }

    if ( updatetimer != null ) {
        clearTimeout( updatetimer );
        updatetimer = null;
    }

    updatetimer = setTimeout( 
        function() {
            inupdate = true;

            lastrev = $('#rev_data').val();
            submittedversion = $('#editor').val();

            diffqueue = findDiffs( lastrev, submittedversion );
            
            //cache a deep copy of the submitted diffs. we need this later.
            lastdiffs = jQuery.extend(true, [], diffqueue);
            
            handleUpdates();
        }, timeout 
    );
}



            // 
            // Not fully tested in IE. Derived from:
            // http://stackoverflow.com/questions/263743/how-to-get-cursor-position-in-textarea
            //
            function getSelection( elem ) {

                var result = { start: 0, end: 0 };
                var field = elem.jquery ? elem.get(0) : elem;

                if ( field.selectionStart ) {
                    result['start'] = field.selectionStart;
                    result['end'] = field.selectionEnd;
                } else if ( field.selection ) {

                    field.focus(); 

                    var r = document.selection.createRange(); 
                    if (r != null) { 

                        var re = field.createTextRange(); 
                        var rc = re.duplicate(); 
                        re.moveToBookmark(r.getBookmark()); 
                        rc.setEndPoint('EndToStart', re); 

                        result['start'] = rc.text.length;
                        result['end'] = rc.text.length + r.text.length;
                    }
                }

                return result;

            }

            function setSelection( elem, selection ) {
            
                var field = elem.jquery ? elem.get(0) : elem;
                if ( field.setSelectionRange ) {
                    field.setSelectionRange( selection['start'], selection['end'] );
                } else if ( field.createTextRange ) {
                    var re = field.createTextRange();
                    re.collapse();
                    re.moveEnd('character', selection['end']);
                    re.moveStart('character', selection['start']);
                    re.select();
                }
            }



</script>

<?php include $templatesdir . 'shared/footer.inc' ?>
