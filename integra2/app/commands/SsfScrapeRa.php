<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class SsfScrapeRa extends Command
{
    protected $name = 'ssf:scrape_ra';
    protected $description = 'Scrapes the RA sent by account manager via email.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $config = Config::get('integra');
        $host = $config['ssf_request_rma']['email.host'];
        $username = $config['ssf_request_rma']['email.username'];
        $password = $config['ssf_request_rma']['email.password'];
        $port = $config['ssf_request_rma']['imap.port'];
        $mailbox = $config['ssf_request_rma']['imap.mailbox'];
        $fromEmail = $config['ssf_request_rma']['recipient.email'];

        $imap = imap_open("{{$host}:{$port}/ssl/novalidate-cert}{$mailbox}", $username, $password);
        $fromDate = date('d M Y', strtotime('-30 day'));
        $emails = imap_search($imap, 'FROM "' . $fromEmail . '" BODY "REF" SINCE "' . $fromDate . '"');

        if (!empty($emails))
        {
            $supplierId = Supplier::where('name', 'SSF')->pluck('id');

            foreach ($emails as $email_number)
            {
                $header = imap_headerinfo($imap, $email_number);
                $subject = $header->subject;

                $this->info("Scraping email - {$subject}");

                $structure = imap_fetchstructure($imap, $email_number);

                if (isset($structure->parts) && is_array($structure->parts) && isset($structure->parts[0]))
                {
                    $part = $structure->parts[0];
                    $message = imap_fetchbody($imap, $email_number, 1);

                    if ($part->encoding == 3)
                        $message = imap_base64($message);
                    else if($part->encoding == 1)
                        $message = imap_8bit($message);
                    else $message = imap_qprint($message);
                }

                preg_match("/REF:(?<ref>\\d+)/", $message, $results);
                if (!isset($results['ref']))
                {
                    preg_match("/REF:(?<ref>\\d+)/", $subject, $results);
                    if (!isset($results['ref']))
                    {
                        $this->info("Email does not have the REF line. Setting to unread...");
                        imap_clearflag_full($imap, $email_number, '\\Seen');
                        continue;
                    }
                }

                $returnId = $results['ref'];

                preg_match("/(?<ra>\\d{8})/", $message, $results);
                if (!isset($results['ra']))
                {
                    $this->info("Email does not have the RA number. Setting to unread...");
                    imap_clearflag_full($imap, $email_number, '\\Seen');
                    continue;
                }

                $return = SupplierReturn::whereNull('return_num')->where('supplier_id', $supplierId)->where('id', $returnId)->first();
                if (empty($return))
                {
                    $this->info("Unable to find a return record with no RA in the database");
                    continue;
                }

                $return->return_num = $results['ra'];
                $return->status = 'Processing';
                $return->save();

                $this->info("RA " . $results['ra'] . " recorded for return ID {$returnId}");
            }
        }
        else
        {
            $this->info("No matching emails");
        }

        imap_close($imap, CL_EXPUNGE);
    }
}
