<?php

  require_once('system/config.php');
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
   
	
       $json = file_get_contents('php://input');
       $obj  = json_decode($json);

       if($obj->action == 'updateProfitPercentage')
       {
        
        $query = mysql_query("UPDATE e_profit_percentage 
        	                  SET profit    = '$obj->profit',
        	                      min_cost  = '$obj->min_cost',
               								  max_cost  = '$obj->max_cost'
               							   WHERE id = '$obj->id'
        	                  ");

         echo json_encode($obj, JSON_NUMERIC_CHECK);
                
       }

       elseif($obj->action == 'updateShippingRate')
       {
       
          $query = mysql_query("UPDATE e_shipping_rate 
        	                    SET weight_from = '$obj->weight_from',
                                  weight_to = '$obj->weight_to',
        	                        rate   = '$obj->rate'
 							               WHERE id  = '$obj->id'
        	                  ");

          echo json_encode($obj, JSON_NUMERIC_CHECK);
       }

       elseif ($obj->action == 'getProfitPercentageForCost')
       {
       	        
	     if($obj->cost > 300)
       {
          $query = mysql_query("SELECT *FROM e_profit_percentage WHERE min_cost > 300 LIMIT 1");           
         }else{
          $query = mysql_query("SELECT *FROM e_profit_percentage WHERE min_cost < '$obj->cost' AND max_cost > '$obj->cost'  LIMIT 1");                  
         }

         $row   = mysql_fetch_array($query);
         $profit = $row['profit'];

         $data = array('profit' => $profit );
         echo json_encode($data, JSON_NUMERIC_CHECK);
       
       }

       elseif ($obj->action == 'getShippingRateForWeight')
       {
           $weight = $obj->weight;

          /* if( $weight < 1)
           {
             $weight = 1;
           }*/
           if($weight > 16)
           {
             $weight = 16;
           }

           $query = mysql_query("SELECT *FROM e_shipping_rate WHERE weight_from <= '$weight' AND weight_to >= '$weight' LIMIT 1"); 
           $row   = mysql_fetch_array($query);
           $rate = $row['rate'];

          $data = array('rate' => $rate );
          echo json_encode($data, JSON_NUMERIC_CHECK);    
                
       }
       elseif ($obj->action == 'addNewProfit')
       {
       
        $query = mysql_query("INSERT INTO e_profit_percentage (profit,min_cost,max_cost)
                              VALUES('$obj->profit','$obj->min_cost', '$obj->max_cost' ); 
                            ");

         echo json_encode($obj, JSON_NUMERIC_CHECK);

       }
       elseif ($obj->action == 'deleteProfit')
       {
       
        $query = mysql_query("DELETE FROM e_profit_percentage WHERE id  = '$obj->id'");

         echo json_encode($obj, JSON_NUMERIC_CHECK);

       }

       elseif ($obj->action == 'addNewShippingRate')
       {
       
        $query = mysql_query("INSERT INTO e_shipping_rate (weight_from,weight_to, rate) 
                              VALUES ('$obj->weight_from','$obj->weight_to','$obj->rate');");

         echo json_encode($obj, JSON_NUMERIC_CHECK);

       }
       elseif ($obj->action == 'deleteShipping')
       {
       
        $query = mysql_query("DELETE FROM e_shipping_rate  WHERE id  = '$obj->id'");
        echo json_encode($obj, JSON_NUMERIC_CHECK);

       }

       //pre defined rates
       elseif($obj->action == 'getDefinedRates')
       {

       $data = [];
       $query = mysql_query("SELECT *FROM e_shipping_rates WHERE mpn != '' ORDER BY mpn"); 

        while ($row = mysql_fetch_assoc($query)) 
        {
           $item = array(
                     'id'         => $row['id'],
                     'mpn'        => $row['mpn'], 
                     'min_qty'    => $row['min_qty'], 
                     'max_qty'    => $row['max_qty'], 
                     'profit'     => $row['profit'],
                     'shipping'   => $row['shipping'], 
                     'notes'      => $row['notes'],
                     'type'      => $row['type'],
                     'created_by' => $row['created_by'], 
                     'created_at' => $row['created_at'], 
                     'updated_by' => $row['updated_by'], 
                     'updated_at' => $row['updated_at'], 
                     );

           array_push($data, $item);

        }
        echo json_encode($data);

       }

       elseif( $obj->action == 'insertDefinedRates')
       {
         
        
         $mpn        = $obj->data->mpn;
         $min_qty    = $obj->data->min_qty;
         $max_qty    = $obj->data->max_qty;
         $profit     = $obj->data->profit;
         $shipping   = $obj->data->shipping;
         $type       = $obj->data->type;
         $notes      = $obj->data->notes;
         $created_by = '1';
         $created_at = date('Y-m-d H:i:s');

         $query = mysql_query("INSERT INTO e_shipping_rates (mpn, min_qty, max_qty, profit, shipping, type, notes, created_by, created_at)
                               VALUES('$mpn', '$min_qty', '$max_qty', '$profit', '$shipping', '$type','$notes', '$created_by', '$created_at')") or die(mysql_error());

         echo json_encode($obj, JSON_NUMERIC_CHECK);

       }
      
      elseif($obj->action ==  'updateDefinedRates')
      {
         $id         = $obj->data->id;
         $mpn        = $obj->data->mpn;
         $min_qty    = $obj->data->min_qty;
         $max_qty    = $obj->data->max_qty;
         $profit     = $obj->data->profit;
         $shipping   = $obj->data->shipping;
         $type       = $obj->data->type;
         $notes      = $obj->data->notes;
         $updated_by = '1';
         $updated_at = date('Y-m-d H:i:s');



         $query = mysql_query("UPDATE e_shipping_rates 
                              SET mpn = '$mpn',
                                  min_qty = '$min_qty',
                                  max_qty = '$max_qty',
                                  profit = '$profit',
                                  shipping = '$shipping',
                                  type = '$type',
                                  notes  = '$notes',
                                  updated_by = '$updated_by',
                                  updated_at = '$updated_at'
                              WHERE id  = '$id'");

          echo json_encode($obj, JSON_NUMERIC_CHECK);

      }
      elseif ($obj->action == 'deleteDefinedRates') 
      {  
        $id   = $obj->data;
        $query = mysql_query("DELETE FROM e_shipping_rates WHERE id= '$id' "); 
        echo 'deleted';
      }
      elseif ($obj->action == 'findDefinedRates')
      { 
        $sku = $obj->data->sku;
        $qty = $obj->data->quantity;

         $data = array();
         $query = mysql_query("SELECT *FROM e_shipping_rates WHERE mpn = '$sku' AND ( min_qty <= '$qty' AND max_qty >= '$qty') "); 
 
        while ($row = mysql_fetch_assoc($query)) 
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

           array_push($data, $item);

        }
        echo json_encode($data, JSON_NUMERIC_CHECK);
           
      }
      elseif ($obj->action == 'getKitComponents')
      { 
       
        $sku = $obj->sku;
        $quantity = $obj->quantity;
        $index = $obj->index;       

        $query = mysql_query("SELECT *FROM integra_prod.products WHERE sku = '$sku' AND is_kit = '1'") or die(mysql_error());
        
        $request = 'imc_'; 
        if (strpos($sku,'.') !== false) {
          $request = 'ssf_';
        }

        $data = [
          'sku' => $sku,
          'quantity' => $quantity,
          'index' => $index,
          'main_request' => $request,
          'components' => [],
        ];

        if(mysql_num_rows($query) > 0 ) 
        {
           $row = mysql_fetch_assoc($query);
           $product_id = $row['id'];

           $components = mysql_query("SELECT a.*, b.quantity FROM integra_prod.products a 
                                      LEFT JOIN integra_prod.kit_components b ON a.id = b.component_product_id
                                      WHERE b.product_id = '$product_id'") or die(mysql_error());
            
           $data['is_kit'] = 1;
           while ($field = mysql_fetch_assoc($components)) 
           {
             
             $item = array(
               'id' => $field['id'],
               'skus' => $field['sku'],               
               'kit_quantity' => $field['quantity'],
               'content' => apiCall($field['sku']),                                   
              );   

             array_push($data['components'], $item );              
           }                 
        }

        else{  
         $data['is_kit'] = 0;
         $item = ['skus'=> $sku,'kit_quantity'=>0,  'content' => apiCall($sku),  ];
         array_push($data['components'], $item ) ;    
      
        }

        echo json_encode($data, JSON_NUMERIC_CHECK);

      }


      function apiCall($sku)
      {
             $request = 'imc_'; 
             if (strpos($sku,'.') !== false) {
               $request = 'ssf_';
             }

              $curl = curl_init();            
              curl_setopt_array($curl, array(
                  CURLOPT_RETURNTRANSFER => 1,
                  CURLOPT_URL => 'http://integra.eocenterprise.com/'.$request.'ajax.php?sku='.$sku,                
              ));
            
              $resp = json_decode(curl_exec($curl));                
              curl_close($curl);

            return array( 'request_type'=> $request, 'item'=> $resp);

      }


   
     

?>