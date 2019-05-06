<?php // spooling_github.php

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
set_error_handler("exception_error_handler");

/*
 *function fatal_handler()
 *{
 *    $errfile = "unknown file";
 *    $errstr  = "shutdown";
 *    $errno   = E_CORE_ERROR;
 *    $errline = 0;
 *    
 *    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
 *}
 *register_shutdown_function( "fatal_handler" );
 */

ini_set("memory_limit", -1);

require_once "vendor/autoload.php";
require_once "utils_php/conection_to_db.php";

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try
{
    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
} catch(Exception $e)
{
    die($e->getMessage());
}
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
$en_decode = array();
$page = 1;
$per_page = 100;
$string_request = "/user/repos?per_page=$per_page&page=";

if($user_gh !== NULL)
{
    $string_request = "/users/$user_gh/repos?per_page=$per_page&page=";
}

do
{
    try
    {
        $en = $api->get($string_request . $page++);
        $en_decode = $api->decode($en);
    } catch(Exception $e)
    {
        die($e->getMessage());
    }
    $repos = array_merge($repos, $en_decode);
} while($en_decode);

$len_repos = sizeof($repos);

try
{
    $con = @pg_connect("host=".HOST_DB." port=".PORT_DB." dbname=".NAME_DB." user=".USER_DB." password=".PASS_DB);
} catch(Exception $e)
{
    die($e->getMessage());
}

while(true)
{
    echo "Proceso de spooling iniciado. Use Ctrl + c para pararlo.\n";
    if(class_exists('Thread'))
    {
        require_once "utils_php/threads_for_parser.php";
        require_once "utils_php/utils_for_concurrency.php";

        $cb = 0.3;
        $num_cores = num_system_cores();
        $tam_pool = (int) ($num_cores / (1 - $cb));
        
        $min = 0;
        $ventana = (int) ($len_repos / $num_cores);
        $max = $ventana;
        
        $threads = array();
        $pool = new Pool($tam_pool);
        $c = new Commits();
        $cerrojo = new Cerrojo();
        
        for($i = 0; $i < $tam_pool; $i++)
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
        
        foreach ($c->data as $commit)
        {
            $msg = new AMQPMessage(json_encode($commit));
            $channel->basic_publish($msg, '', 'github');
        }
    } else
    {
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
    echo ".................................................\n";
}

pg_close($con);

$channel->close();
$connection->close();

session_destroy();
