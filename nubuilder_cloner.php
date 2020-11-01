// ## nuBuilder Cloner 1.09
// https://github.com/smalos/nuBuilder4-cloner

function hashCookieSet($h) {
    return !(preg_match('/\#(.*)\#/', $h) || trim($h) == "");
}

function pkWithoutEvent($pk) {
    return substr($pk, 0, -3);
}

function eventFromPk($pk) {
    return substr($pk, -3);
}

function lookupPk($arr, $key) {
    return array_column($arr, $key) [0];
}

function addToArray(array & $arr, $key, $value) {
    array_push($arr, array(
        $key => $value
    ));
}

function getPk($pk) {
    return "#cloner_new_pks#" == '1' ? nuID() : $pk;
}

function getTabList() {

    $t = "#cloner_tabs#";
    return !hashCookieSet($t) || strlen($t) < 3 ? "" : implode(',', json_decode($t));

}

function getFormSource(&$f1) {

    $f1 = "#cloner_f1#";
    if (!hashCookieSet($f1)) {
        $f1 = "#form_id#";
        return true;
    }

    return formExists($f1);

}

function dbQuote($s) {

    global $nuDB;
    return $nuDB->quote($s);

}

function getFormDestination(&$f2) {

    $f2 = "#cloner_f2#";
    if (!hashCookieSet($f2)) {
        $f2 = "";
        return true;
    }

    return formExists($f2);

}

function formSQL() {
    return "SELECT * FROM zzzzsys_form WHERE zzzzsys_form_id  = ? LIMIT 1";
}

function formExists($f) {

    $t = nuRunQuery(formSQL() , [$f]);
    return db_num_rows($t) == 1;

}

function getFormInfo($f) {

    $t = nuRunQuery(formSQL() , [$f]);
    $row = db_fetch_object($t);

    return array(
        "code" => $row->sfo_code,
        "description" => $row->sfo_description,
        "type" => $row->sfo_type,
        "table" => $row->sfo_table
    );

}

function echoPlainText($val) {

    echo '<pre>';
    echo htmlspecialchars($val);
    echo '</pre>';

}

function dumpFormInfo($f, $dump) {

    if ($dump != '1') return;

    $fi = getFormInfo($f);
    echo "<b>";
    echo "-- nuBuilder cloner SQL Dump " . "<br>";
    echo "-- Version 1.09 " . "<br>";
    echo "-- Generation Time: " . date("F d, Y h:i:s A") . "<br><br>";
    echo "-- Form Description: " . $fi["description"] . "<br>";
    echo "-- Form Code: " . $fi["code"] . "<br>";
    echo "-- Form Table: " . $fi["table"] . "<br>";
    echo "-- Form Type: " . $fi["type"] . "<br><br>";

    $notes = "#cloner_notes#";
    echo hashCookieSet($notes) ? "-- Notes: " . $notes . "<br>" . "<br>" : "";
    echo "</b>";

}

function createInsertStatement($table, $columns, $row) {

    $params = array_map(function ($val) {
        return "?";
    }
    , $row);

    return "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ( " . implode(" , ", $params) . " ) ";

}

function dumpInsertStatement($table, $row, &$first) {

    $values = join(', ', array_map(function ($value) {
        return $value === null ? 'NULL' : dbQuote($value);
    }
    , $row));

    if (!isset($first)) {
        echo "<br>--<br>";
        echo "-- <b>" . $table . "</b><br>";
        echo "--<br><br>";
        $first = false;
    }

    echoPlainText("INSERT INTO $table (" . implode(', ', array_keys($row)) . ") VALUES ( " . $values . " ); ");

}

function insertRecord($table, $row, &$first) {

    if ("#cloner_dump#" == '1') {
        dumpInsertStatement($table, $row, $first);
    }
    else {
        $i = createInsertStatement($table, array_keys($row) , $row);
        nuRunQuery($i, array_values($row) , true);
    }

}

function getFormType($f) {

    $t = nuRunQuery(formSQL() , [$f]);
    $row = db_fetch_array($t);

    return $row['sfo_type'];

}

function cloneForm($f1) {

    $t = nuRunQuery(formSQL() , [$f1]);
    $row = db_fetch_array($t);

    $newPk = getPk($row['zzzzsys_form_id']);
    $row['zzzzsys_form_id'] = $newPk;
    $row['sfo_code'] .= "_clone";

    insertRecord('zzzzsys_form', $row, $first);

    return $newPk;

}

