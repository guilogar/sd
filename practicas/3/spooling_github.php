<?php // spooling_github.php

require_once "vendor/autoload.php";
require_once "utils_php/conection_to_db.php";

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

$user_gh = NULL;
if(!isset($argv[1]) || $argv[1] === NULL)
{
    if(isset($_SERVER['SERVER_SOFTWARE']) && $_SERVER['SERVER_SOFTWARE'] !== NULL && isset($_GET['user_gh']))
    {
        $user_gh = $_GET['user_gh'];
    }
} else
{
    $user_gh = $argv[1];
}

session_start();

$SEND_TO_TWITTER = TRUE;

$config = new Configuration(
    getenv('GITHUB_CLIENT_ID'),
    getenv('GITHUB_CLIENT_SECRET_ID'),
    ['user', 'repo']
);

$storage = new SessionStorage;
$login = new Login($config, $storage);

$code = getenv("GITHUB_ACCESS_TOKEN");
$state = sha1(uniqid(microtime(TRUE), TRUE));
$storage->set("auth.state", $state);
$token = new Token($code, $state);
$api = new Api;
$api->setToken($token);

$repos = array();
$en = NULL;
if($user_gh !== NULL)
{
    $en = $api->get("/users/$user_gh/repos");
} else
{
    $en = $api->get("/user/repos");
}
$repos = $api->decode($en);

$len_repos = sizeof($repos);

if(class_exists('Thread'))
{
    require_once "utils_php/threads_for_parser.php";
    require_once "utils_php/utils_for_concurrency.php";
    
    $num_cores = num_system_cores();
    $min = 0;
    $ventana = (int) ($len_repos / $num_cores);
    $max = $ventana;
    
    $pool = new Pool($num_cores);
    $threads = array();
    $c = new Commits();
    $cerrojo = new Cerrojo();
    
    for($i = 0; $i < $num_cores; $i++)
    {
        $rr = array_slice($repos, $min, $max);
        $p = new Parser($rr, $SEND_TO_TWITTER, $api, $c, $cerrojo);
        array_push($threads, $p);
        $pool->submit($p);
        
        $min = $max + 1;
        $max += $ventana;
    }
    
    if($max < $len_repos - 1)
    {
        $rr = array_slice($repos, $min, $len_repos - 1);
        $p = new Parser($rr, $SEND_TO_TWITTER, $api, $c, $cerrojo);
        array_push($threads, $p);
        $pool->submit($p);
    }

    while($pool->collect());
    
    $pool->shutdown();
    
    //var_dump($c->commits);
    //echo sizeof($c->commits) . "\n";
    foreach ($c->commits as $commit)
    {
        $msg = new AMQPMessage(json_encode($commit));
        $channel->basic_publish($msg, '', 'github');
    }
} else
{
    $con = pg_connect("host=".HOST_DB." port=".PORT_DB." dbname=".NAME_DB." user=".USER_DB." password=".PASS_DB);
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
                     " and branch_name = '$branch_name' and last_commit = '$last_commit'";
            
            $res = pg_query($con, $query);
            $existe_commit = pg_fetch_result($res, 0);
            
            if(!$existe_commit)
            {
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
    }
}

/*
 *pg_close($con);
 *
 *$channel->close();
 *$connection->close();
 */

//session_destroy();
