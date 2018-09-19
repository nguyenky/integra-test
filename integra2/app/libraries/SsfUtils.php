<?php

use \Carbon\Carbon;

class SsfUtils
{
    public static function RequestRaByEmail($items)
    {
        if (empty($items)) return null;

        $config = Config::get('integra');
        $host = $config['ssf_request_rma']['email.host'];
        $username = $config['ssf_request_rma']['email.username'];
        $password = $config['ssf_request_rma']['email.password'];
        $port = $config['ssf_request_rma']['smtp.port'];
        $senderEmail = $config['ssf_request_rma']['sender.email'];
        $senderName = $config['ssf_request_rma']['sender.name'];
        $recipientEmail = $config['ssf_request_rma']['recipient.email'];
        $recipientName = $config['ssf_request_rma']['recipient.name'];

        $return = new SupplierReturn();
        $return->supplier_id = Supplier::where('name', 'SSF')->pluck('id');
        $return->return_num = null;
        $return->return_date = Carbon::now();
        $return->status = 'Pending RA';
        $return->credit_num = '';
        $return->credit_date = null;
        $return->total_credited = null;
        $return->save();

        $transport = Swift_SmtpTransport::newInstance($host, $port, 'ssl')
            ->setUsername($username)
            ->setPassword($password);

        $mailer = Swift_Mailer::newInstance($transport);

        $data =
            [
                'sender_name' => $senderName,
                'recipient_name' => $recipientName,
                'return_id' => $return->id,
                'items' => $items
            ];

        $body = View::make('emails.ssf_request_rma', $data)->render();

        $message = Swift_Message::newInstance('Request for RA (REF:' . $return->id . ')')
            ->setFrom(array($senderEmail => $senderName))
            ->setTo(array($recipientEmail))
            ->setBody($body, 'text/html');

        try
        {
            $mailer->send($message);

            foreach ($items as $item)
            {
                $ri = new SupplierReturnItem();
                $ri->supplier_return_id = $return->id;
                $ri->invoice_num = $item['invoice_num'];
                $ri->sku = $item['sku'];
                $ri->reason = $item['reason'];
                $ri->quantity_requested = $item['quantity'];
                $ri->quantity_credited = null;
                $ri->unit_price_credited = null;
                $ri->save();
            }

            return $return;
        }
        catch (Exception $e)
        {
            $return->delete();
            return null;
        }
    }
} 