function cloneFormPHP($f1, $f2) {

    $s = "
        SELECT
            zzzzsys_php.*
        FROM
            zzzzsys_php
        LEFT JOIN zzzzsys_form ON zzzzsys_form_id = LEFT(zzzzsys_php_id, LENGTH(zzzzsys_php_id) - 3)
        WHERE
            zzzzsys_form_id = ?
	";

    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $event = eventFromPk($row['zzzzsys_php_id']);
        $row['zzzzsys_php_id'] = $f2 . $event;
        $row['sph_code'] = $f2 . $event;

        insertRecord('zzzzsys_php', $row, $first);

    }

}

function cloneFormTabs($f1, $f2) {

    $tabPks = [];
    $s = "SELECT * FROM zzzzsys_tab AS tab1 WHERE syt_zzzzsys_form_id  = ?";
    $s .= whereTabs();

    nuDebug($s);
    
    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $newPk = getPk($row['zzzzsys_tab_id']);
        addToArray($tabPks, $row['zzzzsys_tab_id'], $newPk);
        $row['zzzzsys_tab_id'] = $newPk;
        $row['syt_zzzzsys_form_id'] = $f2;

        insertRecord('zzzzsys_tab', $row, $first);

    }

    return $tabPks;

}

function cloneFormBrowse($f1, $f2) {

    $s = "SELECT * FROM zzzzsys_browse WHERE sbr_zzzzsys_form_id  = ?";
    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $newPk = getPk($row['zzzzsys_browse_id']);
        $row['zzzzsys_browse_id'] = $newPk;
        $row['sbr_zzzzsys_form_id'] = $f2;

        insertRecord('zzzzsys_browse', $row, $first);

    }

}

function whereTabs() {
    $tabs = getTabList();
    return $tabs != '' ? " AND tab1.syt_order DIV 10 IN ($tabs) " : "";
}

function getTabIds($f1, $f2) {

    $s = "    
        SELECT
            tab1.zzzzsys_tab_id AS tab1,
            tab2.zzzzsys_tab_id AS tab2
        FROM
            zzzzsys_tab AS tab1
        LEFT JOIN zzzzsys_tab AS tab2
        ON
            tab1.syt_order = tab2.syt_order
        WHERE
            tab1.syt_zzzzsys_form_id = ? AND tab2.syt_zzzzsys_form_id = ? 
    ";

    $s .= whereTabs();
    $t = nuRunQuery($s, [$f1, $f2]);

    $tabPks = [];
    while ($r = db_fetch_object($t)) {
        addToArray($tabPks, $r->tab1, $r->tab2);
    }

    return $tabPks;

}

function cloneFormObjects($f1, $f2, array & $objectPks, $tabPks) {

    $s = "SELECT * FROM zzzzsys_object WHERE sob_all_zzzzsys_form_id = ?";
    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $row['sob_all_zzzzsys_form_id'] = $f2;
        
        $newPk = getPk($row['zzzzsys_object_id']);
        addToArray($objectPks, $row['zzzzsys_object_id'], $newPk);
        
        $row['zzzzsys_object_id'] = $newPk;
        $tabId = lookupPk($tabPks, $row['sob_all_zzzzsys_tab_id']);
        $row['sob_all_zzzzsys_tab_id'] = $tabId;

        if ($tabId != "") insertRecord('zzzzsys_object', $row, $first);

    }

}

function cloneObjectsPHP($f1, $objectPks) {

    $s = "
        SELECT
           zzzzsys_php.* 
        FROM
           zzzzsys_php 
           LEFT JOIN
              zzzzsys_object 
              ON zzzzsys_object_id = LEFT(zzzzsys_php_id, LENGTH(zzzzsys_php_id) - 3) 
        WHERE
           sob_all_zzzzsys_form_id = ?
	";

    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $event = eventFromPk($row['zzzzsys_php_id']);
        $row['zzzzsys_php_id'] = lookupPk($objectPks, pkWithoutEvent($row['zzzzsys_php_id'])) . $event;
        $row['sph_code'] = lookupPk($objectPks, pkWithoutEvent($row['sph_code'])) . $event;

        insertRecord('zzzzsys_php', $row, $first);

    }

}

