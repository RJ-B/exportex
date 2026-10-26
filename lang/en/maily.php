<?php

return [
    'logging_transport' => [
        'neodeslano_na_produkci_se_e_maily' => 'Not sent: in production, e-mails are only written to the log (log mailer). Connect the application to Pošta.',
        'neznamy' => '(unknown)',
    ],
    'nova_zprava' => [
        'nova_zprava_z_webu' => 'New message from the website :get: :cele_jmeno',
        'nova_zprava_z_kontaktniho_formulare_na' => 'New message from the contact form on the website :get.',
        'od' => 'From: :cele_jmeno <:email>',
        'telefon' => 'Phone: :telefon',
        'odeslano' => 'Sent: :format',
        'odpovedet_muzete_primo_na_tento_e' => '---
You can reply directly to this e-mail. All messages are also in the administration (Website messages).',
    ],
    'oznameni_mail' => [
        'zkouska' => '[Test] ',
    ],
    'oznameni_text' => [
        'zkouska_takhle_oznameni_uvidi_prijemci' => '[Test – this is how recipients will see the notification. It was not sent to anyone else.]',
        'kdy' => 'When: :format',
        'dostavate_to_protoze_jste_souhlasili_se' => 'You are receiving this because you agreed to receive news from :nazev_webu.
Unsubscribe: :odhlaseni',
        'dostavate_to_protoze_mate_ucet_na' => 'You are receiving this because you have an account at :nazev_webu.',
        'predvolby_oznameni' => 'Notification preferences: :predvolby',
    ],
    'oznameni' => [
        'zkouska_takhle_oznameni_uvidi_prijemci' => 'Test – this is how recipients will see the notification. It was not sent to anyone else.',
        'kdy' => 'When:',
        'nabidek' => 'offers',
        'odhlasit_jednim_kliknutim' => 'Unsubscribe with one click',
        'predvolby_oznameni' => 'Notification preferences',
        'dostavate_to_protoze_mate_ucet_na' => 'You are receiving this because you have an account at :nazev_webu.',
        'co_vam_chodi_e_mailem_nastavite' => 'You can set what you receive by e-mail in the',
        'predvolbach_oznameni' => 'notification preferences',
        'dostavate_to_protoze_jste_souhlasili_se' => 'You are receiving this because you agreed to receive :oznameni from :nazev_webu.',
    ],
];
