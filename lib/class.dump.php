<?php
/*********************************************************************************************************************\
 * LAST UPDATE
 * ============
 * March 22, 2007
 *
 *
 * AUTHOR
 * =============
 * Kwaku Otchere
 * ospinto@hotmail.com
 *
 * Thanks to Andrew Hewitt (rudebwoy@hotmail.com) for the idea and suggestion
 *
 * All the credit goes to ColdFusion's brilliant cfdump tag
 * Hope the next version of PHP can implement this or have something similar
 * I love PHP, but var_dump BLOWS!!!
 *
 * FOR DOCUMENTATION AND MORE EXAMPLES: VISIT http://dbug.ospinto.com
 *
 *
 * PURPOSE
 * =============
 * Dumps/Displays the contents of a variable in a colored tabular format
 * Based on the idea, javascript and css code of Macromedia's ColdFusion cfdump tag
 * A much better presentation of a variable's contents than PHP's var_dump and print_r functions
 *
 *
 * USAGE
 * =============
 * new dBug ( variable [,forceType] );
 * example:
 * new dBug ( $myVariable );
 *
 *
 * if the optional "forceType" string is given, the variable supplied to the
 * function is forced to have that forceType type.
 * example: new dBug( $myVariable , "array" );
 * will force $myVariable to be treated and dumped as an array type,
 * even though it might originally have been a string type, etc.
 *
 * NOTE!
 * ==============
 * forceType is REQUIRED for dumping an xml string or xml file
 * new dBug ( $strXml, "xml" );
 *
\*********************************************************************************************************************/

