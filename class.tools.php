<?PHP
/* ******************************************************************************************************************** / 
CLASS NAME: Tools
DESCRIPTION: This class has tools for forms
***********************************************************************************************************************/
class Tools extends Database {
    
    public function __construct() {
        parent::__construct(); 
    }
        
	/* ******************************************************************************************************************** / 
	METHOD: getListData
	DESCRIPTION: This method get the list data to populate a data grid control
	PARAMETERS:  $page - the current page to show
                 $perpage - the number of rows to display per page
                 $query - the sql query to get the list from the database
                 $types - the bind types for the query
                 $params - the bind parameters for the query
                 $searcharray - an associative array of search values to add to the query
                              - value - the search input values to use in the query
                              - condition - the where condition for the query to use when searching
                              - types - the bind types for the search value
                              - params - the bind parameters for the search value
    RETURNS: an array of list data
	***********************************************************************************************************************/
    public function getListData($page, $perpage, $query, $types='', $params=array(), $searcharray=array()) {
        $page = $page==0 ? 1 : $page;
        $perpage = $perpage==0 ? 25 : $perpage;
        $totalpages = 0;

        //SETUP QUERY VALUES IF A SEARCH TERM IS PROVIDED
        if (empty($searcharray['value'])===false) {
            $searchcondition = $searcharray['condition'];
            $types .= $searcharray['types'];
            $params = array_merge($params, $searcharray['params']);
        }
        
        //SET THE FIRST AND LAST ITEMS TO SHOW IN THE RESULTS
        $start = $page==1 ? '0' : ($page-1)*$perpage;

        //QUERY THE DATABASE
        $results = $this->query($query.' '.$searchcondition, $types, $params, false);
        
        if (($numrows = $this->numRows($results, false))>0) {
            //CALCULATE THE TOTAL NUMBER OF PAGES FOR THIS SEARCH
            $totalpages = $numrows<=$perpage ? 1 : ceil($numrows/$perpage);
            
            //CALCULATE THE NUMBER OF RECORDS TO LOOP THROUGH
            if ($totalpages==$page) {
                $end = $numrows-$start;
            } else {
                $end = $perpage;
            }
            
            //JUMP TO THE FIRST RECORD FOR THE PAGE SELECTED
            $this->goToRow($results, $start);

            //LOOP THROUGH THE RECORDS FOR THIS PAGE
            $rowarray = array();
            
            for ($i=0; $i<$end; $i++) {
                $row = $this->getRow($results, false);

                //ADD THE JSON VALUES FOR THIS RECORD
                foreach ($row as $k=>$v) {
                    $rowarray[$i][$k] = trim($v);
                }
                
                //GENERAL FORMATTING
                if (empty($rowarray[$i]['date1'])===false) {
                    $rowarray[$i]['date1'] = date('m/d/Y g:i A', strtotime($rowarray[$i]['date1']));
                }
                
                if (empty($rowarray[$i]['date2'])===false) {
                    $rowarray[$i]['date2'] = date('m/d/Y', strtotime($rowarray[$i]['date2']));
                }
            }
        }
        
        return array('count'=>$numrows, 
                     'page'=>$page, 
                     'perpage'=>$perpage, 
                     'totalpages'=>$totalpages, 
                     'listdata'=>$rowarray                     
                     );
    }
    
	/* ******************************************************************************************************************** / 
	METHOD: getRecordDetails
	DESCRIPTION: This method get details of a specific database record
	PARAMETERS:  $query - the sql query to get the list from the database
                 $id - the unique id value used to pull a specific record
    RETURNS: an array of details
	***********************************************************************************************************************/
    public function getRecordDetails($query, $id) {
        //QUERY THE DATABASE
        $results = $this->query($query, 's', array($id), false);
        
        //IF THIS IS A VALID API USER
        if (($numrows = $this->numRows($results, false))>0) {
            $row = $this->getRow($results, false);
            
            //ADD THE JSON VALUES FOR THIS RECORD
            foreach ($row as $k=>$v) {
                $rowarray[$k] = trim($v);
            }
        }
        
        return $rowarray;
    }
    
	/* ******************************************************************************************************************** / 
	METHOD: saveRecord
	DESCRIPTION: This method get details of a specific database record
	PARAMETERS:  $query - the sql query process
                 $types - the string of value types being bound to the query
                 $params - the array of values to bind to the query
                 $recordid - the record id of the record to update
    RETURNS: an array of result from save 
	***********************************************************************************************************************/
    public function saveRecord($query, $types, $params, $recordid=0) {
        //IF A RECORDID IS PROVIDED
        if ($recordid>0) {
            $rowseffected = $this->query($query, $types, $params, false);
            
            //UPDATE THE RECORD
            if (is_numeric($rowseffected)) {
                $results = array('response'=>'success', 'recordid'=>$recordid);
            } else {
                $results = array('response'=>$rowseffected);
            }
        //ELSE - INSERT A NEW RECORD
        } else {
            if (($newrecordid = $this->query($query, $types, $params, false))>0) {
                $results = array('response'=>'success', 'recordid'=>$newrecordid);
            } else {
                $results = array('response'=>'error');
            }
        }
        return $results;
    }

    /* ******************************************************************************************************************** / 
    METHOD: getEnumList
    DESCRIPTION: This method is used to get the available enum values from a specific field and table
    PARAMETERS: $table - the name of the database table to use
                $field - the name of the field to get the enum list from
    RETURNS: an array of enum values 
    ***********************************************************************************************************************/
    public function getEnumList($table, $field) {        
        $enums = array();
        $results = $this->query('SELECT COLUMN_TYPE AS enumvalues FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=? AND COLUMN_NAME=?', 
                                'ss', array($table, $field), false);
        if (($numrows = $this->numRows($results, false))>0) {
            $row = $this->getRow($results, false);
            $values = str_replace(array('enum(',')',"'"), '', $row['enumvalues']);
            $enums = explode(',', $values);
        }
        
        return $enums;        
    }    
}