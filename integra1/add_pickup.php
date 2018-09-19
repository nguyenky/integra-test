<?php

require_once('system/config.php');
require_once('system/utils.php');
require_once('system/imc_utils.php');

$chosenSku = $_GET['sku'];
$buyerId = $_POST['buyer_id'];
$chosenSite = $_POST['site_id'];

$error = null;

if (empty($chosenSku))
{
	$error = "No item was specified.";
}
else
{
	$items[$chosenSku] = 1;

	foreach (GetSKUParts($items) as $sku => $qty)
	{
		if (empty($sku))
			continue;
				
		$sku = strtoupper($sku);
			
		if (startsWith($sku, 'EOC'))
			$sku = substr($sku, 3);
				
		if (empty($sku))
			continue;
		
		$parts[$sku] = $qty;
	}

	$results = ImcUtils::QueryItems(array_keys($parts));
	
	if (empty($results))
	{
		$error = "The specified item is invalid.";
	}
	else
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);
		
		$q = <<<EOD
SELECT title
FROM ebay_listings
WHERE active = 1
AND sku = '%s'
AND title > ''
ORDER BY timestamp DESC
LIMIT 1
EOD;
		$rows = mysql_query(sprintf($q, cleanup($chosenSku)));
		$row = mysql_fetch_row($rows);
		if (!empty($row) && !empty($row[0]))
			$desc = $row[0];
		else
			$desc = null;

		$availSites = array();
		$coords = array();
		
		$q = <<<EOD
SELECT id, name, supplier_site, coordinates
FROM pickup_sites
WHERE active = 1
AND shipping_only = 0
EOD;
		$rows = mysql_query($q);
		while ($row = mysql_fetch_row($rows))
		{
			$siteId = $row[0];
			$siteName = $row[1];
			$supplierSite = $row[2];
			$coord = $row[3];
			
			$skip = false;
			
			foreach ($results as $result)
			{
				$sku = $result['sku'];
				$needed = $parts[$sku];
				
				if ($result["site_${supplierSite}"] < $needed)
				{
					$skip = true;
					break;
				}
			}
			
			if (!$skip)
			{
				$availSites[$siteId] = $siteName;
				$coords[$siteId] = $coord;
			}
		}
		
		if (empty($availSites))
		{
			$error = "This item is currently not available for local pickup.";
		}
		else
		{
			if (!empty($buyerId))
			{
				if (empty($chosenSite))
				{
					$error = "Please select a pickup location.";
				}
				else
				{
					$q = <<<EOD
INSERT INTO pickups (buyer_id, sku, site_id, added_date)
VALUES ('%s', '%s', '%s', NOW())
EOD;
					mysql_query(sprintf($q,
						cleanup(trim(strtolower($buyerId))),
						cleanup($chosenSku),
						cleanup($chosenSite)));
						
					$error = "Thank you! Now, please go back to eBay to purchase the item, and we will route the item to your chosen pickup location.";
				}
			}
		}
	}
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
  <head>
    <title>Local Pickup</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<style>
		body, p, td, h1, h2, h3, h4
		{
			font-family: tahoma, verdana;
			font-size: 14px;
		}
		#container, #header, #footer
		{
			width: 820px;
		}
		#container, #map, table
		{
			margin: 0px auto;
		}
		body, #container
		{
			text-align: center;
		}
		table, ul
		{
			text-align: left;
		}
	</style>
  </head>
<body>
<?php include_once("analytics.php") ?>
<img id="header" src="http://qeautoparts.eocenterprise.com/img/qe_header.jpg" usemap="#Map" border="0"/>
<div id="container">
<h4>Local Pickup</h4>
<?php
if (!empty($error))
	echo '<p>' . htmlentities($error) . '</p>';
