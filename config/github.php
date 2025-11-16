<?php
declare(strict_types=1);

return [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'scope' => 'repo delete_repo user',
];