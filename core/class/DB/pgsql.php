<?php

class PHPWS_SQL {

  function export(&$info){
    switch ($info['type']){

    case "int8":
    case "int4":
      $setting = "INT";
      $info['flags'] = preg_replace("/unique primary/", "PRIMARY KEY", $info['flags']);
      break;

    case "int2":
      $setting = "SMALLINT";
      break;

    case "text":
    case "blob":
      $setting = "TEXT";
      $info['flags'] = NULL;
      break;
    
    case "bpchar":
      $setting = "CHAR(255)";

      if (empty($info['flags']))
	$info['flags'] = "NULL";
      break;
    
    case "date":
      $setting = "DATE";
      break;
    
    case "real":
      $setting = "FLOAT";
      break;
    
    case "timestamp":
      $setting = "TIMESTAMP";
      $info['flags'] = NULL;
      break;
    }
    return $setting;
  }

  function getLimit($limit){
    $sql[] = "LIMIT";

    if (isset($limit['offset'])) {
      $sql[] = $limit['offset'];
      $sql[] = "OFFSET";
    }

    $sql[] = $limit['total'];
    
    return implode(" ", $sql);
  }


}

?>
