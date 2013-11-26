<?

require_once '../ZonesManager.php';

function NormalizeLineBreaks( $s )
{
    $s = str_replace( "\r\n", "\n", $s );
    $s = str_replace( "\r", "\n", $s );
    return $s;
}

function AreConfigsEqual( $c1, $c2 )
{
    $c1 = trim( NormalizeLineBreaks( $c1 ) );
    $c2 = trim( NormalizeLineBreaks( $c2 ) );
    return $c1 == $c2;
}

function CheckTest( $str, $expect, $callback )
{
    try
    {
        $zm = \ZonesManager\ZonesManager::FromString( $str );
        $callback( $zm );
        $result = $zm->GenerateConfig();
        if( !AreConfigsEqual( $result, $expect ) )
        {
            echo "<pre>Test failed with wrong answer:\n------ Got:\n$result\n------ Excected:\n$expect\n------ Test case:\n$str</pre>";
            return false;
        }
        return true;
    }
    catch( Exception $ex )
    {
        echo "<pre>Test failed with exception: " . $ex->getMessage() . "\n------ Test case:\n$str</pre>";
        return false;
    }
}