<?php
// Copiez ce fichier en config.php (ou laissez install.php le générer).
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'menu',
        'user'    => 'menu',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'              => 'Kitcho',
        // Inscription ouverte à tous ? Sinon, seules les invitations permettent de créer un compte.
        'allow_registration' => true,
        // Affiche les erreurs PHP détaillées (désactiver en production).
        'debug'             => false,
        'timezone'          => 'Europe/Brussels',
    ],
];
