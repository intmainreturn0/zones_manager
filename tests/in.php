<?

/*
 *  Testing work with 'IN' marker in dns entries
 */

require_once '_test_utils.php';

$str = <<<'STR'
yandex.ru.   89 IN  A   213.180.204.11
yandex.ru.      IN  A   93.158.134.11
yandex.ru.      IN  A   213.180.193.11

mail.yandex.ru.     IN  A   93.158.134.25
mail.yandex.ru.         A   213.180.193.25
mail.yandex.ru.     IN  A   213.180.204.25
mail.yandex.ru.         A   87.250.250.25

home  IN TXT  "some" "long" "   txt   " "value"
home  IN NS   nshome
STR;

CheckTest( $str, $str, function ( $zm ) // str will remain STR: on generating (saving), entries initially marked IN will have it
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( count( $zm->GetAllDNS() ) === 9 );
    assert( count( $zm->FilterDNS( 'yandex.ru.' ) ) === 3 );
    assert( $zm->FilterDNS( 'home', 'TXT' )[0] == array( 'host' => 'home', 'type' => 'TXT', 'priority' => NULL, 'value' => '"some" "long" "   txt   " "value"', ) );
    // dynamic adding and removing 'IN' marker can't be done using code: just not necessary
} );