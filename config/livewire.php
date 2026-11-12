<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Make Command
    |---------------------------------------------------------------------------
    | Component files in this project are named without the high-voltage (⚡)
    | prefix, so `php artisan make:livewire` is configured to match. All other
    | Livewire options use the package defaults.
    |
    */

    'make_command' => [
        'type' => 'sfc', // Options: 'sfc', 'mfc', 'class'
        'emoji' => false, // Options: true, false
        'with' => [
            'js' => false,
            'css' => false,
            'test' => false,
        ],
    ],

];
