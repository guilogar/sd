<?php

require_once "vendor/autoload.php";

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('github', false, false, false, false);

use Milo\Github\Api;
use Milo\Github\OAuth\Configuration;
use Milo\Github\OAuth\Token;
use Milo\Github\OAuth\Login;
use Milo\Github\Storages\SessionStorage;

//$SEND_TO_TWITTER = FALSE;
$SEND_TO_TWITTER = TRUE;

//$appUrl = "https://guilogar.github.io";
$appUrl = "http://localhost/universidad/sd/practicas/3/spooling_github.php";

session_start();

$config = new Configuration(
    getenv("GITHUB_CLIENT_ID"),
    getenv("GITHUB_CLIENT_SECRET_ID"),
    ['user', 'repo']
);
$storage = new SessionStorage;
$login = new Login($config, $storage);
$token = NULL;

if ($login->hasToken())
{
    $token = $login->getToken();
} else
{
    if (isset($_GET['back']))
    {
        $token = $login->obtainToken(
            $_GET['code'],
            $_GET['state']
        );
    } else
    {
        # Performs redirect to Github page
        $login->askPermissions("$appUrl?back=1");
    }
}

$api = new Api;
$api->setToken($token);

$en = $api->get('/user/repos');
$repos = $api->decode($en);
$len_repos = sizeof($repos);

$host = "localhost";
$port = "5432";
$dbname = "sd";
$userdb = "usuario";
$passdb = "usuario";
$con = pg_connect("host=$host port=$port dbname=$dbname user=$userdb password=$passdb");

for($i = 0; $i < $len_repos; $i++)
{
    $repo = $repos[$i];
    $name = $repo->name;
    $full_name = $repo->full_name;
    
    $branches = $api->decode($api->get("repos/$full_name/branches"));
    foreach($branches as $k => $b)
    {
        $branch_name = $b->name;
        $last_commit = $b->commit->sha;
        $query = "select * from repositorios where full_name = '$full_name' ".
                 " and branch_name = '$branch_name' and last_commit != '$last_commit'";
        
        $res = pg_query($con, $query);
        $fila = pg_fetch_result($res, 0);
        
        if($fila)
        {
            pg_update($con, "repositorios",
            array(
                'last_commit' => $last_commit,
            ), array(
                'full_name' => $full_name,
                'branch_name' => $branch_name,
            ));
        } else
        {
            pg_insert($con, "repositorios", array(
                'full_name' => $full_name,
                'branch_name' => $branch_name,
                'last_commit' => $last_commit,
            ));
        }

        if(isset($SEND_TO_TWITTER) && $SEND_TO_TWITTER)
        {
            $commit = $api->decode($api->get("repos/$full_name/commits/$last_commit"));
            
            $info_commit = array(
                'repo_name' => $full_name,
                'branch_name' => $branch_name,
                'commiter' => $commit->committer,
                'date' => $commit->commit->committer->date,
                'html_url' => $commit->html_url,
                'files' => $commit->files
            );
            $msg = new AMQPMessage(json_encode($info_commit));
            $channel->basic_publish($msg, '', 'github');
        }
    }
}

pg_close($con);

$channel->close();
$connection->close();