function cloneFormSelect($f1, $f2, array & $formSelectPks) {

    $s = "
        SELECT
           zzzzsys_select.* 
        FROM
           zzzzsys_select 
        WHERE LEFT(zzzzsys_select_id, LENGTH(zzzzsys_select_id) - 3)  = ?
	";

    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $event = eventFromPk($row['zzzzsys_select_id']);
        $newPk = $f2 . $event;
        addToArray($formSelectPks, $row['zzzzsys_select_id'], $newPk);
        $row['zzzzsys_select_id'] = $newPk;

        insertRecord('zzzzsys_select', $row, $first);

    }

}

function cloneFormSelectClause($f1, $formSelectPks) {

    $s = "
        SELECT
           zzzzsys_select_clause.* 
        FROM
           zzzzsys_select_clause 
           LEFT JOIN
              zzzzsys_select 
              ON zzzzsys_select_id = ssc_zzzzsys_select_id 
           LEFT JOIN zzzzsys_form ON LEFT(zzzzsys_select_id, LENGTH(zzzzsys_select_id) - 3) = zzzzsys_form_id
           WHERE zzzzsys_form_id  = ? 
	";

    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $row['ssc_zzzzsys_select_id'] = lookupPk($formSelectPks, $row['ssc_zzzzsys_select_id']);
        $row['zzzzsys_select_clause_id'] = getPk($row['zzzzsys_select_clause_id']);
        if ($row['ssc_zzzzsys_select_id'] != "") insertRecord('zzzzsys_select_clause', $row, $first);

    }

}

function cloneObjectsSelect($f1, $objectPks, array & $selectPks) {

    $s = "
        SELECT
           zzzzsys_select.* 
        FROM
           zzzzsys_select 
           LEFT JOIN
              zzzzsys_object 
              ON zzzzsys_object_id = LEFT(zzzzsys_select_id, LENGTH(zzzzsys_select_id) - 3) 
        WHERE
           sob_all_zzzzsys_form_id = ?
	";

    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $event = eventFromPk($row['zzzzsys_select_id']);
        $newPk = lookupPk($objectPks, pkWithoutEvent($row['zzzzsys_select_id'])) . $event;
        addToArray($selectPks, $row['zzzzsys_select_id'], $newPk);
        $row['zzzzsys_select_id'] = $newPk;

        insertRecord('zzzzsys_select', $row, $first);

    }

}

function cloneObjectsSelectClause($f1, $selectPks) {

    $s = "
        SELECT
           zzzzsys_select_clause.* 
        FROM
           zzzzsys_select_clause 
           LEFT JOIN
              zzzzsys_select 
              ON zzzzsys_select_id = ssc_zzzzsys_select_id 
           LEFT JOIN
              zzzzsys_object 
              ON zzzzsys_object_id = LEFT(zzzzsys_select_id, LENGTH(zzzzsys_select_id) - 3) 
        WHERE
           sob_all_zzzzsys_form_id = ?
	";

    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $row['ssc_zzzzsys_select_id'] = lookupPk($selectPks, $row['ssc_zzzzsys_select_id']);
        $row['zzzzsys_select_clause_id'] = getPk($row['zzzzsys_select_clause_id']);

        if ($row['ssc_zzzzsys_select_id'] != "") insertRecord('zzzzsys_select_clause', $row, $first);

    }

}

function cloneObjectsEvents($f1, $objectPks) {

    $s = "
        SELECT
            *
        FROM
            zzzzsys_event
        WHERE
            sev_zzzzsys_object_id IN (
            SELECT
                zzzzsys_object_id
            FROM
                zzzzsys_object
            WHERE
                sob_all_zzzzsys_form_id = ?
        )
    ";
    $t = nuRunQuery($s, [$f1]);

    while ($row = db_fetch_array($t)) {

        $row['zzzzsys_event_id'] = getPk($row['zzzzsys_event_id']);
        $row['sev_zzzzsys_object_id'] = lookupPk($objectPks, $row['sev_zzzzsys_object_id']);

        insertRecord('zzzzsys_event', $row, $first);

    }

}

function getOpenForm($f2) {

    $ft = getFormType($f2);
    $r = $ft == 'browseedit' ? "" : "-1";
    return "nuForm('$f2', '$r', '', '', '2');";

}

