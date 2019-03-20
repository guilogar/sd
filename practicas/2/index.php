<?php

// La version de php usada es la 7.2
// y la version de apache es la 2.4

if(isset($_GET['save_to_db']))
{
    $con = pg_connect("host=localhost port=5432 dbname=sd user=usuario password=usuario");
    pg_insert($con, "conexiones", array(
        'email' => $_GET['save_to_db']
    ));
    pg_close($con);
} else
{
    echo json_encode(
        array(
            'info' => array(
                'fromEmail' => 'jdkdejava@gmail.com',
                'email' => 'guillermolopezgarcia96@gmail.com',
                'claveAplicacion' => 'htxjlkjoemmtakie',
                'method' => 'db'
            )
        )
    );
}
