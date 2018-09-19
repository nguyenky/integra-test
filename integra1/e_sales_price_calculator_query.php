<?php

    require_once('system/config.php');
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
    
       $data = array('profit' => array(), 'shipping' => array(), 'predefined' => array() ); 

       $profit_query   = mysql_query("SELECT * FROM e_profit_percentage ORDER BY min_cost");
					    
		    while ($row = mysql_fetch_assoc($profit_query)) 
		    {
		       $item = array(
		       	          'id'        => $row['id'],
		       	          'profit'     => $row['profit'],
		       	          'min_cost'   => $row['min_cost'],
		       	          'max_cost'   => $row['max_cost'],
		       	          'from_date'  => $row['from_date'],
		       	          'to_date'    => $row['to_date'],
		       	          'entry_date' => $row['entry_date'],
		       	          'table'      => 'e_profit_percentage',
		       	          'action'     => 'updateProfitPercentage'
		       	         );
		       	  array_push($data['profit'], $item);

		    }

		  $shipping_query   = mysql_query("SELECT * FROM e_shipping_rate ORDER BY weight_from ASC;");			

		    while ($row = mysql_fetch_assoc($shipping_query)) 
		    {
		       $item = array(
		       	          'id'         => $row['id'],
		       	          'weight_from' => $row['weight_from'],
		       	          'weight_to'  => $row['weight_to'],
		       	          'rate'       => $row['rate'],	 
		       	          'entry_date' => $row['entry_date'],
		       	          'table'      => 'e_shipping_rate',
		       	          'action'     => 'updateShippingRate'
		       	         );
		       	  array_push($data['shipping'], $item);

		    }
      
       $predefined_query = mysql_query("SELECT *FROM e_shipping_rates"); 

        while ($row = mysql_fetch_assoc($predefined_query)) 
        {
           $item = array(
                     'id'         => $row['id'],
                     'mpn'        => $row['mpn'], 
                     'min_qty'    => $row['min_qty'], 
                     'max_qty'    => $row['max_qty'], 
                     'profit'     => $row['profit'],
                     'shipping'   => $row['shipping'], 
                     'created_by' => $row['created_by'], 
                     'created_at' => $row['created_at'], 
                     'updated_by' => $row['updated_by'], 
                     'updated_at' => $row['updated_at'], 
                     );

           array_push($data['predefined'], $item);

        }
      
       echo json_encode($data, JSON_NUMERIC_CHECK);        

?>