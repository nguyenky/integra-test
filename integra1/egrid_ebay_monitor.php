<?php

require_once('system/config.php');

require_once('datagrid/datagrid.class.php');

require_once('system/acl.php');


$user = Login('egrid');

set_time_limit(0);

$query = trim($_REQUEST['q']);

$action = $_REQUEST['action'];

$seller = EBAY_SELLER;


mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);

mysql_select_db(DB_SCHEMA);


$rows = mysql_query("SELECT id FROM integra_users WHERE email = '${user}'");

$row = mysql_fetch_row($rows);

$userId = $row[0];
$today = date('Y-m-d H:i:s');
$keywordExists = 0;

settype($userId, 'integer');

$q_result = mysql_query("SELECT *FROM ebay_research_monitor_query WHERE user_id = '$userId' ORDER BY created_at DESC LIMIT 1") or die(mysql_error());
    	
$q_res = mysql_fetch_assoc($q_result);

$query_id = $q_res['id']; 


if ($action == 'clear')
{

  //mysql_query("DELETE FROM ebay_research_monitor WHERE user_id = '$userId' AND $query_id");

}

else if ($action == 'search')

{
   
    if ( $query == '' )
    {   
   
    	return false;
    }
    else{

    	$q = mysql_query("SELECT *FROM ebay_research_monitor_query WHERE keyword = '$query' AND user_id = '$userId' AND is_monitor = 1 ") or die(mysql_error());
    	if(mysql_num_rows($q) > 0 ){
          $keywordExists = 1;
    	}else{
            $keywordExists = 0;
	    	mysql_query("INSERT INTO ebay_research_monitor_query (keyword, user_id, created_at) VALUES ('$query', '$userId','$today')" ) or die(mysql_error());
		
	    	$result = mysql_query("SELECT id FROM ebay_research_monitor_query WHERE user_id = '$userId' ORDER BY created_at DESC LIMIT 1") or die(mysql_error());
	    	
	    	$res = mysql_fetch_assoc($result);
	    	
	    	$query_id = $res['id']; 
		
	    	$ch = curl_init();
		
			curl_setopt($ch, CURLOPT_URL, "http://integra.eocenterprise.com/egrid_ebay_research.php?u=${userId}&q=" . urlencode($query)."&qry_id=".$query_id);
		
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);
		
			curl_exec($ch);
	  }
	}
	
}

else if ($action == 'save_query')

{
  if(!$keywordExists)
  {
   $item_ids = str_replace('%2', ',', $_REQUEST['selected_items']); 

   //mysql_query("INSERT INTO ebay_research_monitor_query ('keyword', 'user_id', 'created_at') VALUES ('$query', '$userId','$today')" );

   $result = mysql_query("SELECT id FROM ebay_research_monitor_query ORDER BY created_at DESC LIMIT 1") or die(mysql_error());
    
   $res = mysql_fetch_assoc($result);

   $query_id = $res['id'];
  
   //mysql_query("DELETE FROM ebay_research_monitor WHERE item_id NOT IN ($item_ids) AND user_id = '$userId';") or die(mysql_error()); //query_id = '$query_id' AND 
   mysql_query("UPDATE ebay_research_monitor_query SET is_monitor = 1 WHERE id = '$query_id'") or die(mysql_error());
   mysql_query("UPDATE ebay_research_monitor SET is_monitor = 1 WHERE item_id IN ($item_ids) AND query_id = '$query_id'") or die(mysql_error());
   mysql_query("UPDATE ebay_research_monitor SET is_monitor = 2 WHERE item_id NOT IN ($item_ids) AND query_id = '$query_id'") or die(mysql_error()); //query_id = '$query_id' AND 
 }
}


session_start();

ob_start();


$paging = array(

	"results" => false,

	"results_align" => "left",

	"pages" => false,

	"pages_align" => "center",

	"page_size" => false,

	"page_size_align" => "right"

	);

