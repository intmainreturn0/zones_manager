<?

require_once '_test_utils.php';

$str = <<<'STR'
$TTL 1h             ; parsed
$SOMETHING 2h       ; unknown
example.com.  IN  SOA  ns.example.com. username.example.com. (
    nothing is here
    will be left
    }}
example.com.  NS    ns                    ; parsed
_sipfederationtls._tcp.exmp.ru.   IN  SRV  0  0  5060  sip.exmp.ru.     ; SRV are not supported; again: not parsed, but not corrupted

@             MX    20 mail2.example.com.
STR;

CheckTest( $str, $str, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    // don't do anything - just ensure that after reading and saving of unknown format content remains the same
} );