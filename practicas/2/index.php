<?php

// La version de php usada es la 7.2
// y la version de apache es la 2.4

if(isset($_GET['save_to_db']))
{
    $host = "localhost";
    $port = "5432";
    $dbname = "sd";
    $userdb = ""; // "usuario";
    $passdb = ""; // "usuario";
    $con = pg_connect("host=$host port=$port dbname=$dbname user=$userdb password=$passdb");
    pg_insert($con, "conexiones", array(
        'email' => $_GET['save_to_db']
    ));
    pg_close($con);
} else
{
    echo json_encode(
        array(
            'info' => array(
                'fromEmail'        => '', // 'torvalds.es.dios@gmail.com',
                'email'            => '', // 'hazme.tuyo.torvalds@gmail.com',
                'claveAplicacion'  => '', // 'me gusta el linux, un poco',
                'method'           => 'db'
            )
        )
    );
}