else
{
?>
<ul>
<li>Please enter your eBay ID or Name, select a pickup location, and click Submit to mark your order for pickup.</li>
<li>Please go back to eBay or Q&amp;E Store and order the item, and we will route the item to the pickup location.</li>
<li>Once your order has been processed and is ready for pick up, you’ll receive a "Ready for Pick Up" confirmation e-mail.</li>
</ul>

<form method="POST">
	<table>
		<tr>
			<td>Item:</td>
			<td><?=htmlentities(empty($desc) ? $chosenSku : $desc)?></td>
		</tr>
		<tr>
			<td>eBay User ID / Name:</td>
			<td><input type="text" name="buyer_id" /></td>
		</tr>
		<tr>
			<td>Pickup Location:</td>
			<td>
				<select name="site_id" id="site_id">
<?php
foreach ($availSites as $siteId => $siteName)
	echo '<option value="' . $siteId . '">' . htmlentities($siteName) . "</option>\n";
?>
				</select>
			</td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" /></td>
		</tr>
	</table>
</form>
<?php
foreach ($coords as $siteId => $coord)
	echo '<input type="hidden" id="coord_' . $siteId . '" value="' . $coord . '"/>' . "\n";
?>

<h4>Compute Distance to Pickup Location</h4>
<ul>
<li>Input the address where you're coming from.</li>
<li>Click Compute to check how far you are from the pickup location selected above.</li>
</ul>
<input id="origin" size="50" type="text" />
<button onclick="javascript:getDirections()">Compute</button>
<p id="result"></p>
<div id="map" style="width: 400px; height: 300px;"></div>

<h4>More Information</h4>
<ul>
<li>Q&amp;E Auto Parts Local Pickup Service is a free service that allows you to pick up your eligible order in certain areas instead of having it shipped to you. Currently, this service is available for all customers Monday through Friday as long as your orders can be fulfilled locally at one of our service centers.</li>
<li>Payments can NOT be made at our local pick up center. All payments must be done online.</li>
<li>Our selection of pick up points will expand as we increase the number of service centers. The item you selected in eBay is currently available in the location(s) listed above.</li>
<li>Local pickup service hours are from 10:00 AM To 5:00 PM. (Please note: The Local Pick Up service center is closed during the following holidays -- New Years Day, Memorial Day, July 4, Labor Day, Thanksgiving Day and Christmas Day.)</li>
<li>When you arrive to pick up your order you will be required to provide your photo ID. This information must match with what you provided at the moment of purchase. A signature is required to confirm receipt of your packages.</li>
<li>Q&amp;E Auto Parts will hold your order in our pickup center for up to 2 calendar days after you receive your Ready for Pickup email. At the end of the seven day period, we will return your order for restocking and process a refund.</li>
<li>If you wish to return an item for an EXCHANGE or REFUND, please contact us at <a href="mailto:customerservice@qeautoparts.com">customerservice@qeautoparts.com</a> or call us at 1800-591-2863.</li>
<li>NOTE: WE WILL NOT RECEIVE ANY RETURNS OR PROVIDE ANY EXCHANGES AT OUR LOCAL PICKUP CENTERS.</li>
<li>If you have any questions or concerns please contact us at <a href="mailto:customerservice@qeautoparts.com">customerservice@qeautoparts.com</a> or call us at 1800-591-2863.</li>
</ul>
<br/>

</div>
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<script type="text/javascript">
function initialize()
{
	var cf = $('#coord_' + $('#site_id :selected').val()).val().split(',');
	var mapOptions =
	{
		zoom: 10,
		draggable: false,
		keyboardShortcuts: false,
		streetViewControl: false,
		overviewMapControl: false,
		panControl: false,
		rotateControl: false,
		scaleControl: false,
		scrollwheel: false,
		zoomControl: false,
		mapTypeControl: false,
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		center: new google.maps.LatLng(cf[0], cf[1])
	};
	map = new google.maps.Map(document.getElementById("map"), mapOptions);

	var drOptions =
	{
		suppressInfoWindows: true,
		draggable: false
	};
	directionsDisplay = new google.maps.DirectionsRenderer(drOptions);
	directionsDisplay.setMap(map);
}

google.maps.event.addDomListener(window, "load", initialize);

$("#origin").keypress(function (e)
{
	if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13))
	{
		getDirections();
		return false;
	}
	else return true;
});

function getDirections()
{
	var directionsService = new google.maps.DirectionsService();

	var request =
	{
		origin: $('#origin').val(), 
		destination: $('#coord_' + $('#site_id :selected').val()).val(),
		travelMode: google.maps.DirectionsTravelMode.DRIVING
	};

	directionsService.route(request, function(response, status)
	{
		if (status == google.maps.DirectionsStatus.OK)
		{
			var meters = response.routes[0].legs[0].distance.value;
			var miles = meters / 1609.34;
			var km = meters / 1000;
			var minutes = response.routes[0].legs[0].duration.value / 60;

			$('#result').html("You are just <b>" + miles.toFixed(2) + " mi (" + km.toFixed(2) + " km)</b> away from the selected pickup location. About <b>" + Math.round(minutes) + "</b> minutes drive.");
			directionsDisplay.setDirections(response);
		}
		else
		{
			$('#result').text("We can't find the address you entered.");
		}
	});
}
</script> 

<?php
}
?>
<img id="footer" src="http://qeautoparts.eocenterprise.com/img/qe_footer.jpg"/>
<map name="Map" id="Map1">
	<area shape="rect" coords="0,0,440,130" href="http://stores.ebay.com/id=663126684"/>
</map>
<script type="text/javascript">
  (function() {
    var se = document.createElement('script'); se.type = 'text/javascript'; se.async = true;
    se.src = '//commondatastorage.googleapis.com/code.snapengage.com/js/415ccca8-7dbb-4b19-b7f9-1e4fdd981e56.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(se, s);
  })();
</script>
</body>
</html>