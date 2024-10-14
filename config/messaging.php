<?php


return [
    'providers' => [
      
    ],
    'user_model' => App\Models\User::class,
    'date_format' => env('MESSAGING_DATE_FORMAT', config('app.date_format', 'd/m/Y')),

];
