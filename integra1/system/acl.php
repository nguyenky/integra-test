<?php

require_once('config.php');

function Login($page = null)
{
	session_start();
	
	if (isset($_SESSION['user']))
		return Authorize($page);
    else if ($_SERVER['SERVER_NAME'] == 'integra.eocenterprise.dev')
    {
        $_SESSION['user'] = 'server@eocenterprise.com';
        return Authorize($page);
    }
	else
    {
        $_SESSION['redirect'] = $_SERVER['REQUEST_URI'];
        header("Location: /login.php");
        exit();
    }
}

function Authorize($page = null)
{
	if (empty($page))
		$page = basename($_SERVER['PHP_SELF'], '.php');

	try
	{
		if (empty($_SESSION['user']))
		{
			DenyAccess();
			exit;
		}
		
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(DB_SCHEMA);

		$res = mysql_fetch_row(mysql_query(sprintf("SELECT u.restrict_ip FROM acl a, integra_users u WHERE a.page = '%s' AND a.email = '%s' AND a.email = u.email",
			mysql_real_escape_string($page),
			mysql_real_escape_string($_SESSION['user']))));

		mysql_close();
	
		if (!empty($res))
        {
            if (empty($res[0]))
                return $_SESSION['user'];
            else if ($res[0] == $_SERVER['REMOTE_ADDR'])
                return $_SESSION['user'];
            else
            {
                DenyAccess();
                exit;
            }
        }

		DenyAccess();
		exit;
	}
	catch (Exception $e)
	{
		error_log($e->getMessage());
		SendAdminEmail("ACL exception for $page, " . $_SESSION['user'], $e->getMessage(), false);
		echo "There was a security error. Please contact your administrator.";
		exit;
	}
}

function DenyAccess()
{
	echo <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Access Denied</title>
<style>
	body { font-family: tahoma, verdana; }
	.warning { color: red; }
</style>
</head>
<body>
<p class="warning"><b>Either you do not have access to this page or your current IP address is unauthorized. Please contact your administrator.</b></p>
<p>Alternatively, you can <a href="logout.php">logout</a> and login with another account that has access.</p>
</body>
</html>
EOD;
}