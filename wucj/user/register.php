<?php

namespace wucj\user;

class register
{
    public function __construct()
    {
        echo "register";
        (new login)->main();
    }
}

// $register = new register();
// echo $register->register();