<?php

if(isset($_GET['save_to_db']))
{
    
} else
{
    echo json_encode(
        array(
            'info' => array(
                'email' => 'guillermolopezgarcia96@gmail.com',
                'method' => 'mail'
            )
        )
    );
}
