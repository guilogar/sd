<?php

class Cerrojo extends Threaded
{
    
}

class Commits extends Threaded
{
    public $commits;
    
    public function __construct()
    {
        $this->commits = array();
    }
    
    public function add(array $commit)
    {
        array_push($this->commits, $commit);
    }
}

class Parser extends Volatile
{
    private $con_db;
    private $repos;
    private $stt;
    private $api;
    private $commits;
    private $cerrojo;
    
    public function __construct($connection, $repos, $stt, $api_github, $commits, $cerrojo)
    {
        $this->con_db = $connection;
        $this->repos = $repos;
        $this->stt = $stt;
        $this->api = $api_github;
        $this->commits = $commits;
        $this->cerrojo = $cerrojo;
    }
    
    public function run()
    {
        $SEND_TO_TWITTER = $this->stt;
        $api = $this->api;
        $con = $this->con_db;
        $repos = $this->repos;
        $len_repos = sizeof($repos);
        $channel = $this->channel_mq;
        
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
                
                $this->cerrojo->synchronized(function ($con, $query, $full_name, $branch_name, $last_commit)
                {
                    $res = pg_query($con, $query);
                    $existe_commit = pg_fetch_result($res, 0);
                    
                    if(!$existe_commit)
                    {
                        $query = "select * from repositorios where full_name = '$full_name' ".
                                 " and branch_name = '$branch_name' and last_commit != '$last_commit'";

                        $this->cerrojo->synchronized(function ($con, $query, $full_name, $branch_name, $last_commit)
                        {
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
                                
                                var_dump($info_commit);
                                $this->cerrojo->synchronized(function ($commits, $info_commit) {
                                    $commits->add($info_commit);
                                }, $this->commits, $info_commit);
                                /*
                                 *$msg = new AMQPMessage(json_encode($info_commit));
                                 *$channel->basic_publish($msg, '', 'github');
                                 */
                            }
                        }, $con, $query, $full_name, $branch_name, $last_commit);
                    }
                }, $con, $query, $full_name, $branch_name, $last_commit);
            }
        }
    }
}