if (!class_exists('dBug')) {

class dBug {

	var $xmlDepth=array();
	var $xmlCData;
	var $xmlSData;
	var $xmlDData;
	var $xmlCount=0;
	var $stopCount=0;
	var $xmlAttrib;
	var $xmlName;
	var $arrType=array("array","object","resource","boolean","NULL");
	var $bInitialized = false;
	var $bCollapsed = false;
	var $arrHistory = array();

	//constructor
	function dBug($var,$forceType="",$bCollapsed=false) {
		//include js and css scripts
		if(!defined('BDBUGINIT')) {
			define("BDBUGINIT", TRUE);
			$this->initJSandCSS();
		}
		$arrAccept=array("array","object","xml"); //array of variable types that can be "forced"
		$this->bCollapsed = $bCollapsed;
		if(in_array($forceType,$arrAccept))
			$this->{"varIs".ucfirst($forceType)}($var);
		else
			$this->checkType($var);
	}

	//get variable name
	function getVariableName() {
		$arrBacktrace = debug_backtrace();

		//possible 'included' functions
		$arrInclude = array("include","include_once","require","require_once");

		//check for any included/required files. if found, get array of the last included file (they contain the right line numbers)
		for($i=count($arrBacktrace)-1; $i>=0; $i--) {
			$arrCurrent = $arrBacktrace[$i];
			if(array_key_exists("function", $arrCurrent) &&
				(in_array($arrCurrent["function"], $arrInclude) || (0 != strcasecmp($arrCurrent["function"], "dbug"))))
				continue;

			$arrFile = $arrCurrent;

			break;
		}

		if(isset($arrFile)) {
			$arrLines = file($arrFile["file"]);
			$code = $arrLines[($arrFile["line"]-1)];

			//find call to dBug class
			preg_match('/\bnew dBug\s*\(\s*(.+)\s*\);/i', $code, $arrMatches);

			return $arrMatches[1];
		}
		return "";
	}

	//create the main table header
	function makeTableHeader($type,$header,$colspan=2) {
		if(!$this->bInitialized) {
			$header = $this->getVariableName() . " (" . $header . ")";
			$this->bInitialized = true;
		}
		$str_i = ($this->bCollapsed) ? "style=\"font-style:italic\" " : "";

		echo "<table cellspacing=2 cellpadding=3 class=\"dBug_".$type."\">
				<tr>
					<td ".$str_i."class=\"dBug_".$type."Header\" colspan=".$colspan." onClick='dBug_toggleTable(this)'>".$header."</td>
				</tr>";
	}

	//create the table row header
	function makeTDHeader($type,$header) {
		$str_d = ($this->bCollapsed) ? " style=\"display:none\"" : "";
		echo "<tr".$str_d.">
				<td valign=\"top\" onClick='dBug_toggleRow(this)' class=\"dBug_".$type."Key\">".$header."</td>
				<td>";
	}

	//close table row
	function closeTDRow() {
		return "</td></tr>\n";
	}

	//error
	function  error($type) {
		$error="Error: Variable cannot be a";
		// this just checks if the type starts with a vowel or "x" and displays either "a" or "an"
		if(in_array(substr($type,0,1),array("a","e","i","o","u","x")))
			$error.="n";
		return ($error." ".$type." type");
	}

	//check variable type
	function checkType($var) {


// echo gettype($var) . '<p>';


		switch(gettype($var)) {
			case "resource":
				$this->varIsResource($var);
				break;
			case "object":
				$this->varIsObject($var);
				break;
			case "array":
				$this->varIsArray($var);
				break;
			case "NULL":
				$this->varIsNULL();
				break;
			case "boolean":
				$this->varIsBoolean($var);
				break;
			default:
				$var=($var=="") ? "" : $var;
				echo "<table cellspacing=0><tr>\n<td>".$var."</td>\n</tr>\n</table>\n";
				break;
		}
	}

	//if variable is a NULL type
	function varIsNULL() {
		echo "NULL";
	}

	//if variable is a boolean type
	function varIsBoolean($var) {
		$var=($var==1) ? "TRUE" : "FALSE";
		echo $var;
	}

	//if variable is an array type
	function varIsArray($var) {
		$var_ser = serialize($var);
		array_push($this->arrHistory, $var_ser);

		$this->makeTableHeader("array","array");
		if(is_array($var)) {
			foreach($var as $key=>$value) {
				$this->makeTDHeader("array",$key);

				//check for recursion
				if(is_array($value)) {
					$var_ser = serialize($value);
					if(in_array($var_ser, $this->arrHistory, TRUE)){
						$value = "*RECURSION*";

						$this->stopCount++;

						if($this->stopCount > 10){
							die;
						}


					}
				}

				if(in_array(gettype($value),$this->arrType))
					$this->checkType($value);
				else {
					$value=(trim($value)=="") ? "" : $value;
					echo $value;
				}
				echo $this->closeTDRow();
			}
		}
		else echo "<tr><td>".$this->error("array").$this->closeTDRow();
		array_pop($this->arrHistory);
		echo "</table>";
	}

	//if variable is an object type
	function varIsObject($var) {
		$var_ser = serialize($var);
		array_push($this->arrHistory, $var_ser);
		$this->makeTableHeader("object","object");

		if(is_object($var)) {
			$arrObjVars=get_object_vars($var);
			foreach($arrObjVars as $key=>$value) {
if($key != 'db'){




				$value=(!is_object($value) && !is_array($value) && trim($value)=="") ? "" : $value;

				// $this->makeTDHeader("object",$key);



		$skip = false;
		ob_start();
				//check for recursion
				if(is_object($value)||is_array($value)) {
					$var_ser = serialize($value);
					if(in_array($var_ser, $this->arrHistory, TRUE)) {
						$value = (is_object($value)) ? "*RECURSION* -> $".get_class($value) : "*RECURSION*";

		$skip = true;
						if($value == '*RECURSION*'){
							$this->stopCount++;

							if($this->stopCount > 10){
								// die;
							}
						}


					}
				}
				if(in_array(gettype($value),$this->arrType)){
					$this->checkType($value);
				} else {
					echo $value;
				}



		$_dump_result = ob_get_contents();
		ob_end_clean();

				$this->makeTDHeader("object",$key);
				if(!$skip) echo $_dump_result;
				$_dump_result = '';
				echo $this->closeTDRow();

}

			}
			$arrObjMethods=get_class_methods(get_class($var));
			$ClassMethodList = '';
			foreach($arrObjMethods as $key=>$value) {
				$ClassMethodList .= '' . $value . '<br>';
			}

			////////// $this->makeTDHeader("object",'_CLASS_METHODS_');

			$str_d = ' style="display:none"';
			
			if(!$ClassMethodList == ''){
					echo "<tr>
					<td valign=\"top\" onClick='dBug_toggleRow(this)' class=\"dBug_objectKey\">_CLASS_METHODS_</td>
					";
					echo "<td".$str_d.">";
					echo $ClassMethodList . $this->closeTDRow();
			}

					// target.style.display = (switchToState=='open') ? '' : 'none';





				// print_r ($ClassMethodList);
				// die;


		}
		else echo "<tr><td>".$this->error("object").$this->closeTDRow();
		array_pop($this->arrHistory);
		echo "</table>";
	}

	//if variable is a resource type
	function varIsResource($var) {
		$this->makeTableHeader("resourceC","resource",1);
		echo "<tr>\n<td>\n";
		switch(get_resource($var)) {
			case "fbsql result":
			case "mssql result":
			case "msql query":
			case "pgsql result":
			case "sybase-db result":
			case "sybase-ct result":
			case "mysql result":
				$db=current(explode(" ",get_resource($var)));
				$this->varIsDBResource($var,$db);
				break;
			case "gd":
				$this->varIsGDResource($var);
				break;
			case "xml":
				$this->varIsXmlResource($var);
				break;
			default:
				echo get_resource($var).$this->closeTDRow();
				break;
		}
		echo $this->closeTDRow()."</table>\n";
	}

	//if variable is a database resource type
	function varIsDBResource($var,$db="mysql") {
		if($db == "pgsql")
			$db = "pg";
		if($db == "sybase-db" || $db == "sybase-ct")
			$db = "sybase";
		$arrFields = array("name","type","flags");
		$numrows=call_user_func($db."_num_rows",$var);
		$numfields=call_user_func($db."_num_fields",$var);
		$this->makeTableHeader("resource",$db." result",$numfields+1);
		echo "<tr><td class=\"dBug_resourceKey\">&nbsp;</td>";
		for($i=0;$i<$numfields;$i++) {
			$field_header = "";
			for($j=0; $j<count($arrFields); $j++) {
				$db_func = $db."_field_".$arrFields[$j];
				if(function_exists($db_func)) {
					$fheader = call_user_func($db_func, $var, $i). " ";
					if($j==0)
						$field_name = $fheader;
					else
						$field_header .= $fheader;
				}
			}
			$field[$i]=call_user_func($db."_fetch_field",$var,$i);
			echo "<td class=\"dBug_resourceKey\" title=\"".$field_header."\">".$field_name."</td>";
		}
		echo "</tr>";
		for($i=0;$i<$numrows;$i++) {
			$row=call_user_func($db."_fetch_array",$var,constant(strtoupper($db)."_ASSOC"));
			echo "<tr>\n";
			echo "<td class=\"dBug_resourceKey\">".($i+1)."</td>";
			for($k=0;$k<$numfields;$k++) {
				$tempField=$field[$k]->name;
				$fieldrow=$row[($field[$k]->name)];
				$fieldrow=($fieldrow=="") ? "" : $fieldrow;
				echo "<td>".$fieldrow."</td>\n";
			}
			echo "</tr>\n";
		}
		echo "</table>";
		if($numrows>0)
			call_user_func($db."_data_seek",$var,0);
	}

	//if variable is an image/gd resource type
	function varIsGDResource($var) {
		$this->makeTableHeader("resource","gd",2);
		$this->makeTDHeader("resource","Width");
		echo imagesx($var).$this->closeTDRow();
		$this->makeTDHeader("resource","Height");
		echo imagesy($var).$this->closeTDRow();
		$this->makeTDHeader("resource","Colors");
		echo imagecolorstotal($var).$this->closeTDRow();
		echo "</table>";
	}

	//if variable is an xml type
	function varIsXml($var) {
		$this->varIsXmlResource($var);
	}

	//if variable is an xml resource type
	function varIsXmlResource($var) {
		$xml_parser=xml_parser_create();
		xml_parser_set_option($xml_parser,XML_OPTION_CASE_FOLDING,0);
		xml_set_element_handler($xml_parser,array(&$this,"xmlStartElement"),array(&$this,"xmlEndElement"));
		xml_set_character_data_handler($xml_parser,array(&$this,"xmlCharacterData"));
		xml_set_default_handler($xml_parser,array(&$this,"xmlDefaultHandler"));

		$this->makeTableHeader("xml","xml document",2);
		$this->makeTDHeader("xml","xmlRoot");

		//attempt to open xml file
		$bFile=(!($fp=@fopen($var,"r"))) ? false : true;

		//read xml file
		if($bFile) {
			while($data=str_replace("\n","",fread($fp,4096)))
				$this->xmlParse($xml_parser,$data,feof($fp));
		}
		//if xml is not a file, attempt to read it as a string
		else {
			if(!is_string($var)) {
				echo $this->error("xml").$this->closeTDRow()."</table>\n";
				return;
			}
			$data=$var;
			$this->xmlParse($xml_parser,$data,1);
		}

		echo $this->closeTDRow()."</table>\n";

	}

	//parse xml
	function xmlParse($xml_parser,$data,$bFinal) {
		if (!xml_parse($xml_parser,$data,$bFinal)) {
				   die(sprintf("XML error: %s at line %d\n",
							   xml_error_string(xml_get_error_code($xml_parser)),
							   xml_get_current_line_number($xml_parser)));
		}
	}

	//xml: inititiated when a start tag is encountered
	function xmlStartElement($parser,$name,$attribs) {
		$this->xmlAttrib[$this->xmlCount]=$attribs;
		$this->xmlName[$this->xmlCount]=$name;
		$this->xmlSData[$this->xmlCount]='$this->makeTableHeader("xml","xml element",2);';
		$this->xmlSData[$this->xmlCount].='$this->makeTDHeader("xml","xmlName");';
		$this->xmlSData[$this->xmlCount].='echo "<strong>'.$this->xmlName[$this->xmlCount].'</strong>".$this->closeTDRow();';
		$this->xmlSData[$this->xmlCount].='$this->makeTDHeader("xml","xmlAttributes");';
		if(count($attribs)>0)
			$this->xmlSData[$this->xmlCount].='$this->varIsArray($this->xmlAttrib['.$this->xmlCount.']);';
		else
			$this->xmlSData[$this->xmlCount].='echo "&nbsp;";';
		$this->xmlSData[$this->xmlCount].='echo $this->closeTDRow();';
		$this->xmlCount++;
	}

	//xml: initiated when an end tag is encountered
	function xmlEndElement($parser,$name) {
		for($i=0;$i<$this->xmlCount;$i++) {
			eval($this->xmlSData[$i]);
			$this->makeTDHeader("xml","xmlText");
			echo (!empty($this->xmlCData[$i])) ? $this->xmlCData[$i] : "&nbsp;";
			echo $this->closeTDRow();
			$this->makeTDHeader("xml","xmlComment");
			echo (!empty($this->xmlDData[$i])) ? $this->xmlDData[$i] : "&nbsp;";
			echo $this->closeTDRow();
			$this->makeTDHeader("xml","xmlChildren");
			unset($this->xmlCData[$i],$this->xmlDData[$i]);
		}
		echo $this->closeTDRow();
		echo "</table>";
		$this->xmlCount=0;
	}

	//xml: initiated when text between tags is encountered
	function xmlCharacterData($parser,$data) {
		$count=$this->xmlCount-1;
		if(!empty($this->xmlCData[$count]))
			$this->xmlCData[$count].=$data;
		else
			$this->xmlCData[$count]=$data;
	}

	//xml: initiated when a comment or other miscellaneous texts is encountered
	function xmlDefaultHandler($parser,$data) {
		//strip '<!--' and '-->' off comments
		$data=str_replace(array("&lt;!--","--&gt;"),"",htmlspecialchars($data));
		$count=$this->xmlCount-1;
		if(!empty($this->xmlDData[$count]))
			$this->xmlDData[$count].=$data;
		else
			$this->xmlDData[$count]=$data;
	}

	function initJSandCSS() {
		echo <<<SCRIPTS
			<script language="JavaScript">
			/* code modified from ColdFusion's cfdump code */
				function dBug_toggleRow(source) {
					var target = (document.all) ? source.parentElement.cells[1] : source.parentNode.lastChild;
					dBug_toggleTarget(target,dBug_toggleSource(source));
				}

				function dBug_toggleSource(source) {
					if (source.style.fontStyle=='italic') {
						source.style.fontStyle='normal';
						source.title='click to collapse';
						return 'open';
					} else {
						source.style.fontStyle='italic';
						source.title='click to expand';
						return 'closed';
					}
				}

				function dBug_toggleTarget(target,switchToState) {
					target.style.display = (switchToState=='open') ? '' : 'none';
				}

				function dBug_toggleTable(source) {
					var switchToState=dBug_toggleSource(source);
					if(document.all) {
						var table=source.parentElement.parentElement;
						for(var i=1;i<table.rows.length;i++) {
							target=table.rows[i];
							dBug_toggleTarget(target,switchToState);
						}
					}
					else {
						var table=source.parentNode.parentNode;
						for (var i=1;i<table.childNodes.length;i++) {
							target=table.childNodes[i];
							if(target.style) {
								dBug_toggleTarget(target,switchToState);
							}
						}
					}
				}
			</script>

			<style type="text/css">
				table.dBug_array,table.dBug_object,table.dBug_resource,table.dBug_resourceC,table.dBug_xml {
					font-family:Verdana, Arial, Helvetica, sans-serif; color:#000000; font-size:12px;
				}

				.dBug_arrayHeader,
				.dBug_objectHeader,
				.dBug_resourceHeader,
				.dBug_resourceCHeader,
				.dBug_xmlHeader
					{ font-weight:bold; color:#FFFFFF; cursor:pointer; }

				.dBug_arrayKey,
				.dBug_objectKey,
				.dBug_xmlKey
					{ cursor:pointer; }

				/* array */
				table.dBug_array { background-color:#006600; }
				table.dBug_array td { background-color:#FFFFFF; }
				table.dBug_array td.dBug_arrayHeader { background-color:#009900; }
				table.dBug_array td.dBug_arrayKey { background-color:#CCFFCC; }

				/* object */
				table.dBug_object { background-color:#0000CC; }
				table.dBug_object td { background-color:#FFFFFF; }
				table.dBug_object td.dBug_objectHeader { background-color:#4444CC; }
				table.dBug_object td.dBug_objectKey { background-color:#CCDDFF; }



				table.dBug_objectM { background-color:#AA66AA; }
				table.dBug_objectM td { background-color:#AA66AA; }
				table.dBug_objectM td.dBug_objectMHeader { background-color:#AA66AA; }
				table.dBug_objectM td.dBug_objectMKey { background-color:#AA66AA; }





				/* resource */
				table.dBug_resourceC { background-color:#884488; }
				table.dBug_resourceC td { background-color:#FFFFFF; }
				table.dBug_resourceC td.dBug_resourceCHeader { background-color:#AA66AA; }
				table.dBug_resourceC td.dBug_resourceCKey { background-color:#FFDDFF; }

				/* resource */
				table.dBug_resource { background-color:#884488; }
				table.dBug_resource td { background-color:#FFFFFF; }
				table.dBug_resource td.dBug_resourceHeader { background-color:#AA66AA; }
				table.dBug_resource td.dBug_resourceKey { background-color:#FFDDFF; }

				/* xml */
				table.dBug_xml { background-color:#888888; }
				table.dBug_xml td { background-color:#FFFFFF; }
				table.dBug_xml td.dBug_xmlHeader { background-color:#AAAAAA; }
				table.dBug_xml td.dBug_xmlKey { background-color:#DDDDDD; }
			</style>
SCRIPTS;
	}

}





function get_dump($v='',$label='') {
	ob_start();

		echo '<div>';
		if($label != ''){
			echo $label . '</br>';
		}
		dump($v);
		echo '</div>';

		$_dump_result = ob_get_contents();
	ob_end_clean();
	return $_dump_result;
}

function abortm() {

	// multi abort

	$args = func_get_args();

	$num  = func_num_args();

	echo '<hr>';
	for ($i = 1; $i < $num; $i++) {
		$args[0] = array_merge((array) $args[0], (array) $args[$i]);

		// dump($args[0]);

		$value = func_get_arg($i);
		echo '<table><tr>';
			echo '<td><b>'.$i . ')</b></td>';
			echo '<td>';

			dump($value);
			echo '</td>';
		echo '</tr></table>';

	}

		echo '<hr>';

	// foreach(func_get_args() as $a) dump($a);

	// $args = func_get_args();

	// dump($args);

	// new dBug($v);
	die;
}

function abort($v='') {


	new dBug($v);
	die;
}


function youfoo()
{
   $numargs = func_num_args();
   echo "Number of arguments: $numargs<br />\n";
   if ($numargs >= 2) {
       echo "Second argument is: " . dump(func_get_arg(1)) . "<br />\n";
   }
   $arg_list = func_get_args();
   for ($i = 0; $i < $numargs; $i++) {
       echo "Argument $i is: " . dump($arg_list[$i]) . "<br />\n";
   }
}



function dump($v='',$collapse=false) {
	// echo '<h2>'.$label.'</h2>';
	new dBug($v,'',$collapse);
}




}
