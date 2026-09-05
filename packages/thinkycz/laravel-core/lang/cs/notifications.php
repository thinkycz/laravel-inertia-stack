<?php

declare(strict_types=1);

return [
    'password_init' => [
        'subject' => 'Požadavek na nastavení hesla',
        'line1' => 'Tato zpráva vám byla doručena na základě žádosti pro nastavení hesla.',
        'action' => 'Nastavit heslo',
        'line2' => 'Tento odkaz na nastavení hesla vyprší za :count minut.',
        'line3' => 'Pokud jste nežádali o nastavení hesla, zprávu smažte. Původní heslo zůstalo beze změny.',
    ],
    'password_reset' => [
        'subject' => 'Požadavek na obnovení hesla',
        'line1' => 'Obdrželi jste tento e-mail, protože jsme obdrželi žádost o obnovení hesla k vašemu účtu.',
        'action' => 'Obnovit heslo',
        'line2' => 'Tento odkaz pro obnovení hesla vyprší za :count minut.',
        'line3' => 'Pokud jste o obnovení hesla nežádali, není třeba nic dělat.',
    ],
];