function clearHashCookies() {

    return;
    "
        function clearHashCookies() {
            nuSetProperty('cloner_f1','');
            nuSetProperty('cloner_f2','');
            nuSetProperty('cloner_tabs','');
            nuSetProperty('cloner_without_objects', '0');
            nuSetProperty('cloner_dump','0');
            nuSetProperty('cloner_new_pks','');
            nuSetProperty('cloner_open_new_form','1');
        }
        
        clearHashCookies();
    ";

}

function getSelectValues($formId, $selectId) {

    $sql = "
        SELECT
            sob_select_sql
        FROM
            `zzzzsys_object`
        WHERE
            sob_all_zzzzsys_form_id = ? AND sob_all_id = ?
    ";

    $t = nuRunQuery($sql, [$formId, $selectId]);

    $a = [];
    if (db_num_rows($t) == 1) {

        $r = db_fetch_row($t);
        if ($r != false) {
            $disS = nuReplaceHashVariables($r[0]);
            $t = nuRunQuery($disS);

            while ($row = db_fetch_row($t)) {
                $a[] = $row;
            }

            return json_encode($a);
        }

    }

    return $a;

}

function populateSelectObject($formId, $selectId) {

    $j = getSelectValues($formId, $selectId);

    return "
    	function populateSelectObject() {
    		var p = $j;
    
    		$('#$selectId').empty();
    		$('#$selectId').append('<option value=\"\"></option>');
    
    		if (p != '') {
    		    var s = nuIsSaved();
    		    
    			for (var i = 0; i < p.length; i++) {
    				$('#$selectId').append('<option value=\"' + p[i][0] + '\">' + p[i][1] + '</option>');
    			}
    			if (s) { nuHasNotBeenEdited(); }
    		}
    	}
    
    	populateSelectObject();
    ";

}

function refreshSelectObject() {

    $selectId = '#cloner_refresh_selectId#';
    $formId = '#cloner_refresh_selectFormId#';

    if (hashCookieSet($selectId)) {

        if (!hashCookieSet($formId)) {
            $formId = '#form_id#';
        }

        nuJavascriptCallback(populateSelectObject($formId, $selectId));
        return true;
    }

    return false;

}

function showError($msg) {
    nuJavascriptCallback("nuMessage(['<h2>Error</h2><br>" . $msg . "']);" . clearHashCookies());
}

function showForm($f2, $dump) {

    if ("#cloner_open_new_form#" == '0' || $dump == '1') return;
    
    nuJavascriptCallback(getOpenForm($f2) . clearHashCookies());

}

function cloneFormAll($f1, &$f2, &$tabPks) {

    if ($f2 != "") return;

    $formSelectPks = [];

    $f2 = cloneForm($f1);
    $tabPks = cloneFormTabs($f1, $f2);

    cloneFormSelect($f1, $f2, $formSelectPks);
    cloneFormSelectClause($f1, $formSelectPks);
    cloneFormBrowse($f1, $f2);
    cloneFormPHP($f1, $f2);

}

function cloneObjectsAll($f1, $f2, &$tabPks) {

    if ("#cloner_without_objects#" == '1') return;

    $objectPks = [];
    $selectPks = [];

    cloneFormObjects($f1, $f2, $objectPks, $tabPks);
    cloneObjectsPHP($f1, $objectPks);
    cloneObjectsSelect($f1, $objectPks, $selectPks);
    cloneObjectsSelectClause($f1, $selectPks);
    cloneObjectsEvents($f1, $objectPks);

}

function startCloner() {

    $dump = "#cloner_dump#";

    $newPks = "#cloner_new_pks#";
    if ($newPks == '0' && $dump != '1') {
        showError('Primary keys can only be retained in dump mode.');
        return;
    }

    if (getFormSource($f1) == false) {
        showError('The form $f1 (cloner_f1) does not exist!');
        return;
    }

    if (getFormDestination($f2) == false) {
        showError('The form $f2 (cloner_f2) does not exist!');
        return;
    }

    dumpFormInfo($f1, $dump);

    cloneFormAll($f1, $f2, $tabPks);

    cloneObjectsAll($f1, $f2, $tabPks);

    showForm($f2, $dump);

}

if (refreshSelectObject() == true) return;

startCloner();
