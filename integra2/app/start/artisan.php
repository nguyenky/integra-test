<?php

/*
|--------------------------------------------------------------------------
| Register The Artisan Commands
|--------------------------------------------------------------------------
|
| Each available Artisan command must be registered with the console so
| that it is available to be called. We'll register every command so
| the console gets access to each of the command object instances.
|
*/

Artisan::add(new UpdateEbayInventory);
Artisan::add(new UpdateAmazonInventory);
Artisan::add(new EsiUpdateTargets);
Artisan::add(new EsiUpdateProducts);
Artisan::add(new EsiScrape);
Artisan::add(new EbayScrape);
Artisan::add(new EbayScrapeInventory);
Artisan::add(new ImcScrapeInvoices);
Artisan::add(new SsfScrapeInvoices);
Artisan::add(new ImcScrapeReturns);
Artisan::add(new SsfScrapeReturns);
Artisan::add(new SsfScrapeRa);
Artisan::add(new SsfScrapeCompatibility);
Artisan::add(new ImcLoadAces);
Artisan::add(new AmazonListProducts);
Artisan::add(new ImcCreateProducts);
Artisan::add(new KitHunter);
Artisan::add(new KitLister);
Artisan::add(new ImcScrapeApplications);
Artisan::add(new NoteSplit);
Artisan::add(new NoteEngine);
Artisan::add(new KitHybrid);
Artisan::add(new ImcExportScrape);
Artisan::add(new ImcExportOrder);
Artisan::add(new MageUrl);
Artisan::add(new GoogleCompat);
Artisan::add(new GoogleClear);
Artisan::add(new AmazonSqs);
Artisan::add(new AmazonRepricer);
Artisan::add(new ReportsSkuWeekly);
Artisan::add(new KitsUpdating);
Artisan::add(new EbayMonitorMigration);
Artisan::add(new AmazonSubscription);
Artisan::add(new AmazonDestinationRegister);
Artisan::add(new AmazonDeregisterDestination);
Artisan::add(new AmazonListRegisterDestination);
Artisan::add(new AmazonUpdateSubscription);
Artisan::add(new AmazonUpdateSubscription);
/*------- new -------*/
Artisan::add(new EbayMonitor);
Artisan::add(new EbayMonitorDESC);
Artisan::add(new EbayQuickLookup);


