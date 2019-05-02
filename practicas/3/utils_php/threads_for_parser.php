<?php // utils_php/threads_for_parser.php

require_once "conection_to_db.php";

class Cerrojo extends Threaded { }

class Commits extends Threaded
{
    public $data;
    
    public function __construct()
    {
        $this->data = [];
    }
    
    public function add(array $commit)
    {
        $this->data[sizeof($this->data)] = (object) $commit;
    }
}

class Parser extends Threaded
{
    private $repos;
    private $stt;
    private $api;
    private $commits;
    private $cerrojo;
    
    public function __construct(array $repos, $stt, $api_github, Commits $commits, Cerrojo $cerrojo)
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
        $branches = array();
        
        for($i = 0; $i < $len_repos; $i++)
        {
            $repo = $repos[$i];
            $name = $repo->name;
            $full_name = $repo->full_name;
            
            // Se provee sincronizacion
            $branches_repo = $this->cerrojo->synchronized(function ($api, $full_name)
            {
                try
                {
                    return $api->decode($api->get("repos/$full_name/branches"));
                } catch(Exception $e)
                {
                    die($e->getMessage());
                }
            }, $api, $full_name);
            
            foreach($branches_repo as $k => $b)
            {
                $b->full_name = $full_name;
                $branches_repo[$k] = $b;
            }
            
            $branches = array_merge($branches, $branches_repo);
        }
        
        printf("%s #%lu avanza del analisis de ramas.\n", __CLASS__, Thread::getCurrentThreadId());
        // Se analiza cada rama por si ha habido cambios
        foreach($branches as $k => $b)
        {
            $full_name   = $b->full_name;
            $branch_name = $b->name;
            $last_commit = $b->commit->sha;
            
            $query_existe = " select * from repositorios where full_name = '$full_name' ".
                             " and branch_name = '$branch_name' and last_commit = '$last_commit'; ";
            
            $query_no_existe = " select * from repositorios where full_name = '$full_name' ".
                                " and branch_name = '$branch_name' and last_commit != '$last_commit'; ";
            
            $res = NULL;
            $con = pg_connect("host=".HOST_DB." port=".PORT_DB." dbname=".NAME_DB." user=".USER_DB." password=".PASS_DB);
            $existe_commit = pg_fetch_result(pg_query($con, $query_existe), 0);
            $distintos     = pg_fetch_result(pg_query($con, $query_no_existe), 0);
            pg_close($con);
            
            if($existe_commit)  $res = 1;
            else if($distintos) $res = 2;
            else                $res = 3;
            
            if($res === 2)
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
            } else if($res === 3)
            {
                $con = pg_connect("host=".HOST_DB." port=".PORT_DB." dbname=".NAME_DB." user=".USER_DB." password=".PASS_DB);
                pg_insert($con, "repositorios", array(
                    'full_name' => $full_name,
                    'branch_name' => $branch_name,
                    'last_commit' => $last_commit,
                ));
                pg_close($con);
            }
            printf("%s #%lu empieza a hacer su trabajo.\n", __CLASS__, Thread::getCurrentThreadId());
            
            if($res !== 1 && isset($SEND_TO_TWITTER) && $SEND_TO_TWITTER)
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
