<?php

/*
 * JSON Class CRUD PHP
 * Url 		: https://github.com/ryzaer/php-json-crud
 * Author	: Riza TTNT
 * Desc		: PHP Class for easy creating json & crud
 * Date Created	: 13th, July 2020
 * Last Updated : 19th, July 2020
 * Support	: php > v5.4
 * License 	: MIT
 */ 

class createJSON {   
    
    private static $instance;
    private $maxData = 10000; /* 0/null for unlimited query */    
    public $rmode    = false;
    public $initID;
    public $nextID;    
    public $table;    

    public static function params($string_json = null){
	if (!isset(self::$instance)){
		self::$instance = new createJSON($string_json);	
	}		
	return self::$instance;
    }
    
    public function __construct($json=null){          
        if($json)
            if(is_array($json)){
                $json = [$json];                
            }else{                
                $json = json_decode($json, true);
            } 
            $this->build_virtual_table($json);        
    }

    private function build_virtual_table($arrJSON){
        
        $num = 1;
        $total = 0;
        $table = [];
        $noval = true;

        if(isset($arrJSON[0]) && is_array($arrJSON[0])){

            foreach($arrJSON as $j => $on){ 
                foreach($on as $o => $n){
                    if($n) $noval = false;
                }
                $table[] = $this->serialize($on,$j); 
            } 
            
            $bigID = [];
            foreach ($table as $key => $value) {
                $bigID[] = $value[$this->initID]; 
            }

            asort($bigID);
            $lastid = array_values($bigID);
            $total  = count($lastid);
            $fstrow = $lastid[0];
            $lstrow = $lastid[abs($total - 1)];
            $num    = ((!$noval)? (($fstrow > $lstrow)? $fstrow : $lstrow) : 0 ) + $num ; 
        }
       
        $this->nextID    = $num;
        $this->count     = (!$noval)? $total : 0;
        $this->query     = (!$noval)? $table : [];
        $this->last_row  = (!$noval)? $table[abs($total-1)] : [];
        $this->encode    = (!$noval)? json_encode($table) : null;        
        $this->last_enc  = (!$noval)? json_encode($table[abs($total-1)]) : null ;
    } 

    private function passing_rows($param){
        $pass = [];
        $init = false;
        if($this->table){
            foreach($this->table as $key => $val){
                if(isset($param[$val]) && $param[$val] ){
                    $pass[$val] = $param[$val];
                    $init = true;
                }else{
                    $pass[$val] = '';
                }
            }
        }
        return [$init,$pass];
    }

    private function serialize($arg,$id){
        $args = [];
        $no=0;
        $bigID=[];
        foreach ($arg as $o => $n) {
            /* initialization for 'id' chars */
            if($no == 0 && !preg_match('/id/i', $o)){                              
                $args['id'] = ($id+1);
            }
            $args[$o] =  $n;
            $no++;
        }

        $strc = array_keys($args);
        $this->initID = $strc[0];

        $cons = [];        
        foreach($strc as $d => $s){            
            if($d>0) $cons[] = $s;            
        }        
        $this->table = $cons;

        return $args;
    }

    public function rows($arrs){
        if(is_array($arrs) && isset($arrs[0])){
            $rest = null;
            foreach ($arrs as $key => $val) {
                if(!is_numeric($val)) $rest[$val] = '';
            }
            $this->build_virtual_table([$rest]);
	    $this->rmode = true;
        }
    }

    public function sortBy($sort_by){
        /* example [key => asc/desc] */
        $newarrs = [];
        $sort = [];
        if(!empty($sort_by) && !empty($this->query)){            
            foreach($sort_by as $b => $y){
                if(!is_numeric($y)){
                    foreach($this->query as $g => $var){
                        foreach($var as $v => $r){
                            /* but then always sort by ID */
                            if($b == $v) $sort[$var[$this->initID]] = $r;                            
                        }
                    }
                    if($y == 'asc')     { asort($sort);  }        
                    if($y == 'desc')    { arsort($sort); }
                }
            }

            foreach($sort as $id => $val){
                foreach($this->query as $g => $var){
                    if($var[$this->initID] == $id) $newarrs[] = $var;                    
                }
            }
        }

        $this->query = $newarrs;
    }

    public function insert($data = []){

	if($this->rmode){
            $nrows =[];
            for($n=0;$n<count($this->table);$n++){
                $nrows[$this->table[$n]] = (isset($data[$n])? $data[$n] : '');
            }
            $data = $nrows;
        }

        if(!$this->maxData){
            $maxdata = false;
        }else{
            $maxdata = true;
            if(is_numeric($this->maxData) > 0 && $this->count < $this->maxData)
                $maxdata = false;
        }
        
        $query  = [];
        $check  = $this->passing_rows($data);
        $insert = false;
        if($check[0]){ 

            foreach($this->query as $que => $ry){                
                $query[] = $ry;
            }

            $indata[$this->initID] = $this->nextID;
            foreach($check[1] as $da => $ta){
                $indata[$da] = $ta;
            } 

            $query[] = $indata;
        }

        if(!empty($query) && !$maxdata){
            /* refresh query */
            $this->build_virtual_table($query);
            $insert = true;
        }
            
        return  $insert; /* true,false */
            
    }

    public function update($param, $id){
        
        $query  = [];
        $inrow  = [];
        $update = false;
        if(!empty($this->query)){
            foreach($this->query as $que => $str){
                if(isset($str[$this->initID]) && $str[$this->initID] == $id){
                    foreach ($str as $key => $val) {
                        if(isset($param[$key])){
                            /* success goes here... */
                            $inrow[$key] = $param[$key];
                            $update = true;
                        }else{
                            $inrow[$key] = $val;
                        }
                    }
                    $query[] = $inrow;
                }else{
                    $query[] = $str;   
                }
            } 

            /* refresh query */
            $this->build_virtual_table($query);    
        }    
        
        return $update; /* true, false */
    }
    
    public function select($param, $excact= false ){
        /* $excact = false is 'LIKE' in mysql */
        $query = [];
        if(!empty($this->query)){
            foreach($this->query as $que => $str){
                foreach($param as $key => $value) {
                    if($excact){
                        if(isset($str[$key]) && trim($str[$key]) == trim($value))
                            $query[] = $str;
                    }else{
                        if(isset($str[$key]) && preg_match('/'.$value.'/i', $str[$key]))
                            $query[] = $str;                    
                    }
                }  
            }  
        }   

        /* dont run refresh query cuz for initial purpose only */ 
        return $query;
    }

    public function delete($id){
        if(!empty($this->query)){
            $query = [];
            foreach($this->query as $que => $row){  
                if(isset($row[$this->initID])){
                    if($row[$this->initID] !== abs($id)) $query[] = $row;
                }
            }

            /* refresh query */
            $this->build_virtual_table($query);
        }
    }

    public function save($name=null){
        $name = ($name)? $name : 'output.json';
        file_put_contents($name, $this->text());
    }

    public function text(){
        return json_encode($this->query,JSON_PRETTY_PRINT);
    }
}
