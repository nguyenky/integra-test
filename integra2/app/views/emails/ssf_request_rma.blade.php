<!DOCTYPE html>
<html lang="en-US">
	<head>
		<meta charset="utf-8">
	</head>
	<body>
	    <p>Hi {{ $recipient_name }},</p><br/>
	    <p>We would like return the following orders:</p><br/>
	    <table border="1" cellspacing="0" cellpadding="5">
            <tr>
                <td align="center"><b>Invoice Number</b></td>
                <td align="center"><b>SKU</b></td>
                <td align="center"><b>Quantity</b></td>
                <td align="center"><b>Reason</b></td>
            </tr>
        @foreach ($items as $item)
            <tr>
                <td align="center">{{$item['invoice_num']}}</td>
                <td align="center">{{$item['sku']}}</td>
                <td align="center">{{$item['quantity']}}</td>
                <td>{{$item['reason']}}</td>
            </tr>
        @endforeach
	    </table>
	    <br/><p>Please send us RA. Thanks!</p>
	    <p> {{ $sender_name }}</p><br/>
	    <p>REF:{{$return_id}} (please include this line unmodified in your reply)</p>
	</body>
</html>
