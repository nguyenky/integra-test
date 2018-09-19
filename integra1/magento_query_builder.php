<?php
 
	require_once('system/config.php');
	mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
	mysql_select_db(DB_SCHEMA);
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
     <title>Batch Research</title>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
      <script type="text/javascript" src="js/jquery.min.js"></script>

      <style type="text/css">
         fieldset{
         	width: 90%;
         	margin: 0 auto;
         	padding: 5px 10px;
         }
         .disabled{
         	  pointer-events: none;  
			  opacity: 0.5;
			  background: #CCC;
			  padding: 10px;
         }
         .note{
         	color: red;
         }

      </style>
    </head>
 <body>   

 <fieldset>
	<legend> <h3> Magento Query Builder </h3> </legend>

		<?php

         $source_table = 'integra_prod.ebay_scraped_listings';//'integra.esi_products'; //
         $source     = mysql_query("SELECT * FROM $source_table");
         $source_result = array_keys(mysql_fetch_assoc($source));
         $attribute  = mysql_query("SELECT * FROM magento.eav_attribute WHERE entity_type_id ='4'");

          $attribute_arr[] = "";

          while ($row = mysql_fetch_assoc($attribute)) 
          {
          	$attribute_arr[$row['attribute_code']] = $row['attribute_code'];//. ' ('.$row['entity_type_id'] .')';
          }
          
          print "
            <i>source table:</i> <b>$source_table</b> <br> 
            <table border ='1' width='100%' cellpadding='4' class='source_tbl'>";     
	            	print "<tr>
	          	            <th>Field</th>
                            <th>catalog_product_entity_</th>
                            <th>attribute_code (entity_type_id)</th>                           
                            <th>Action</th>
	          	          </tr>";
             
             
             
           $list = 1;
	         $product_entity = array(
	         	                   ''=>'',
	         	                   'datetime' => ' datetime ',
	         	                   'decimal'  => ' decimal ',
	         	                   'int'      => ' int ',
	         	                   'text'     => ' text ',
	         	                   'varchar'  => ' varchar ');

            //1: INSERT all sku/mon into catalog_product_entity
            $query = "INSERT IGNORE INTO magento.catalog_product_entity (entity_type_id, attribute_set_id, sku)
(SELECT 4, a.attribute_set_id, CONCAT('EW','',src.sku)
 FROM $source_table src, magento.eav_attribute_set a
WHERE a.attribute_set_name = 'Auto Part' AND a.entity_type_id = 4);";
           

            $ignore_field = ['id','big_image', 'small_image','item_id','category_id','mpn','ipn','opn','category','seller','shipping','shipping_type','rating','top','hits','sold'];       
	         foreach ($source_result as $key => $field ) 
	         {
             if( !in_array($field,$ignore_field)) {
    	          print "<tr>
          	             <td><sup>".$list++.".</sup> ".$field."<input type='hidden' name='field' class='field' value='".$field."'></td>
                         <td align='center'>";

    	                    print  "<select name='entity_type' class='entity_type'>";
    	                    foreach ($product_entity as $type) 
    	                    {
    	                      print "<option value=$type> $type </option>";     	
    	                    }
    	                    print "</select>
                        </td>";
                 
                  print "<td align='center'>";

    	                    print  "<select name='attr_code' class='attr_code'>";
    	                    foreach ($attribute_arr as $attr_key => $attr_value)
    	                    {
    	                      print "<option value=$attr_key> $attr_value </option>";     	
    	                    }
    	                    print "</select>
                         </td>               
                         <td align='center'><input type='checkbox' name='selected_row'></td>
    	          	    </tr>";

                     /*
                       insert ignore into catalog_category_product (category_id, product_id, position (select 4309, entity_id, 1 from catalog_product_entity)
                       insert ignore into catalog_product_website (product_id, website_id) (select entity_id, website_id from catalog_product_entity, core_website where website_id > 0)
                       */
              }
	         }


	    ?>   	      
	    
	    <tr>
	     <td align="right" colspan="5"><button id="generate"> Generate Query </button>
	      <hr>
	      <textarea cols="150" rows="5" class="result_query"><?php echo $query; ?></textarea>
         </td>
        </tr>
        </table> <br>         
     
  </fieldset>

  <script type="text/javascript">

      $(function () {

            $('#generate').click(function(){
              var values = $(".source_tbl input[name=selected_row]:checked").map(function() 
                 {                
                      row = $(this).closest("tr");
                      field       = $(row).find(".field").val();                            
                      attr_code   = $(row).find(".attr_code option:selected").val();
                      entity_type = $(row).find('.entity_type option:selected').val(); 
                      source      = "<?php echo $source_table; ?>";

                      query = "INSERT INTO catalog_product_entity_"+entity_type + " (entity_type_id, attribute_id, store_id, entity_id, value)"
                              +"\n(SELECT 4, a.attribute_id, 0, p.entity_id, src."+field 
                              +"\nFROM "+source +" src, magento.eav_attribute a, magento.catalog_product_entity p"
                              +"\nWHERE a.attribute_code = '"+attr_code+ "' AND src.sku = p.sku) \n ON DUPLICATE KEY UPDATE value = VALUES(value);";
                 
                      $('.result_query').val($('.result_query').val()+ "\n\n"+query);

                      return { 
                         str:query
                      }
                  }).get();
               
               //$('.result_query').val($('.result_query').val() + "\n\n"+values.str);
              console.log(values);

             });
        });

  </script>


</body>
</html>