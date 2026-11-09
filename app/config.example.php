<?php
// Reference only. deploy/install.sh creates app/config.php interactively with mode 0600.
return [
 'base_url'=>'https://pina.market',
 'public_dir'=>'/absolute/path/to/domain/document-root',
 'db'=>['host'=>'localhost','port'=>3306,'name'=>'','user'=>'','password'=>''],
 'smtp'=>['host'=>'','port'=>465,'security'=>'ssl','user'=>'','password'=>'','from'=>'mail@pina.market','from_name'=>'Pina.market'],
 'cash_address'=>'', 'cash_enabled'=>false,
 'max_amount_minor'=>100000000, 'debug'=>false,
];
