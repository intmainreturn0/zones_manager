<?

file_exists( '../ZonesManager.php' ) && require_once '../ZonesManager.php';

function NormalizeLineBreaks( $s )
{
    $s = str_replace( "\r\n", "\n", $s );
    $s = str_replace( "\r", "\n", $s );
    return $s;
}

function AreConfigsEqual( $c1, $c2 )
{
    $lines1 = explode( "\n", NormalizeLineBreaks( $c1 ) );
    $lines2 = explode( "\n", NormalizeLineBreaks( $c2 ) );
    if( count( $lines1 ) !== count( $lines2 ) )
        return false;
    for( $i = 0; $i < count( $lines1 ); ++$i )
        if( preg_replace( '/\s/', '', $lines1[$i] ) !== preg_replace( '/\s/', '', $lines2[$i] ) ) // let it be just simple: equality with spaces stripped out
        {
            //echo $i;
            return false;
        }
    return true;
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
            echo "<pre>Test failed with wrong answer:\n------ Got:\n$result\n------ Excected:\n$expect\n------ Test case:\n$str\n\n\n</pre>";
            return false;
        }
        return true;
    }
    catch( Exception $ex )
    {
        echo "<pre>Test failed with exception: " . $ex->getMessage() . "\n------ Test case:\n$str\n\n\n</pre>";
        return false;
    }
}