<?

/*
 *  Testing unique TTL per line - get, add, remove
 *  TTL in each line must be numeric
 */

require_once '_test_utils.php';

$str = <<<'STR'
$TLL 1h
yandex.ru.   89 IN  A   213.180.204.11
yandex.ru.      IN  A   93.158.134.11
yandex.ru.   18 IN  A   213.180.193.11

mail.yandex.ru.     IN  A   93.158.134.25
mail.yandex.ru.     89  A   213.180.193.25
mail.yandex.ru.     IN  A   213.180.204.25
mail.yandex.ru.     80  A   87.250.250.25

home  IN TXT    "some" "long" "   txt   " "value"
STR;

$expect = <<<'STR'
$TLL 1h
yandex.ru.      IN  A   213.180.204.11
yandex.ru.   10 IN  A   93.158.134.11
yandex.ru.   18 IN  A   213.180.193.11

mail.yandex.ru.  10 IN  A   93.158.134.25
mail.yandex.ru.     89  A   213.180.193.25
mail.yandex.ru.     IN  A   213.180.204.25

home  IN TXT    "some" "long" "   txt   " "value"
home  70 NS     nshome
STR;

CheckTest( $str, $expect, function ( $zm )
{
    /** @var $zm \ZonesManager\ZonesManager */
    assert( $zm->FilterDNS( 'yandex.ru.', 'A' )[0]['ttl'] == 89 );
    assert( $zm->FilterDNS( 'yandex.ru.', 'A' )[1]['ttl'] == null );

    $zm->ReplaceDNS( 'yandex.ru.', 'A', '213.180.204.11', null, null, null, null, null, 0 ); // 0 == remove TTL (not null!)
    $zm->ReplaceDNS( 'yandex.ru.', 'A', '93.158.134.11', null, null, null, null, null, 10 );
    $zm->ReplaceDNS( 'mail.yandex.ru.', 'A', '93.158.134.25', null, null, null, null, null, 10 );
    $zm->RemoveDNS( 'mail.yandex.ru.', 'A', '87.250.250.25' );
    $zm->AddDNS( 'home', 'NS', 'nshome', null, null, 70 );

    assert( $zm->FilterDNS( 'yandex.ru.', 'A' )[0]['ttl'] == null );
} );