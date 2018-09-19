<?php

	Class Utils
	{
		public function alast($array)
		{
			if (!is_array($array))
				return $array;
			
			return alast(reset($array));
		}


		public function asearch($array, $key, $avoid = '', $last = true){

			$res = $this->search_nested_arrays($array, $key, $avoid);
	
			if ($last)
				return $this->alast($res);
			else
				return reset($res);

		}

		public function search_nested_arrays($array, $key, $avoid = '')
		{
			try{
				if (is_object($array))
			        $array = (array)$array;
					
				if (!is_array($array))
					return null;
			    
			    $result = array();
			    foreach ($array as $k => $value)
				{ 
			        if (is_array($value) || is_object($value))
					{
						if (is_array($avoid) && !empty($avoid))
						{
							$skip = false;

							foreach ($avoid as $a)
							{
								if (!empty($a) && $k === $a)
								{
									$skip = true;
									break;
								}
							}
							
							if ($skip)
								continue;
						}
						else
						{
							if (!empty($avoid) && $k === $avoid)
								continue;
						}
			            $r = $this->search_nested_arrays($value, $key, $avoid);
			            if (!is_null($r))
							array_push($result,$r);
			        }
			    }
			    
			    if (array_key_exists($key, $array))
			        array_push($result,$array[$key]);
			    
			    if (count($result) > 0)
				{
			        $result_plain = array();
			        foreach ($result as $k => $value)
					{ 
			            if(is_array($value))
			                $result_plain = array_merge($result_plain,$value);
			            else
			                array_push($result_plain,$value);
			        }
			        return $result_plain;
			    }
			    return NULL;
			}catch(Exception $ex) {
				return NULL;
				// LogForJob("EXCEPTION IN updateItemAndCompetitors: ".$ex->getMessage());
			}
		    
		}

		public function XMLtoArray($XML)
		{
		    $xml_array = null;
		    $xml_parser = xml_parser_create();
		    xml_parse_into_struct($xml_parser, $XML, $vals);
		    xml_parser_free($xml_parser);
		    // wyznaczamy tablice z powtarzajacymi sie tagami na tym samym poziomie
		    $_tmp='';
		    foreach ($vals as $xml_elem) {
		        $x_tag=$xml_elem['tag'];
		        $x_level=$xml_elem['level'];
		        $x_type=$xml_elem['type'];
		        if ($x_level!=1 && $x_type == 'close') {
		            if (isset($multi_key[$x_tag][$x_level]))
		                $multi_key[$x_tag][$x_level]=1;
		            else
		                $multi_key[$x_tag][$x_level]=0;
		        }
		        if ($x_level!=1 && $x_type == 'complete') {
		            if ($_tmp==$x_tag)
		                $multi_key[$x_tag][$x_level]=1;
		            $_tmp=$x_tag;
		        }
		    }
		    // jedziemy po tablicy
		    foreach ($vals as $xml_elem) {
		        $x_tag=$xml_elem['tag'];
		        $x_level=$xml_elem['level'];
		        $x_type=$xml_elem['type'];
		        if ($x_type == 'open')
		            $level[$x_level] = $x_tag;
		        $start_level = 1;
		        $php_stmt = '$xml_array';
		        if ($x_type=='close' && $x_level!=1)
		            $multi_key[$x_tag][$x_level]++;
		        while ($start_level < $x_level) {
		            $php_stmt .= '[$level['.$start_level.']]';
		            if (isset($multi_key[$level[$start_level]][$start_level]) && $multi_key[$level[$start_level]][$start_level])
		                $php_stmt .= '['.($multi_key[$level[$start_level]][$start_level]-1).']';
		            $start_level++;
		        }
		        $add='';
		        if (isset($multi_key[$x_tag][$x_level]) && $multi_key[$x_tag][$x_level] && ($x_type=='open' || $x_type=='complete')) {
		            if (!isset($multi_key2[$x_tag][$x_level]))
		                $multi_key2[$x_tag][$x_level]=0;
		            else
		                $multi_key2[$x_tag][$x_level]++;
		            $add='['.$multi_key2[$x_tag][$x_level].']';
		        }
		        if (isset($xml_elem['value']) && trim($xml_elem['value'])!='' && !array_key_exists('attributes', $xml_elem)) {
		            if ($x_type == 'open')
		                $php_stmt_main=$php_stmt.'[$x_type]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
		            else
		                $php_stmt_main=$php_stmt.'[$x_tag]'.$add.' = $xml_elem[\'value\'];';
		            eval($php_stmt_main);
		        }
		        if (array_key_exists('attributes', $xml_elem)) {
		            if (isset($xml_elem['value'])) {
		                $php_stmt_main=$php_stmt.'[$x_tag]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
		                eval($php_stmt_main);
		            }
		            foreach ($xml_elem['attributes'] as $key=>$value) {
		                $php_stmt_att=$php_stmt.'[$x_tag]'.$add.'[$key] = $value;';
		                eval($php_stmt_att);
		            }
		        }
		    }
		    return $xml_array;
		}
		public function search_name_value($line, $name)
		{
			$nvs = $this->search_nested_arrays($line, 'OA:NAMEVALUE', 'AAIA:SUBLINE');
			
			if (empty($nvs))
				return null;
				
			if (array_key_exists('NAME', $nvs))
				$nvs = array($nvs);

			foreach ($nvs as $key => $pair)
			{
				if (!array_key_exists('NAME', $pair))
					continue;

				if ($pair['NAME'] == $name)
					if (array_key_exists('content', $pair))
						return $pair['content'];
			}
		}
	}

?>