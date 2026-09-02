<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'timeout' => 120,
    'models' => [
        'low' => ['model' => 'gpt-5.6-luna', 'effort' => 'low', 'input_price' => 200000, 'output_price' => 1200000],
        'medium' => ['model' => 'gpt-5.6-terra', 'effort' => 'medium', 'input_price' => 2000000, 'output_price' => 12000000],
        'high' => ['model' => 'gpt-5.6-sol', 'effort' => 'high', 'input_price' => 4000000, 'output_price' => 20000000],
    ],
];