$columns = array(

	
	"selected_item" => array(

		"header" => "#",

		"type" => "checkbox",

		"align" => "center",

		"wrap" => "nowrap",
		
		"true_value" => 1,

		"false_value" => 0
		
     ),

	"image" => array(

		"header" => "Image",

		"type" => "data",

		"align" => "center"),

	"item_id" => array(

		"header" => "Item ID",	

		"type" => "link",	

		"align" => "left",

		"wrap" => "nowrap",

		"sort_by" => "item_id",

		"field_key" => "item_id",

		"field_data" => "item_id", 

        "target" => "_blank",

		"href" => "http://www.ebay.com/itm/{0}"),

	"title" => array(

		"header" => "Title",

		"type" => "link",

		"align" => "left",

		"wrap" => "nowrap",

		"sort_by" => "title",

		"field_key" => "item_id",

		"field_data" => "title", 

		"target" => "_blank",

		"href" => "http://www.ebay.com/itm/{0}"),

	"category" => array(

		"header" => "Category",

		"type" => "label",

		"align" => "left",

		"wrap" => "nowrap"),

	"brand" => array(

		"header" => "Brand",

		"type" => "label",

		"align" => "left",

		"wrap" => "nowrap"),

	"seller" => array(

		"header" => "Seller",

		"type" => "label",

		"align" => "left"),

	/*"rating" => array(

		"header" => "Rating",

		"type" => "label",

		"align" => "left",

		"wrap" => "nowrap"),

*/

	"price" => array(

		"header" => "Price",

		"type" => "money",

		"align" => "right",

		"sort_by" => "price",

		"sort_type" => "numeric",

		"sign" => "",

		"sign_place" => "before",

		"decimal_places" => "2",

		"dec_separator" => ".",

		"thousands_separator" => ","),

	"shipping" => array(

		"header" => "Shipping",

		"type" => "money",

		"align" => "right",

		"sort_by" => "shipping",

		"sort_type" => "numeric",

		"sign" => "",

		"sign_place" => "before",

		"decimal_places" => "2",

		"dec_separator" => ".",

		"thousands_separator" => ","),

	/*"num_hit" => array(

		"header" => "Hits",

		"type" => "label",

		"align" => "right",

		"sort_by" => "num_hit",

		"sort_type" => "numeric"),*/

	"num_sold" => array(

		"header" => "Qty Sold",

		"type" => "label",

		"align" => "right",

		"sort_by" => "num_sold",

		"sort_type" => "numeric"),

	"num_compat" => array(

		"header" => "Compatible Vehicles",

		"type" => "label",

		"align" => "right",

		"sort_by" => "num_compat",

		"sort_type" => "numeric"),


	"mpn" => array(

		"header" => "MPN",

		"type" => "label",

		"align" => "left",

		"wrap" => "nowrap"),

	"opn" => array(

		"header" => "OPN",

		"type" => "label",

		"align" => "left",

		"wrap" => "nowrap"),

	"ipn" => array(

		"header" => "IPN",

		"type" => "label",

		"align" => "left",

		"wrap" => "nowrap"),

	);


$result = mysql_query("SELECT id FROM ebay_research_monitor_query WHERE user_id = '$userId' ORDER BY created_at DESC LIMIT 1") or die(mysql_error());
    
$res = mysql_fetch_assoc($result);

$query_id = $res['id']; 

$sql = <<<EOD

SELECT user_id, item_id, item_id AS selected_item,  title, 

CONCAT('<a class="preview" href="', image_url, '"><img class="preview" src="', image_url, '"/></a>') AS image,

price, shipping, seller, num_hit, num_sold, num_compat, num_avail,

category, mpn, ipn, opn, brand

FROM ebay_research_monitor

WHERE user_id = '${userId}' AND is_monitor < 2 AND query_id = '$query_id'

GROUP BY item_id

EOD;


$dg = new DataGrid(false, false, 'sh_');

$dg->SetColumnsInViewMode($columns);

$dg->DataSource("PEAR", "mysql", DB_HOST, DB_SCHEMA, DB_USERNAME, DB_PASSWORD, $sql, array('num_sold' => 'DESC'));

$layouts = array(

	"view" => "0",

	"edit" => "0", 

	"details" => "1", 

	"filter" => "0"

	);

$dg->SetLayouts($layouts);

$dg->SetPostBackMethod('AJAX');

$dg->SetModes(array());

$dg->SetCssClass("x-blue");

$dg->AllowSorting(true);

$dg->AllowPrinting(false);

$dg->AllowExporting(true, true);

$dg->AllowExportingTypes(array('csv'=>'true', 'xls'=>'true', 'pdf'=>'false', 'xml'=>'false'));

$dg->SetPagingSettings($paging, array(), array(), 100, array());

$dg->AllowFiltering(false, false);

