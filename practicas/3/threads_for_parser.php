<?php

class Parser extends Volatile
{
    private $con_db;
    private $repos;
    private $stt;
    private $api;
    public static $commits = array();
    
    public function __construct($connection, $repos, $stt, $api_github)
    {
        $this->con_db = $connection;
        $this->repos = $repos;
        $this->stt = $stt;
        $this->api = $api_github;
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
                        /*
                         *$msg = new AMQPMessage(json_encode($info_commit));
                         *$channel->basic_publish($msg, '', 'github');
                         */
                    }
                }
            }
        }
    }
}
