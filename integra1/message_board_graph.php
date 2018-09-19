<?php

require_once('system/config.php');
require_once('system/acl.php');

$user = Login('shipgrid');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);
mysql_set_charset('utf8');

$graphDays = 2;

if (!empty($_REQUEST['gd']))
    $graphDays = $_REQUEST['gd'];
?>

<html>
<head>
	<title>Message Board Graph</title>
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script> 
    <script src="https://www.google.com/jsapi" type="text/javascript"></script>	
</head>
<body>
	
	<div class="col-md-12" align="center">

        <h2>Message Board Graph</h2>
        <br>
        <form class="form-inline">
          <div class="form-group">
            <label for="hourlyFilter">Days in graph:</label>
            <select name="gd" class="form-control" id="hourlyFilter">
                <option value="2" <?=($graphDays==2)?'selected':''?>>2</option>
                <option value="5" <?=($graphDays==5)?'selected':''?>>5</option>
                <option value="7" <?=($graphDays==7)?'selected':''?>>7</option>
                <option value="14" <?=($graphDays==14)?'selected':''?>>14</option>
                <option value="21" <?=($graphDays==21)?'selected':''?>>21</option>
            </select>
           </div>
          <input type="submit" value="Filter" class="btn btn-primary">
        </form>

    <div>      
    <?php
     $log = ['reply',  'flagged','compose', 'trash', 'livehelperchat']; 
     for ($lcount=0; $lcount < count($log); $lcount++) { 
    ?>
     <section class="row">     
       <div style="height:600px; width: 100%" id="logGraph_<?=$lcount;?>"></div> 
     </section> <hr>
    <?php
      }
    ?>
  
   </div>
</div>
<br/><br/>

<div class="modal fade" id="graphModal">
  <div class="modal-dialog">
    <div class="modal-content">
     
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Modal title</h4>
      </div>

      <div class="modal-body">       
      </div>    

    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->


<?php

for ($lcount=0; $lcount < count($log); $lcount++) { 

/*auto-response ,compose ,ebay_order_confirmation ,email_order_confirmation ,flagged ,followup ,reply ,trash*/
//reply
$log_type = $log[$lcount];
if( $log_type != 'livehelperchat')
{

/* if($log_type == 'compose'){
$q=<<<EOQ
    SELECT DATE_FORMAT( cm_timestamp,  '%m/%d %H:00' ) AS HR, cm_user, COUNT(*) AS CNT
    FROM api_composed_messages
    WHERE cm_timestamp >= DATE_SUB( CURDATE( ) , INTERVAL ${graphDays} DAY )  
    GROUP BY 1, 2
    ORDER BY cm_timestamp
EOQ;
 }
 else{*/
$q=<<<EOQ
    SELECT DATE_FORMAT( log_timestamp,  '%m/%d %H:00' ) AS HR, log_user, COUNT(*) AS CNT
    FROM api_messages_logs
    WHERE log_timestamp >= DATE_SUB( CURDATE( ) , INTERVAL ${graphDays} DAY )
    AND log_activity = '$log_type'
    AND log_user != 1
    GROUP BY 1, 2
    ORDER BY log_timestamp
EOQ;


}else{

$q=<<<EOQ
    SELECT from_unixtime(time,'%m/%d %H:00') AS HR, name_support, COUNT(*) AS CNT
    FROM magento_chat.lh_msg
    WHERE from_unixtime(time,'%Y-%m-%d') >= DATE_SUB( CURDATE( ) , INTERVAL ${graphDays} DAY ) 
    AND user_id >= 1   
    GROUP BY 1, 2
    ORDER BY time
EOQ;

}

$people = [];
$shipGraph = [];
$result = mysql_query($q) or die(mysql_error());
while ($row = mysql_fetch_row($result))
{
    $people[$row[1]] = 1;
    $shipGraph[$row[0]][$row[1]] = $row[2];
}

?>

    <script type="text/javascript">
        google.load('visualization', '1.0', {'packages':['corechart']});
        google.setOnLoadCallback(drawGraph);

        function drawGraph()
        {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Hours');

            <?php
                foreach ($people as $p => $x)
                {
                    echo "data.addColumn('number', '{$p}');";
                }
            ?>

            data.addRows([
                <?php
                        for ($i = ($graphDays * 24); $i >= 0; $i--)
                        {
                            $hour = intval(date("H", strtotime("-$i hour")));

                            if ($hour < 7 || $hour > 19)
                                continue;

                            $dh = date("m/d H:00", strtotime("-$i hour"));
                            if (array_key_exists($dh, $shipGraph))
                            {
                                echo "['${dh}', ";
                                $tmp = [];

                                foreach ($people as $p => $x)
                                {
                                    if (array_key_exists($p, $shipGraph[$dh]))
                                        $tmp[] = $shipGraph[$dh][$p];
                                    else $tmp[] = 0;
                                }

                                echo implode(',', $tmp) . "],\n";
                            }
                            else
                            {
                                echo "['${dh}', ";
                                $tmp = [];

                                foreach ($people as $p => $x)
                                {
                                    $tmp[] = '0';
                                }

                                echo implode(',', $tmp) . "],\n";
                            }
                        }

                        echo "['', ";
                        $tmp = [];

                        foreach ($people as $p => $x)
                        {
                            $tmp[] = '0';
                        }

                        echo implode(',', $tmp) . "]\n";
                ?>
            ]);


            var settings =
            {
                title: 'Hourly <?=$log_type;?>',
                hAxis:
                {
                    title: 'Last <?=$graphDays?> Days',
                    slantedText: true,
                    slantedTextAngle: 90
                },
                vAxis:
                {
                    title: '<?=$log_type;?>',
                    viewWindowMode: 'maximized'
                },
                legend: 'right',
                isStacked: true
            };

            var chart = new google.visualization.ColumnChart(document.getElementById('logGraph_<?=$lcount;?>'));
            chart.draw(data, settings);         

            google.visualization.events.addListener(chart, 'select', selectHandler);

            function selectHandler() {
            
                    var type = "<?=$log_type;?>";
                    var selectedItem = chart.getSelection();                
                      if (selectedItem) {
                         var row = selectedItem[0].row; 
                         
                         /* console.log('date '+data.getValue(row,0));
                          console.log('count '+data.getValue(row,1));
                          console.log('user ' + data.getColumnLabel(selectedItem[0].column)); */
                          
                          var graphDate = data.getValue(row,0);
                          var graphUser = data.getColumnLabel(selectedItem[0].column);


                           var postData = {
                             'action' : type,
                             'graphDate': graphDate,
                             'graphUser': graphUser
                            }

                            $('#graphModal').modal('show')
                            $('#graphModal').find('.modal-title').text(graphUser + " ("+graphDate+" ) ");
                            $('#graphModal').find('.modal-body').html('<i class="fa fa-cog fa-2x fa-spin"></i>');

                            $.ajax({
                                  url: 'magento_prices_query.php',
                                  type: "POST",                                      
                                  data:postData,           
                                  success: function(response)
                                   {                                   
                                     $('#graphModal').find('.modal-body').html(response);
                                   }
                              });                  
               }//end selecthandler

            }
           
        }
    </script>
<?php
  } //end loop

mysql_close();
?> 

</body>
</html>
