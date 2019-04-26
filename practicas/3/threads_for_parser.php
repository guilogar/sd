<?php

require_once "conection_to_db.php";

class Cerrojo extends Threaded { }

class Commits extends Threaded
{
    public $commits;
    
    public function __construct()
    {
        $this->commits = (array) array();
    }
    
    public function add(array $commit)
    {
        array_push($this->commits, (array) $commit);
    }
}

class Parser extends Volatile
{
    private $repos;
    private $stt;
    private $api;
    private $commits;
    private $cerrojo;
    
    public function __construct($repos, $stt, $api_github, $commits, $cerrojo)
    {
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
                $query_existe = " select * from repositorios where full_name = '$full_name' ".
                                 " and branch_name = '$branch_name' and last_commit = '$last_commit'; ";
                
                $query_no_existe = " select * from repositorios where full_name = '$full_name' ".
                                    " and branch_name = '$branch_name' and last_commit != '$last_commit'; ";
                
                $res = $this->cerrojo->synchronized(function ($query_existe, $query_no_existe)
                {
                    $con = pg_connect("host=".HOST_DB." port=".PORT_DB." dbname=".NAME_DB." user=".USER_DB." password=".PASS_DB);
                    $existe_commit = pg_fetch_result(pg_query($con, $query_existe), 0);
                    $distintos     = pg_fetch_result(pg_query($con, $query_no_existe), 0);
                    pg_close($con);
                    
                    if($existe_commit)
                    {
                        return 1;
                    } else if($distintos)
                    {
                        return 2;
                    } else
                    {
                        return 3;
                    }
                }, $query_existe, $query_no_existe);

                if($res === 2)
                {
                    $this->cerrojo->synchronized(function ($last_commit, $full_name, $branch_name)
                    {
                        $con = pg_connect("host=".HOST_DB." port=".PORT_DB." dbname=".NAME_DB." user=".USER_DB." password=".PASS_DB);
                        pg_update($con, "repositorios",
                        array(
                            'last_commit' => $last_commit,
                        ), array(
                            'full_name' => $full_name,
                            'branch_name' => $branch_name,
                        ));
                        pg_close($con);
                    }, $last_commit, $full_name, $branch_name);
                } else if($res === 3)
                {
                    $this->cerrojo->synchronized(function ($last_commit, $full_name, $branch_name)
                    {
                        $con = pg_connect("host=".HOST_DB." port=".PORT_DB." dbname=".NAME_DB." user=".USER_DB." password=".PASS_DB);
                        pg_insert($con, "repositorios", array(
                            'full_name' => $full_name,
                            'branch_name' => $branch_name,
                            'last_commit' => $last_commit,
                        ));
                        pg_close($con);
                    }, $last_commit, $full_name, $branch_name);
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
                    
                    $this->cerrojo->synchronized(function ($commits, $info_commit) {
                        $commits->add((array) $info_commit);
                    }, $this->commits, $info_commit);
                }
            }
        }
    }
    
/*
 *    public function run()
 *    {
 *        $SEND_TO_TWITTER = $this->stt;
 *        $api = $this->api;
 *        $con = $this->con_db;
 *        $repos = $this->repos;
 *        $len_repos = sizeof($repos);
 *        $channel = $this->channel_mq;
 *        
 *        for($i = 0; $i < $len_repos; $i++)
 *        {
 *            $repo = $repos[$i];
 *            $name = $repo->name;
 *            $full_name = $repo->full_name;
 *            
 *            $branches = $api->decode($api->get("repos/$full_name/branches"));
 *            foreach($branches as $k => $b)
 *            {
 *                $branch_name = $b->name;
 *                $last_commit = $b->commit->sha;
 *                $query = "select * from repositorios where full_name = '$full_name' ".
 *                         " and branch_name = '$branch_name' and last_commit = '$last_commit'";
 *                
 *                $this->cerrojo->synchronized(function ($con, $query, $api, $full_name, $branch_name, $last_commit, $SEND_TO_TWITTER)
 *                {
 *                    $res = pg_query($con, $query);
 *                    $existe_commit = pg_fetch_result($res, 0);
 *                    
 *                    if(!$existe_commit)
 *                    {
 *                        $query = "select * from repositorios where full_name = '$full_name' ".
 *                                 " and branch_name = '$branch_name' and last_commit != '$last_commit'";
 *
 *                        $this->cerrojo->synchronized(function ($con, $query, $api, $full_name, $branch_name, $last_commit, $SEND_TO_TWITTER)
 *                        {
 *                            $res = pg_query($con, $query);
 *                            $fila = pg_fetch_result($res, 0);
 *                            
 *                            if($fila)
 *                            {
 *                                pg_update($con, "repositorios",
 *                                array(
 *                                    'last_commit' => $last_commit,
 *                                ), array(
 *                                    'full_name' => $full_name,
 *                                    'branch_name' => $branch_name,
 *                                ));
 *                            } else
 *                            {
 *                                pg_insert($con, "repositorios", array(
 *                                    'full_name' => $full_name,
 *                                    'branch_name' => $branch_name,
 *                                    'last_commit' => $last_commit,
 *                                ));
 *                            }
 *
 *                            if(isset($SEND_TO_TWITTER) && $SEND_TO_TWITTER)
 *                            {
 *                                $commit = $api->decode($api->get("repos/$full_name/commits/$last_commit"));
 *                                
 *                                $info_commit = array(
 *                                    'repo_name' => $full_name,
 *                                    'branch_name' => $branch_name,
 *                                    'commiter' => $commit->committer,
 *                                    'date' => $commit->commit->committer->date,
 *                                    'html_url' => $commit->html_url,
 *                                    'files' => $commit->files
 *                                );
 *                                
 *                                $this->cerrojo->synchronized(function ($commits, $info_commit) {
 *                                    $commits->add((array) $info_commit);
 *                                }, $this->commits, $info_commit);
 *                            }
 *                        }, $con, $query, $api, $full_name, $branch_name, $last_commit, $SEND_TO_TWITTER);
 *                    }
 *                }, $con, $query, $api, $full_name, $branch_name, $last_commit, $SEND_TO_TWITTER);
 *            }
 *        }
 *    }
 */
}