$dg->Bind(false);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">

<html>

  <head>

    <title>eBay Research Monitor </title>

    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

	<style>

		img.preview

		{

			height: 50px;

		}

		#preview

		{

			position:absolute;

			border:1px solid #ccc;

			background:#333;

			padding:5px;

			display:none;

			color:#fff;

		}

		.ourListing

		{

			background-color: lightgreen !important;

		}

		input:hover#search

		{

			border: 2px inset !important;

		}

	</style>

  </head>

<body>

<?php include_once("analytics.php") ?>

<center>

<br/>

<?php

	if ($_REQUEST['sh_export'] != 'true'):

?>

		<form id="searchForm" method="GET">

			<div class="x-blue_dg_caption" style="display: inline;">  Keyword Search:</div>

			<input id="action" name="action" type="hidden" value="search"/>

			<input id="search" name="q" type="text" value="<?=$query?>"/>

			<input class="x-blue_dg_button" type="submit" value="Search" /> &nbsp;

			<!-- <input class="x-blue_dg_button" type="button" onclick="clearResults();" value="Start Over" /> &nbsp; -->

			<input class="x-blue_dg_button" type="button" onclick="saveQuery();" value="Save query" /> &nbsp;

			<input type="hidden" value="" name="selected_items" class="selected_items">

			<input type="hidden" value="<?php echo $keywordExists;?>" name="keywordExists" class="keywordExists">

		</form>

<?php

	endif;


  if($keywordExists){
  	 echo '<div align="center"><h4 style="color:red"> Keyword already exists.</h4></div>';

  }
?>

<div class="apphp_datagrid">

<?php

	$dg->Show();

    ob_end_flush();

?>

</div>

</center>

 <div align="right">
	
	 <p><sup>
		<?php  
		 //echo  $q_res['id']. ' - '. $q_res['keyword'] . ' - ' . $q_res['created_at'];  
		?>
	 </sup>
	 </p>
</div>

<br/><br/><br/><br/><br/><br/>

<script src="js/jquery.min.js"></script>

<script>

this.imagePreview = function()

{

	xOffset = 10;

	yOffset = 30;

	$("a.preview").hover(function(e)

	{

		this.t = this.title;

		this.title = "";	

		var c = (this.t != "") ? "<br/>" + this.t : "";

		$("body").append("<p id='preview'><img src='"+ this.href +"' alt='Image preview' />"+ c +"</p>");								 

		$("#preview")

			.css("top",(e.pageY - xOffset) + "px")

			.css("left",(e.pageX + yOffset) + "px")

			.fadeIn("fast");						

    },

	function()

	{

		this.title = this.t;	

		$("#preview").remove();

    });	

	$("a.preview").mousemove(function(e)

	{

		$("#preview")

			.css("top",(e.pageY - xOffset) + "px")

			.css("left",(e.pageX + yOffset) + "px");

	});			

};

$(document).ready(function()

{

	imagePreview();
    checkboxes();
	$('td:contains("<?=$seller?>")').closest('tr').addClass('ourListing');


	/*$('.x-blue_dg_checkbox').change(function () 
	{
      $('tbody tr td input[type="checkbox"]').prop('checked', $(this).prop('checked'));   
    
    }); */  


});

$('#sh_dg_ajax_container').bind("DOMSubtreeModified",function(){

	$('td:contains("<?=$seller?>")').closest('tr').addClass('ourListing');
	 checkboxes();

});

$('.apphp_datagrid').bind('change',function(){

	$('td:contains("<?=$seller?>")').closest('tr').addClass('ourListing');

});

function clearResults()

{

	$('#action').val('clear');

	$('#searchForm').submit();

}

 function checkboxes()
 {
       $('tbody tr td input[type="checkbox"]').each(function(){
            $(this).removeAttr('onclick');
        });
 }

 function saveQuery()

{

	$('#action').val('save_query');	
	
	var c = []
	$('input[type="checkbox"]:checked').each(function() {
       c.push(this.value); 
    });

    $('.selected_items').val(c);

	    if( $('.keywordExists').val() == 1 ) {
	         alert('Unable to save, keyword already exists');
	    }else{
		    if( c.length > 0 )
		    {
		       $('#searchForm').submit();
		    }else{           
		     alert('Please select an item(s)');     
		     return false;
		    }
	    }
}

</script>

</body>

</html>