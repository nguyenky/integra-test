<?php
    
    define("ENV_PRODUCTION", 'production');
    define("ENV_SANDBOX", 'sandbox');
    
    function getBulkDataExchangeServiceEndpoint($environment)
    {
	    if ( $environment == ENV_PRODUCTION ) {
	        $endpoint = 'https://webservices.ebay.com/BulkDataExchangeService';    
	    }
	    elseif ( $environment == ENV_SANDBOX ) {  
	    	$endpoint = 'https://webservices.sandbox.ebay.com/BulkDataExchangeService';              
	    }
	    else {
	    	die("Invalid Environment: $environment");  
	    }
	    
	    return $endpoint;
    }
    
    function getFileTransferServiceEndpoint($environment)
    {
    	if ( $environment == ENV_PRODUCTION ) {
	        $endpoint = 'https://storage.ebay.com/FileTransferService';    
	    }
	    elseif ( $environment == ENV_SANDBOX ) {  
	    	$endpoint = 'https://storage.sandbox.ebay.com/FileTransferService';              
	    }
	    else {
	    	die("Invalid Environment: $environment");    
	    }
	    
	    return $endpoint;
    }
    
    function getSecurityToken($environment)
    {
	    if ( $environment === ENV_PRODUCTION ) {
	        $securityToken = 
'AgAAAA**AQAAAA**aAAAAA**zJZMWg**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6wCloGgCpaEqQidj6x9nY+seQ**NosBAA**AAMAAA**7+MiDKWT7B1VlFXExufh4Eedx5WzkgWlmTjSN5YaxKXihyfE0dUkla6bpc47+xZH5YzR+E04ZCyinwnCH4iOYXDpgZMnovv34x13OzalirA7MMwwjlx9qT2+3l0m42I9t6j+ZdEhWURMA/47/kbgt5k6baA5cXn4Syy7kiDyzdjLORubXP49K9ip59kJIZ5b8J4DnWslV8fSkkR2DkfiZ6+WlvLBxxv1KuVB59TZvbOARoRugOZAgG0iJHc+2faJhtHVt0m9JR+TmOvoMT6a5y9Mf2W4+shmeK5ena63rZ4p7aeMY0YLYHy1xVkphWTmI8j5qRJEsYIVmOLMCAedYbMKejFKHZOJqNjLtoBd4egz4P0mZkveGmF6PeYAvr/w+V+1fSPpdnU6KMUhiNGTrtWgi/dRBdylC+XhS/OwvbMdYtZXg1eaIbR6eVANcRlQuW93+Wm5YNtqUIIfD5ej6JEoVk8EOMijzq/DUT2Q1Op8sLNvpZWMvSiOeP+ccpxsWdh9aIzRq73N3X8Z3o4T7uudbnaDzeu3QZqzCYWUf0r+wK1uab8WP36NcsvAEafJyacnrpdgRvPLLkFv5A+CQWtb9HddciIaXoCsKMv6RwnrxQrvkdpndCYdgasljB355enjXJDnjP3fwAFMO3vCnbPsdJ44jk6g2oaRkcPsFOA36f4Ddbd9cPJRgdzveZhsXdEphMDmJA5fJ6TX68uFkT0964l6C1IXP+xRN02cioYcrEQlt/n5xwDY3junWBxU';
	    }
	    elseif ( $environment === ENV_SANDBOX ) {  
	        $securityToken = 
'AgAAAA**AQAAAA**aAAAAA**fsNuWA**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6wCloGgCpaEqQidj6x9nY+seQ**NosBAA**AAMAAA**7+MiDKWT7B1VlFXExufh4Eedx5WzkgWlmTjSN5YaxKXihyfE0dUkla6bpc47+xZH5YzR+E04ZCyinwnCH4iOYXDpgZMnovv34x13OzalirA7MMwwjlx9qT2+3l0m42I9t6j+ZdEhWURMA/47/kbgt5k6baA5cXn4Syy7kiDyzdjLORubXP49K9ip59kJIZ5b8J4DnWslV8fSkkR2DkfiZ6+WlvLBxxv1KuVB59TZvbOARoRugOZAgG0iJHc+2faJhtHVt0m9JR+TmOvoMT6a5y9Mf2W4+shmeK5ena63rZ4p7aeMY0YLYHy1xVkphWTmI8j5qRJEsYIVmOLMCAedYbMKejFKHZOJqNjLtoBd4egz4P0mZkveGmF6PeYAvr/w+V+1fSPpdnU6KMUhiNGTrtWgi/dRBdylC+XhS/OwvbMdYtZXg1eaIbR6eVANcRlQuW93+Wm5YNtqUIIfD5ej6JEoVk8EOMijzq/DUT2Q1Op8sLNvpZWMvSiOeP+ccpxsWdh9aIzRq73N3X8Z3o4T7uudbnaDzeu3QZqzCYWUf0r+wK1uab8WP36NcsvAEafJyacnrpdgRvPLLkFv5A+CQWtb9HddciIaXoCsKMv6RwnrxQrvkdpndCYdgasljB355enjXJDnjP3fwAFMO3vCnbPsdJ44jk6g2oaRkcPsFOA36f4Ddbd9cPJRgdzveZhsXdEphMDmJA5fJ6TX68uFkT0964l6C1IXP+xRN02cioYcrEQlt/n5xwDY3junWBxU';
	    }
	    else {
	    	die("Invalid Environment: $environment");   
	    }
	    
	    return $securityToken;
    }

?>
